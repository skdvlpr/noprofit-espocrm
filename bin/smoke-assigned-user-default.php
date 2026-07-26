<?php

declare(strict_types=1);


require __DIR__ . '/lib/refuse-production.php';


/**
 * Smoke: assignedUser defaults to current user on record create.
 *
 * Usage: ddev exec php bin/smoke-assigned-user-default.php
 *
 * Auth: provisions ephemeral API user `smoke_api_assigned_user` (no hardcoded keys).
 * Optional override: ESPOCRM_API_KEY env var.
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\Binding\BindingContainerBuilder;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Tools\Record\AssignedUserDefaultApplier;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;

const SMOKE_API_USER = 'smoke_api_assigned_user';

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Metadata $metadata */
$metadata = $container->get('metadata');
/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);
/** @var Config $config */
$config = $container->getByClass(Config::class);

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $mark = $pass ? 'PASS' : 'FAIL';
    echo "  [$mark] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$hook = 'Espo\\Modules\\NonprofitEspocrm\\Classes\\RecordHooks\\AssignedUser\\BeforeCreate';
$populator = 'Espo\\Modules\\NonprofitEspocrm\\Core\\Record\\Defaults\\AssignedUserPopulator';

$contactHooks = $metadata->get(['recordDefs', 'Contact', 'beforeCreateHookClassNameList']) ?? [];
$ok('Contact beforeCreate hook registered', in_array($hook, $contactHooks, true));
$ok(
    'Contact defaults populator registered',
    ($metadata->get(['recordDefs', 'Contact', 'defaultsPopulatorClassName']) ?? '') === $populator
);

$mealCountHooks = $metadata->get(['recordDefs', 'MealCount', 'beforeCreateHookClassNameList']) ?? [];
$ok('MealCount beforeCreate hook registered', in_array($hook, $mealCountHooks, true));

$admin = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['type' => 'admin', 'isActive' => true])
    ->findOne();

if ($admin === null) {
    $ok('admin user available for applier binding', false);
    echo "\nFAILED: no admin user\n";
    exit(1);
}

$binding = BindingContainerBuilder::create()
    ->bindInstance(User::class, $admin)
    ->build();
/** @var AssignedUserDefaultApplier $applier */
$applier = $injectableFactory->createWithBinding(AssignedUserDefaultApplier::class, $binding);

$applierContact = $em->getNewEntity('Contact');
$applierContact->set([
    'firstName' => 'Assigned',
    'lastName' => 'Applier' . date('His'),
]);
$applier->applyIfEmpty($applierContact);
$ok('applier sets assignedUser on empty Contact', $applierContact->get('assignedUserId') === $admin->getId());

$applierContact->set([
    'assignedUserId' => 'some-other-user-id',
    'assignedUserName' => 'Other User',
]);
$applier->applyIfEmpty($applierContact);
$ok('applier keeps explicit assignedUser', $applierContact->get('assignedUserId') === 'some-other-user-id');

$systemApplier = $injectableFactory->create(AssignedUserDefaultApplier::class);
$systemContact = $em->getNewEntity('Contact');
$systemContact->set(['firstName' => 'Sys', 'lastName' => 'Skip' . date('His')]);
$systemApplier->applyIfEmpty($systemContact);
$ok('applier skips system user', $systemContact->get('assignedUserId') === null);

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: siteUrl empty\n");
    exit(1);
}

$envKey = getenv('ESPOCRM_API_KEY');
$apiKey = is_string($envKey) && $envKey !== '' ? $envKey : null;
$apiUserId = null;

if ($apiKey === null) {
    $role = $em->getRDBRepository('Role')
        ->where(['name' => 'Admin', 'deleted' => false])
        ->findOne();
    if ($role === null) {
        fwrite(STDERR, "FAIL: Admin role missing\n");
        exit(1);
    }

    /** @var ?User $apiUser */
    $apiUser = $em->getRDBRepository(User::ENTITY_TYPE)
        ->where(['userName' => SMOKE_API_USER, 'deleted' => false])
        ->findOne();

    if ($apiUser === null) {
        $apiUser = $em->createEntity(User::ENTITY_TYPE, [
            'userName' => SMOKE_API_USER,
            'type' => User::TYPE_API,
            'authMethod' => ApiKeyLogin::NAME,
            'rolesIds' => [$role->getId()],
            'firstName' => 'Smoke',
            'lastName' => 'AssignedUser',
        ]);
        $em->saveEntity($apiUser);
        $apiUser = $em->getRDBRepository(User::ENTITY_TYPE)->getById($apiUser->getId());
    }

    $apiKey = $apiUser->get('apiKey');
    if (!is_string($apiKey) || $apiKey === '') {
        $apiKey = Util::generateApiKey();
        $apiUser->set('apiKey', $apiKey);
        $em->saveEntity($apiUser);
    }

    $apiUserId = $apiUser->getId();
}

$client = new Client([
    'base_uri' => $siteUrl . '/',
    'http_errors' => false,
    'verify' => false,
    'headers' => [
        'X-Api-Key' => $apiKey,
        'Content-Type' => 'application/json',
    ],
]);

$who = $client->get('api/v1/App/user');
$whoBody = json_decode((string) $who->getBody(), true) ?: [];
$whoUserId = $whoBody['user']['id'] ?? null;
$ok('API auth App/user', $who->getStatusCode() === 200 && is_string($whoUserId) && $whoUserId !== '');

if ($apiUserId === null && is_string($whoUserId)) {
    $apiUserId = $whoUserId;
}

$payload = [
    'firstName' => 'Assigned',
    'lastName' => 'Rest' . date('His'),
];
$create = $client->post('api/v1/Contact', ['json' => $payload]);
$createBody = json_decode((string) $create->getBody(), true) ?: [];
$createdId = $createBody['id'] ?? null;
$ok(
    'Contact create via API persists assignedUser',
    $create->getStatusCode() === 200
        && is_string($createdId)
        && ($createBody['assignedUserId'] ?? null) === $whoUserId,
    'code=' . $create->getStatusCode() . ' assigned=' . ($createBody['assignedUserId'] ?? 'null')
);

if (is_string($createdId) && $createdId !== '') {
    $client->delete('api/v1/Contact/' . $createdId);
}

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
