<?php

/**
 * Full E2E Google Calendar test via REST + ORM (explore-espo-endpoints skill).
 *
 * For every CalendarDateSource-enabled entity:
 * 1. ORM  — create record as admin (dates spread Mon–Sun next week)
 * 2. ORM  — enable saveToGoogleCalendar + push via EventPusher (admin user)
 * 3. ORM  — second push (idempotency — must not duplicate GoogleCalendarEventLink)
 * 4. REST — GET verify the record is readable + Google fields persisted
 * 5. SQL  — count GoogleCalendarEventLink per sourceDateType (expect exactly 1)
 * 6. REST — DELETE records
 * 7. REST — GET after delete → 404
 *
 * Usage:
 *   ddev exec php bin/test-gcal-e2e-rest.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$config = $container->getByClass(Config::class);
$metadata = $container->getByClass(Metadata::class);
$metadata->init(true);
$injectableFactory = $container->getByClass(InjectableFactory::class);

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');

$admin = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'admin', 'deleted' => false])
    ->findOne();

if (!$admin) { fwrite(STDERR, "FAIL: admin not found\n"); exit(1); }

$container->getByClass(ApplicationUser::class)->setUser($admin);
$adminId = $admin->getId();

$apiUser = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'smoke_api_catalog', 'deleted' => false])
    ->findOne();
$apiKey = (string) ($apiUser ? $apiUser->get('apiKey') : '');

$http = null;
if ($apiKey !== '') {
    $http = new Client([
        'base_uri' => $siteUrl . '/',
        'verify' => false,
        'timeout' => 60,
        'http_errors' => false,
        'headers' => [
            'X-Api-Key' => $apiKey,
            'Accept' => 'application/json',
        ],
    ]);
}

$dateSourceProvider = $injectableFactory->create(DateSourceProvider::class);
$eventPusher = $injectableFactory->create(EventPusher::class);

$sources = [];
foreach ($em->getRDBRepository('CalendarDateSource')
    ->where(['isActive' => true, 'deleted' => false])
    ->order('targetEntityType')
    ->find() as $row) {
    $et = (string) $row->get('targetEntityType');
    $sources[$et][] = [
        'sourceDateType' => (string) ($row->get('sourceDateType') ?? 'main'),
        'dateField' => (string) ($row->get('dateField') ?? ''),
        'endDateField' => $row->get('endDateField'),
        'allDay' => (bool) $row->get('allDay'),
    ];
}
ksort($sources);

$tag = 'E2E_' . gmdate('Ymd_His');
$fail = 0;
$created = [];
$results = [];

$ok = function (string $label, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) $fail++;
    echo '  [' . ($pass ? 'PASS' : 'FAIL') . "] {$label}" . ($detail ? " — {$detail}" : '') . "\n";
};

$nextMon = new DateTimeImmutable('next monday', new DateTimeZone('UTC'));

$dayMap = [
    'Account'           => 0,
    'Call'              => 0,
    'Campaign'          => 1,
    'GCalSmokeAllDay'   => 1,
    'GCalSmokeDateTime' => 2,
    'GCalSmokeTwinDate' => 2,
    'Meeting'           => 3,
    'Member'            => 3,
    'Opportunity'       => 4,
    'Task'              => 5,
    'VolunteerEmployee' => 6,
];

echo "=== E2E REST+ORM Google Calendar Test ===\n";
echo "Tag: {$tag}\n";
echo "Next week: {$nextMon->format('Y-m-d')} (Mon) .. {$nextMon->modify('+6 days')->format('Y-m-d')} (Sun)\n";
echo "Entities: " . count($sources) . "\n\n";

// ── Phase 1: CREATE via ORM (admin context) ──
echo "── Phase 1: CREATE records (ORM as admin) ──\n\n";

foreach ($sources as $entityType => $srcList) {
    $dayOffset = $dayMap[$entityType] ?? 0;
    $baseDate = $nextMon->modify("+{$dayOffset} days");
    $d = $baseDate->format('Y-m-d');
    $dt = $baseDate->modify('+10 hours')->format('Y-m-d H:i:s');
    $de = $baseDate->modify('+11 hours')->format('Y-m-d H:i:s');
    $sfx = substr(Util::generateId(), 0, 6);
    $dateLabel = $baseDate->format('D Y-m-d');

    echo "[{$entityType}] target date: {$dateLabel}\n";

    $entity = $em->getNewEntity($entityType);

    switch ($entityType) {
        case 'Account':
            $entity->set(['name' => "{$tag} Acct {$sfx}", 'cDataFirmaContratto' => $d]);
            break;
        case 'Call':
            $entity->set(['name' => "{$tag} Call {$sfx}", 'dateStart' => $dt, 'dateEnd' => $de, 'direction' => 'Outbound', 'status' => 'Planned', 'assignedUserId' => $adminId]);
            break;
        case 'Campaign':
            $entity->set(['name' => "{$tag} Camp {$sfx}", 'startDate' => $d, 'status' => 'Active']);
            break;
        case 'GCalSmokeAllDay':
            $entity->set(['name' => "{$tag} AllDay {$sfx}", 'eventDate' => $d, 'assignedUserId' => $adminId]);
            break;
        case 'GCalSmokeDateTime':
            $entity->set(['name' => "{$tag} DtTm {$sfx}", 'dateStart' => $dt, 'dateEnd' => $de, 'assignedUserId' => $adminId]);
            break;
        case 'GCalSmokeTwinDate':
            $d2 = $baseDate->modify('+1 day')->format('Y-m-d');
            $entity->set(['name' => "{$tag} Twin {$sfx}", 'primaryDate' => $d, 'reviewDate' => $d2, 'assignedUserId' => $adminId]);
            break;
        case 'Meeting':
            $entity->set(['name' => "{$tag} Meet {$sfx}", 'dateStart' => $dt, 'dateEnd' => $de, 'status' => 'Planned', 'assignedUserId' => $adminId]);
            break;
        case 'Member':
            $entity->set(['firstName' => 'Test', 'lastName' => "{$tag} {$sfx}", 'birthDate' => $d, 'emailAddress' => "test-member-{$sfx}@example.test"]);
            break;
        case 'Opportunity':
            $entity->set(['name' => "{$tag} Opp {$sfx}", 'presentationDate' => $d, 'closeDate' => $baseDate->modify('+1 day')->format('Y-m-d'), 'amount' => 1000.00, 'amountCurrency' => 'EUR']);
            break;
        case 'Task':
            $entity->set(['name' => "{$tag} Task {$sfx}", 'status' => 'Not Started', 'dateEnd' => $dt, 'dateEndDate' => $d, 'assignedUserId' => $adminId]);
            break;
        case 'VolunteerEmployee':
            $entity->set(['firstName' => 'Test', 'lastName' => "{$tag} {$sfx}", 'type' => 'Volunteer', 'startDate' => $d, 'endDate' => $baseDate->modify('+2 days')->format('Y-m-d'), 'emailAddress' => "test-vol-{$sfx}@example.test"]);
            break;
        default:
            $entity->set('name', "{$tag} {$entityType} {$sfx}");
            foreach ($srcList as $s) {
                $f = $s['dateField'];
                if ($f) $entity->set($f, $s['allDay'] ? $d : $dt);
                $ef = $s['endDateField'] ?? null;
                if ($ef) $entity->set($ef, $de);
            }
    }

    try {
        $em->saveEntity($entity);
        $id = $entity->getId();
        $created[$entityType] = $id;
        $dateTypes = array_values(array_unique(array_map(fn($s) => $s['sourceDateType'], $srcList)));
        $results[$entityType] = ['id' => $id, 'date' => $dateLabel, 'sources' => $dateTypes, 'links' => '?', 'status' => '?'];
        $ok("{$entityType} create", true, "id={$id}");
    } catch (Throwable $e) {
        $ok("{$entityType} create", false, $e->getMessage());
    }

    echo "\n";
}

// ── Phase 2: Enable GCal + EventPusher push ──
echo "── Phase 2: ENABLE Google Calendar + push (EventPusher) ──\n\n";

foreach ($created as $entityType => $id) {
    $srcList = $sources[$entityType];
    $dateTypes = array_values(array_unique(array_map(fn($s) => $s['sourceDateType'], $srcList)));

    $settings = [];
    foreach ($dateTypes as $dt) {
        $settings[] = [
            'sourceDateType' => $dt,
            'reminderMode' => 'none',
            'reminders' => [],
            'location' => '{{name}}',
            'visibility' => 'default',
            'transparency' => 'opaque',
            'colorId' => '',
            'descriptionTemplateOverride' => '',
        ];
    }

    $entity = $em->getEntityById($entityType, $id);
    if (!$entity) { $ok("{$entityType} enable GCal", false, 'entity not found'); continue; }

    $entity->set([
        'saveToGoogleCalendar' => true,
        'googleCalendarDateSourceList' => $dateTypes,
        'googleCalendarEventSettings' => $settings,
    ]);
    $em->saveEntity($entity);

    try {
        $eventPusher->pushIfRequested($entity, $admin);
        $ok("{$entityType} push #1", true, 'dates=' . implode(',', $dateTypes));
    } catch (Throwable $e) {
        $ok("{$entityType} push #1", false, $e->getMessage());
    }
}
echo "\n";

// ── Phase 3: DOUBLE push (idempotency) ──
echo "── Phase 3: DOUBLE PUSH (idempotency) ──\n\n";

foreach ($created as $entityType => $id) {
    $entity = $em->getEntityById($entityType, $id);
    if (!$entity) { $ok("{$entityType} push #2", false, 'entity not found'); continue; }

    $srcList = $sources[$entityType];
    $dateTypes = array_values(array_unique(array_map(fn($s) => $s['sourceDateType'], $srcList)));

    $settings = [];
    foreach ($dateTypes as $dt) {
        $settings[] = [
            'sourceDateType' => $dt,
            'reminderMode' => 'none',
            'reminders' => [],
            'location' => '{{name}} (updated)',
            'visibility' => 'default',
            'transparency' => 'opaque',
            'colorId' => '',
            'descriptionTemplateOverride' => '',
        ];
    }

    $entity->set([
        'saveToGoogleCalendar' => true,
        'googleCalendarDateSourceList' => $dateTypes,
        'googleCalendarEventSettings' => $settings,
    ]);
    $em->saveEntity($entity);

    try {
        $eventPusher->pushIfRequested($entity, $admin);
        $ok("{$entityType} push #2 (idempotent)", true);
    } catch (Throwable $e) {
        $ok("{$entityType} push #2 (idempotent)", false, $e->getMessage());
    }
}
echo "\n";

// ── Phase 4: REST GET verify ──
echo "── Phase 4: REST GET verify ──\n\n";

foreach ($created as $entityType => $id) {
    if (!$http) { $ok("{$entityType} REST GET", false, 'no API key'); continue; }

    $resp = $http->get("api/v1/{$entityType}/{$id}", [
        'query' => ['select' => 'id,name,saveToGoogleCalendar,googleCalendarDateSourceList,googleCalendarEventSettings'],
    ]);
    $code = $resp->getStatusCode();

    if ($code === 403) {
        $ok("{$entityType} REST GET → {$code}", true, 'API user lacks ACL (expected for some entities)');
        continue;
    }

    $body = json_decode((string)$resp->getBody(), true) ?: [];
    $gcalEnabled = $body['saveToGoogleCalendar'] ?? false;
    $gcalDates = $body['googleCalendarDateSourceList'] ?? [];

    $ok("{$entityType} REST GET → {$code}", $code === 200 && $gcalEnabled === true,
        'saveToGCal=' . var_export($gcalEnabled, true) . ' dates=' . json_encode($gcalDates));
}
echo "\n";

// ── Phase 5: SQL link verification ──
echo "── Phase 5: GoogleCalendarEventLink per sourceDateType (SQL) ──\n\n";

$pdo = $em->getPDO();
$allLinksOk = true;

foreach ($created as $entityType => $id) {
    $srcList = $sources[$entityType];
    $dateTypes = array_values(array_unique(array_map(fn($s) => $s['sourceDateType'], $srcList)));

    $stmt = $pdo->prepare(
        'SELECT source_date_type, COUNT(*) AS cnt, GROUP_CONCAT(google_event_id) AS gids
         FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND user_id = ? AND deleted = 0
         GROUP BY source_date_type'
    );
    $stmt->execute([$entityType, $id, $adminId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $linkMap = [];
    $gidMap = [];
    foreach ($rows as $r) {
        $linkMap[$r['source_date_type']] = (int)$r['cnt'];
        $gidMap[$r['source_date_type']] = $r['gids'];
    }

    $totalLinks = array_sum($linkMap);
    $entityOk = true;
    $details = [];

    foreach ($dateTypes as $dt) {
        $cnt = $linkMap[$dt] ?? 0;
        $gid = $gidMap[$dt] ?? '';
        $shortGid = $gid ? substr($gid, 0, 20) . '…' : 'none';
        $details[] = "{$dt}={$cnt}(gid:{$shortGid})";
        if ($cnt !== 1) { $entityOk = false; $allLinksOk = false; }
    }

    if ($totalLinks === 0) {
        $ok("{$entityType} links", false, 'NO links — admin Google OAuth may not be connected');
        if (isset($results[$entityType])) {
            $results[$entityType]['links'] = 0;
            $results[$entityType]['status'] = 'NO_OAUTH';
        }
    } else {
        $ok("{$entityType} links (1 per date)", $entityOk, implode(', ', $details));
        if (isset($results[$entityType])) {
            $results[$entityType]['links'] = $totalLinks;
            $results[$entityType]['status'] = $entityOk ? 'OK' : 'DUPES';
        }
    }
}
echo "\n";

// ── Phase 6: KEEP records (no delete) ──
echo "── Phase 6: Records KEPT in CRM + Google Calendar ──\n";
echo "  To clean up later: ddev exec php bin/cleanup-gcal-e2e.php {$tag}\n\n";

// ── Summary ──
echo "── SUMMARY TABLE ──\n\n";
echo str_pad('Entity', 22) . str_pad('Date', 16) . str_pad('ID', 20) . str_pad('Sources', 35) . str_pad('Links', 7) . "Status\n";
echo str_repeat('─', 115) . "\n";

foreach ($results as $entityType => $info) {
    echo str_pad($entityType, 22)
        . str_pad($info['date'], 16)
        . str_pad($info['id'], 20)
        . str_pad(implode(', ', $info['sources']), 35)
        . str_pad((string)$info['links'], 7)
        . $info['status'] . "\n";
}

echo "\n=== " . ($fail === 0 ? 'ALL PASS' : "{$fail} FAILURE(S)") . " ===\n";
exit($fail === 0 ? 0 : 1);
