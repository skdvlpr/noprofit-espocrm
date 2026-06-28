<?php
/**
 * Smoke: Lead restored in navbar + i18n + REST CRUD (Task 7.1.1–7.1.2).
 *
 * Hygiene: deletes prior QA-/SMOKE- Lead rows before seeding a fresh QA record
 * (settings/config untouched). Leaves the new row for manual user review.
 *
 * Usage:
 *   ddev exec php bin/smoke-lead-restore.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Tools\Installer;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;

const SMOKE_USER = 'smoke_api_catalog';
const QA_PREFIX = 'QA-Lead-';
const SMOKE_PREFIX = 'SMOKE-Lead-';

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Config $config */
$config = $container->getByClass(Config::class);
/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $m = $pass ? 'PASS' : 'FAIL';
    echo "  [$m] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

echo "Run Safehouse installer (Lead in tabList)\n";
(new Installer())->runPostInstall($container);
$roleSetup = $container->getByClass(\Espo\Core\InjectableFactory::class)
    ->create(\Espo\Modules\NonprofitEspocrm\Tools\RoleSetup::class);
$roleSetup->provisionRoles();
$config->update();

$tabStrings = array_filter($config->get('tabList', []) ?? [], 'is_string');
$qcStrings = array_filter($config->get('quickCreateList', []) ?? [], 'is_string');
$ok('Lead in tabList after installer', in_array('Lead', $tabStrings, true));
$ok('Case present in tabList (Segnalazioni)', in_array('Case', $tabStrings, true));
$ok('Lead in quickCreateList', in_array('Lead', $qcStrings, true));
$ok('Case in quickCreateList', in_array('Case', $qcStrings, true));

$itGlobalPath = __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/Global.json';
$itGlobal = is_file($itGlobalPath)
    ? (json_decode((string) file_get_contents($itGlobalPath), true) ?: [])
    : [];
$ok('it_IT scopeNames Lead is Lead', ($itGlobal['scopeNames']['Lead'] ?? '') === 'Lead');
$ok('it_IT scopeNamesPlural Lead is Lead', ($itGlobal['scopeNamesPlural']['Lead'] ?? '') === 'Lead');
$ok('it_IT scopeNames Opportunity is Fondi e Finanziamenti', ($itGlobal['scopeNames']['Opportunity'] ?? '') === 'Fondi e Finanziamenti');
$ok('it_IT Global links.opportunities renamed', ($itGlobal['links']['opportunities'] ?? '') === 'Fondi e Finanziamenti');

$itContactPath = __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/Contact.json';
$itContact = is_file($itContactPath)
    ? (json_decode((string) file_get_contents($itContactPath), true) ?: [])
    : [];
$ok('it_IT Contact links.opportunities renamed', ($itContact['links']['opportunities'] ?? '') === 'Fondi e Finanziamenti');

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: siteUrl empty\n");
    exit(1);
}

$role = $em->getRDBRepository('Role')->where(['name' => 'Admin', 'deleted' => false])->findOne();
if ($role === null) {
    fwrite(STDERR, "FAIL: Admin role missing\n");
    exit(1);
}

$user = $em->getRDBRepository('User')
    ->where(['userName' => SMOKE_USER, 'deleted' => false])
    ->findOne();

if ($user === null) {
    $user = $em->createEntity(User::ENTITY_TYPE, [
        'userName' => SMOKE_USER,
        'type' => User::TYPE_API,
        'authMethod' => ApiKeyLogin::NAME,
        'rolesIds' => [$role->getId()],
        'firstName' => 'Smoke',
        'lastName' => 'LeadRestore',
    ]);
    $em->saveEntity($user);
    $user = $em->getRDBRepository('User')->getById($user->getId());
}

$apiKey = $user->get('apiKey');
if (!is_string($apiKey) || $apiKey === '') {
    $apiKey = Util::generateApiKey();
    $user->set('apiKey', $apiKey);
    $em->saveEntity($user);
}

$client = new Client([
    'base_uri' => $siteUrl . '/api/v1/',
    'headers' => [
        'X-Api-Key' => $apiKey,
        'Content-Type' => 'application/json',
    ],
    'http_errors' => false,
    'timeout' => 30,
]);

echo "\nCleanup prior QA/SMOKE Lead rows\n";
$rList = $client->get('Lead', [
    'query' => [
        'select' => 'id,firstName,lastName',
        'maxSize' => 200,
    ],
]);
$listBody = json_decode((string) $rList->getBody(), true) ?: [];
$deleted = 0;
foreach ($listBody['list'] ?? [] as $row) {
    $first = (string) ($row['firstName'] ?? '');
    if (
        !str_starts_with($first, QA_PREFIX)
        && !str_starts_with($first, SMOKE_PREFIX)
    ) {
        continue;
    }
    $id = $row['id'] ?? null;
    if (!is_string($id) || $id === '') {
        continue;
    }
    $client->delete('Lead/' . $id);
    $deleted++;
}
$ok('Cleanup QA/SMOKE Lead rows', $rList->getStatusCode() === 200, "removed=$deleted");

$qaName = QA_PREFIX . gmdate('Ymd') . '-01';
$payload = [
    'firstName' => $qaName,
    'lastName' => 'Safehouse',
    'status' => 'New',
    'source' => 'Web Site',
];

echo "\nREST Lead CRUD\n";
$rCreate = $client->post('Lead', ['json' => $payload]);
$createBody = json_decode((string) $rCreate->getBody(), true) ?: [];
$leadId = is_string($createBody['id'] ?? null) ? $createBody['id'] : null;
$ok(
    'POST Lead QA seed → 200',
    $rCreate->getStatusCode() === 200 && $leadId !== null,
    'code=' . $rCreate->getStatusCode() . ' reason=' . $rCreate->getHeaderLine('X-Status-Reason')
);

if ($leadId !== null) {
    $rGet = $client->get('Lead/' . $leadId, [
        'query' => ['select' => 'id,firstName,lastName,status'],
    ]);
    $getBody = json_decode((string) $rGet->getBody(), true) ?: [];
    $ok('GET Lead persists QA seed', ($getBody['firstName'] ?? '') === $qaName);
    echo "\n  → QA record kept for user review: Lead/$leadId ($qaName Safehouse)\n";
}

$leadScope = $metadata->get('scopes.Lead') ?? [];
$ok('Lead scope entity=true', ($leadScope['entity'] ?? null) === true);

echo "\n=== ";
echo $fail === 0 ? 'ALL PASS' : ($fail . ' FAILURE(S)');
echo " ===\n";
echo "Manual QA: hard refresh → navbar «Leads» tab → open QA record above.\n";
exit($fail === 0 ? 0 : 1);
