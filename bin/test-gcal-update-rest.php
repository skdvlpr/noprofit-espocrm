<?php

/**
 * REST update smoke: PUT seeded GCal records via /api/v1, verify via GET.
 *
 * Usage: ddev exec php bin/test-gcal-update-rest.php
 */

declare(strict_types=1);

require __DIR__ . '/lib/GcalTestFixtures.php';

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;

const API_USER = 'smoke_api_catalog';
const TAG = 'REST-UPD';
const APP_TZ = GcalTestFixtures::DEFAULT_APP_TIMEZONE;

/** @var array<string, array{id: string, updates: array<string, mixed>, checks: array<string, mixed>}> $cases */
$cases = [
    'Call' => [
        'id' => '6a3314e23e307c412',
        'updates' => [
            'name' => 'T- Il gatto ha prenotato la meeting room [' . TAG . ']',
            'dateStart' => GcalTestFixtures::wallClockToUtcStorage('2026-06-15 11:00:00', APP_TZ),
            'dateEnd' => GcalTestFixtures::wallClockToUtcStorage('2026-06-15 11:30:00', APP_TZ),
        ],
        'checks' => [
            'nameContains' => TAG,
            'crmStartTime' => '11:00',
            'crmEndTime' => '11:30',
            'utcStart' => GcalTestFixtures::wallClockToUtcStorage('2026-06-15 11:00:00', APP_TZ),
        ],
    ],
    'Meeting' => [
        'id' => '6a3314e4a0ca9442d',
        'updates' => [
            'name' => 'T- Chi ha mangiato l\'ultimo cannolo? [' . TAG . ']',
            'dateStart' => GcalTestFixtures::wallClockToUtcStorage('2026-06-16 14:00:00', APP_TZ),
            'dateEnd' => GcalTestFixtures::wallClockToUtcStorage('2026-06-16 15:00:00', APP_TZ),
        ],
        'checks' => [
            'nameContains' => TAG,
            'crmStartTime' => '14:00',
            'crmEndTime' => '15:00',
            'utcStart' => GcalTestFixtures::wallClockToUtcStorage('2026-06-16 14:00:00', APP_TZ),
        ],
    ],
    'GCalSmokeDateTime' => [
        'id' => '6a3314e3549eab903',
        'updates' => [
            'name' => 'T- Macchina del tempo 14:00–15:00 [' . TAG . ']',
            'dateStart' => GcalTestFixtures::wallClockToUtcStorage('2026-06-24 15:00:00', APP_TZ),
            'dateEnd' => GcalTestFixtures::wallClockToUtcStorage('2026-06-24 16:00:00', APP_TZ),
        ],
        'checks' => [
            'nameContains' => TAG,
            'crmStartTime' => '15:00',
            'crmEndTime' => '16:00',
            'utcStart' => GcalTestFixtures::wallClockToUtcStorage('2026-06-24 15:00:00', APP_TZ),
        ],
    ],
    'Task' => [
        'id' => '6a3314e689669df5c',
        'updates' => [
            'name' => 'T- Contare i chicchi di riso fino a mezzanotte [' . TAG . ']',
            'status' => 'Started',
        ],
        'checks' => [
            'nameContains' => TAG,
            'status' => 'Started',
        ],
    ],
];

$app = new Application();
$container = $app->getContainer();
$config = $container->getByClass(Config::class);
$em = $container->getByClass(EntityManager::class);
$dtUtil = $container->getByClass(DateTimeUtil::class);

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');

if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: siteUrl empty\n");
    exit(1);
}

$user = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => API_USER, 'deleted' => false])
    ->findOne();

if ($user === null) {
    $role = $em->getRDBRepository('Role')->where(['name' => 'Admin', 'deleted' => false])->findOne();

    if ($role === null) {
        fwrite(STDERR, "FAIL: Admin role missing\n");
        exit(1);
    }

    $user = $em->createEntity(User::ENTITY_TYPE, [
        'userName' => API_USER,
        'type' => User::TYPE_API,
        'authMethod' => ApiKeyLogin::NAME,
        'rolesIds' => [$role->getId()],
        'firstName' => 'Smoke',
        'lastName' => 'GCalUpd',
    ]);
    $em->saveEntity($user);
    $user = $em->getEntityById(User::ENTITY_TYPE, $user->getId());
}

$apiKey = $user->get('apiKey');

if (!is_string($apiKey) || $apiKey === '') {
    $apiKey = Util::generateApiKey();
    $user->set('apiKey', $apiKey);
    $em->saveEntity($user);
}

$headers = [
    'X-Api-Key' => $apiKey,
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
];

$http = new Client([
    'base_uri' => $siteUrl,
    'verify' => false,
    'http_errors' => false,
    'timeout' => 60,
]);

$fail = 0;
$pass = 0;

$ok = static function (string $label, bool $passed, string $detail = '') use (&$fail, &$pass): void {
    if ($passed) {
        $pass++;
    } else {
        $fail++;
    }

    echo '  [' . ($passed ? 'PASS' : 'FAIL') . '] ' . $label
        . ($detail !== '' ? ' — ' . $detail : '') . "\n";
};

$reason = static function ($response): string {
    $body = json_decode((string) $response->getBody(), true);
    $reason = $response->getHeaderLine('X-Status-Reason');

    if ($reason !== '') {
        return $reason;
    }

    if (is_array($body)) {
        return (string) ($body['messageTranslation']['label'] ?? $body['message'] ?? json_encode($body));
    }

    return 'code=' . $response->getStatusCode();
};

$get = static function (string $entityType, string $id, string $select) use ($http, $headers) {
    return $http->get("/api/v1/{$entityType}/{$id}", [
        'headers' => $headers,
        'query' => ['select' => $select],
    ]);
};

$countGcalLinks = static function (string $entityType, string $id) use ($http, $headers): array {
    $r = $http->get('/api/v1/GoogleCalendarEventLink', [
        'headers' => $headers,
        'query' => [
            'maxSize' => 20,
            'select' => 'id,sourceDateType,googleEventId,lastSyncedAt',
            'where' => [
                ['type' => 'equals', 'attribute' => 'sourceEntityType', 'value' => $entityType],
                ['type' => 'equals', 'attribute' => 'sourceEntityId', 'value' => $id],
            ],
        ],
    ]);

    if ($r->getStatusCode() === 403) {
        return ['status' => 403, 'count' => 0, 'list' => []];
    }

    $body = json_decode((string) $r->getBody(), true);
    $list = is_array($body['list'] ?? null) ? $body['list'] : [];

    return ['status' => $r->getStatusCode(), 'count' => count($list), 'list' => $list];
};

echo "=== GCal REST update test (PUT → GET) ===\n";
echo "Base: {$siteUrl} | API user: " . API_USER . "\n\n";

$rUser = $http->get('/api/v1/App/user', ['headers' => $headers]);
$ok('GET /api/v1/App/user → 200', $rUser->getStatusCode() === 200, 'code=' . $rUser->getStatusCode());

if ($rUser->getStatusCode() !== 200) {
    exit(1);
}

foreach ($cases as $entityType => $case) {
    $id = $case['id'];
    echo "── {$entityType}/{$id} ──\n";

    $select = match ($entityType) {
        'Task' => 'id,name,status,dateStart,dateEnd,saveToGoogleCalendar,googleCalendarDateSourceList',
        default => 'id,name,dateStart,dateEnd,saveToGoogleCalendar,googleCalendarDateSourceList',
    };

    $before = $get($entityType, $id, $select);
    $ok("GET before → 200", $before->getStatusCode() === 200, $reason($before));

    if ($before->getStatusCode() !== 200) {
        echo "\n";
        continue;
    }

    $beforeRow = json_decode((string) $before->getBody(), true);
    $linksBeforeInfo = $countGcalLinks($entityType, $id);
    $linksBefore = $linksBeforeInfo['count'];
    $googleEventIdsBefore = [];

    foreach ($linksBeforeInfo['list'] as $link) {
        $googleEventIdsBefore[(string) ($link['sourceDateType'] ?? 'main')] = (string) ($link['googleEventId'] ?? '');
    }

    echo "  before: name=" . ($beforeRow['name'] ?? '') . "\n";
    echo "  before: saveToGoogleCalendar=" . json_encode($beforeRow['saveToGoogleCalendar'] ?? null) . "\n";
    echo "  before: gcal links={$linksBefore}\n";

    $put = $http->put("/api/v1/{$entityType}/{$id}", [
        'headers' => $headers,
        'json' => $case['updates'],
    ]);
    $ok('PUT update → 200', $put->getStatusCode() === 200, $reason($put));

    if ($put->getStatusCode() !== 200) {
        echo "\n";
        continue;
    }

    $after = $get($entityType, $id, $select);
    $ok('GET after → 200', $after->getStatusCode() === 200, $reason($after));

    if ($after->getStatusCode() !== 200) {
        echo "\n";
        continue;
    }

    $afterRow = json_decode((string) $after->getBody(), true);
    $name = (string) ($afterRow['name'] ?? '');
    $ok(
        'name contains ' . TAG,
        str_contains($name, (string) $case['checks']['nameContains']),
        'name=' . $name
    );

    if (isset($case['checks']['status'])) {
        $ok(
            'status updated',
            ($afterRow['status'] ?? '') === $case['checks']['status'],
            'status=' . ($afterRow['status'] ?? '')
        );
    }

    if (isset($case['checks']['crmStartTime'])) {
        $dbStart = (string) ($afterRow['dateStart'] ?? '');
        $ok(
            'REST dateStart UTC storage after PUT',
            $dbStart === (string) $case['checks']['utcStart'],
            "got={$dbStart} expected={$case['checks']['utcStart']}"
        );

        $crmStart = $dbStart !== '' ? $dtUtil->convertSystemDateTime($dbStart) : '';
        preg_match('/\d{2}:\d{2}/', $crmStart, $mStart);
        $crmStartTime = $mStart[0] ?? '';
        $ok(
            'CRM dateStart time after PUT',
            $crmStartTime === $case['checks']['crmStartTime'],
            "crm={$crmStart} expectedTime={$case['checks']['crmStartTime']}"
        );

        $dbEnd = (string) ($afterRow['dateEnd'] ?? '');
        $crmEnd = $dbEnd !== '' ? $dtUtil->convertSystemDateTime($dbEnd) : '';
        preg_match('/\d{2}:\d{2}/', $crmEnd, $mEnd);
        $crmEndTime = $mEnd[0] ?? '';
        $ok(
            'CRM dateEnd time after PUT',
            $crmEndTime === $case['checks']['crmEndTime'],
            "crm={$crmEnd} expectedTime={$case['checks']['crmEndTime']}"
        );
    }

    $ok(
        'saveToGoogleCalendar still true',
        ($afterRow['saveToGoogleCalendar'] ?? false) === true,
        'value=' . json_encode($afterRow['saveToGoogleCalendar'] ?? null)
    );

    $linksAfterInfo = $countGcalLinks($entityType, $id);
    $linksAfter = $linksAfterInfo['count'];
    $googleEventIdsAfter = [];

    if ($linksBeforeInfo['status'] === 403 || $linksAfterInfo['status'] === 403 || $linksBefore === 0) {
        $ok('GoogleCalendarEventLink via REST', true, 'skipped (no link rows visible to API user)');
    } else {
        $ok(
            'GoogleCalendarEventLink count unchanged',
            $linksAfter === $linksBefore && $linksBefore > 0,
            "before={$linksBefore} after={$linksAfter}"
        );

        foreach ($linksAfterInfo['list'] as $link) {
            $key = (string) ($link['sourceDateType'] ?? 'main');
            $googleEventIdsAfter[$key] = (string) ($link['googleEventId'] ?? '');
            echo "  link {$key}: googleEventId=" . substr((string) ($link['googleEventId'] ?? ''), 0, 24)
                . '… lastSyncedAt=' . ($link['lastSyncedAt'] ?? '') . "\n";
        }

        $sameIds = $googleEventIdsBefore === $googleEventIdsAfter;
        $ok(
            'same google_event_id per sourceDateType (no duplicate)',
            $sameIds && $googleEventIdsBefore !== [],
            $sameIds ? 'ids stable' : 'before=' . json_encode($googleEventIdsBefore) . ' after=' . json_encode($googleEventIdsAfter)
        );
    }

    echo "\n";
}

echo "── Summary ──\n";
echo "  PASS: {$pass}\n";
echo "  FAIL: {$fail}\n\n";

if ($fail > 0) {
    echo "=== {$fail} FAILURE(S) ===\n";
    exit(1);
}

echo "=== ALL PASS (REST update) ===\n";
exit(0);
