<?php
/**
 * Deep REST smoke: Google Calendar record fields, template scoping, sync metadata.
 *
 * Follows explore-espo-endpoints Workflows A + D.
 * Does NOT call Google APIs (no OAuth in CI); verifies CRM persistence and APIs only.
 *
 * Usage:
 *   ddev exec php bin/smoke-google-calendar-deep.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\SyncMode;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;

const SMOKE_USER = 'smoke_api_catalog';

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Config $config */
$config = $container->getByClass(Config::class);
/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: config siteUrl is empty.\n");
    exit(1);
}

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $m = $pass ? 'PASS' : 'FAIL';
    echo "  [$m] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$role = $em->getRDBRepository('Role')->where(['name' => 'Admin', 'deleted' => false])->findOne();
if ($role === null) {
    fwrite(STDERR, "FAIL: Admin role not found.\n");
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
        'lastName' => 'CalendarDeep',
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
    'base_uri' => $siteUrl,
    'verify' => false,
    'timeout' => 30,
    'http_errors' => false,
    'headers' => [
        'X-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ],
]);

echo "=== Google Calendar deep smoke ===\n";
echo "Base URL: $siteUrl\n\n";

$rUser = $client->get('/api/v1/App/user');
$ok('Workflow A: GET App/user → 200', $rUser->getStatusCode() === 200, 'code=' . $rUser->getStatusCode());

if ($rUser->getStatusCode() !== 200) {
    exit(1);
}

echo "\nTemplate options scoped by entity type\n";

foreach (['Call' => ['Meeting', 'Task', 'Opportunity'], 'Meeting' => ['Call']] as $entityType => $forbiddenTypes) {
    $r = $client->get('/api/v1/GoogleIntegration/calendar/template-options/' . $entityType);
    $body = json_decode((string) $r->getBody(), true) ?: [];
    $templates = is_array($body['templates'] ?? null) ? $body['templates'] : [];
    $allMatch = $templates !== [] && array_reduce(
        $templates,
        static fn (bool $carry, array $row): bool => $carry && ($row['targetEntityType'] ?? '') === $entityType,
        true
    );
    $ok(
        "template-options/$entityType returns only $entityType templates",
        $r->getStatusCode() === 200 && $allMatch,
        'code=' . $r->getStatusCode() . ' count=' . count($templates)
    );

    foreach ($forbiddenTypes as $other) {
        $hasOther = array_filter(
            $templates,
            static fn (array $row): bool => ($row['targetEntityType'] ?? '') === $other
        );
        $ok("template-options/$entityType excludes $other", $hasOther === []);
    }
}

echo "\nCalendarTemplate list filter (where targetEntityType)\n";

$rList = $client->get('/api/v1/CalendarTemplate', [
    'query' => [
        'select' => 'id,name,targetEntityType',
        'maxSize' => 50,
        'where' => [
            [
                'type' => 'equals',
                'attribute' => 'targetEntityType',
                'value' => 'Call',
            ],
            [
                'type' => 'isTrue',
                'attribute' => 'isActive',
            ],
        ],
    ],
]);
$listBody = json_decode((string) $rList->getBody(), true) ?: [];
$list = is_array($listBody['list'] ?? null) ? $listBody['list'] : [];
$listOk = $rList->getStatusCode() === 200 && array_reduce(
    $list,
    static fn (bool $c, array $row): bool => $c && ($row['targetEntityType'] ?? '') === 'Call',
    true
);
$ok('GET CalendarTemplate where targetEntityType=Call', $listOk, 'code=' . $rList->getStatusCode() . ' count=' . count($list));

echo "\nPersist Call with Google Calendar fields (no live Google push for API user)\n";

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$dateStart = $now->modify('+2 days')->format('Y-m-d H:i:s');
$dateEnd = $now->modify('+2 days +1 hour')->format('Y-m-d H:i:s');

$callTemplateId = null;
foreach ($list as $row) {
    if (($row['name'] ?? '') === 'Call — default' || str_contains((string) ($row['name'] ?? ''), 'Call')) {
        $callTemplateId = $row['id'] ?? null;
        break;
    }
}
if ($callTemplateId === null && $list !== []) {
    $callTemplateId = $list[0]['id'] ?? null;
}

$payload = [
    'name' => 'Smoke Google Call ' . substr(Util::generateId(), 0, 8),
    'status' => 'Planned',
    'direction' => 'Outbound',
    'dateStart' => $dateStart,
    'dateEnd' => $dateEnd,
    'saveToGoogleCalendar' => true,
    'googleCalendarId' => 'primary',
    'googleCalendarReminderMode' => 'none',
    'assignedUserId' => $user->getId(),
];
if (is_string($callTemplateId) && $callTemplateId !== '') {
    $payload['googleCalendarTemplateId'] = $callTemplateId;
    $payload['googleCalendarTemplateName'] = 'Call — default';
}

$rCreate = $client->post('/api/v1/Call', ['json' => $payload]);
$createBody = json_decode((string) $rCreate->getBody(), true) ?: [];
$callId = is_string($createBody['id'] ?? null) ? $createBody['id'] : null;
$ok(
    'POST Call with saveToGoogleCalendar → 200',
    $rCreate->getStatusCode() === 200 && $callId !== null,
    'code=' . $rCreate->getStatusCode() . ' reason=' . $rCreate->getHeaderLine('X-Status-Reason')
);

if ($callId !== null) {
    $rGet = $client->get('/api/v1/Call/' . $callId, [
        'query' => ['select' => 'id,name,saveToGoogleCalendar,googleCalendarId,googleCalendarReminderMode,googleCalendarTemplateId'],
    ]);
    $getBody = json_decode((string) $rGet->getBody(), true) ?: [];
    $ok('GET Call persists saveToGoogleCalendar', ($getBody['saveToGoogleCalendar'] ?? false) === true);
    $ok('GET Call persists googleCalendarId', ($getBody['googleCalendarId'] ?? '') === 'primary');
    $client->delete('/api/v1/Call/' . $callId);
}

echo "\nExternalAccount calendarSyncMode (stored in ExternalAccount.data)\n";

$i18nOptions = $metadata->get(['app', 'language', 'en_US', 'ExternalAccount', 'options', 'calendarSyncMode']) ?? [];
if ($i18nOptions === []) {
    $langFile = __DIR__ . '/../custom/Espo/Modules/GoogleIntegration/Resources/i18n/en_US/ExternalAccount.json';
    $lang = is_file($langFile) ? json_decode((string) file_get_contents($langFile), true) : [];
    $i18nOptions = $lang['options']['calendarSyncMode'] ?? [];
}
$i18nKeys = is_array($i18nOptions) ? array_keys($i18nOptions) : [];
$ok('calendarSyncMode i18n options defined', $i18nKeys !== []);
$ok('SyncMode::BIDIRECTIONAL allowed', in_array(SyncMode::BIDIRECTIONAL, SyncMode::ALL, true));
$ok('SyncMode::CRM_TO_GOOGLE allowed', in_array(SyncMode::CRM_TO_GOOGLE, SyncMode::ALL, true));
$ok('SyncMode::GOOGLE_TO_CRM allowed', in_array(SyncMode::GOOGLE_TO_CRM, SyncMode::ALL, true));

$jobClass = 'Espo\\Modules\\GoogleIntegration\\Jobs\\SyncCalendar';
echo '  [INFO] Background job ' . $jobClass . ': '
    . (class_exists($jobClass) ? 'implemented' : 'not in codebase — UI calendarSyncMode only; manual OAuth E2E for push')
    . "\n";

echo "\n";

if ($fail > 0) {
    fwrite(STDERR, "=== FAILED: $fail ===\n");
    exit(1);
}

echo "=== ALL PASS ($fail failures) ===\n";
echo "Note: Live Google push/pull requires a connected External Account (not API user). ";
echo "Test bidirectional/crmToGoogle/googleToCrm in UI after OAuth.\n";
