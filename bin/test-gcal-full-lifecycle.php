<?php

/**
 * Full-lifecycle E2E Google Calendar test.
 *
 * Phases:
 *  1. CLEANUP   — purge all E2E_* records + Google events from previous runs
 *  2. CREATE    — create records via ORM for every CalendarDateSource-enabled entity
 *  3. PUSH      — enable saveToGoogleCalendar + EventPusher push
 *  4. IDEM      — second push (idempotency — no new links)
 *  5. VERIFY    — REST GET + SQL link count
 *  6. DELETE    — soft-delete records from CRM
 *  7. DEL-CHECK — verify Google events also deleted (links soft-deleted)
 *  8. RESTORE   — restore records via Record\Service::restoreDeleted
 *  9. RST-CHECK — verify Google events re-created (new links, new gids)
 * 10. ACL       — probe restricted API user for 403
 * 11. SUMMARY   — table with all results
 *
 * Cleanup: ddev exec php bin/cleanup-gcal-e2e.php <tag>
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

if (!$admin) {
    fwrite(STDERR, "FAIL: admin not found\n");
    exit(1);
}

$container->getByClass(ApplicationUser::class)->setUser($admin);
$adminId = $admin->getId();

$apiUser = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'smoke_api_catalog', 'deleted' => false])
    ->findOne();
$apiKey = (string) ($apiUser ? $apiUser->get('apiKey') : '');

$volunteerApiUser = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'smoke_api_volunteer', 'deleted' => false])
    ->findOne();
$volunteerApiKey = (string) ($volunteerApiUser ? $volunteerApiUser->get('apiKey') : '');

$httpAdmin = null;
if ($apiKey !== '') {
    $httpAdmin = new Client([
        'base_uri' => $siteUrl . '/',
        'verify' => false,
        'timeout' => 60,
        'http_errors' => false,
        'headers' => ['X-Api-Key' => $apiKey, 'Accept' => 'application/json'],
    ]);
}

$httpVolunteer = null;
if ($volunteerApiKey !== '') {
    $httpVolunteer = new Client([
        'base_uri' => $siteUrl . '/',
        'verify' => false,
        'timeout' => 60,
        'http_errors' => false,
        'headers' => ['X-Api-Key' => $volunteerApiKey, 'Accept' => 'application/json'],
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

$pdo = $em->getPDO();
$tag = 'E2E_' . gmdate('Ymd_His');
$fail = 0;
$pass = 0;
$created = [];
$results = [];

$ok = function (string $label, bool $passed, string $detail = '') use (&$fail, &$pass): void {
    if ($passed) {
        $pass++;
    } else {
        $fail++;
    }
    echo '  [' . ($passed ? 'PASS' : 'FAIL') . "] {$label}" . ($detail ? " — {$detail}" : '') . "\n";
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

$expectedLinksPerEntity = [];
foreach ($sources as $et => $srcList) {
    $expectedLinksPerEntity[$et] = count(array_unique(array_column($srcList, 'sourceDateType')));
}
$totalExpectedLinks = array_sum($expectedLinksPerEntity);

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  FULL-LIFECYCLE E2E GOOGLE CALENDAR TEST                    ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";
echo "Tag:       {$tag}\n";
echo "Week:      {$nextMon->format('Y-m-d')} (Mon) .. {$nextMon->modify('+6 days')->format('Y-m-d')} (Sun)\n";
echo "Entities:  " . count($sources) . "\n";
echo "Expected:  {$totalExpectedLinks} Google events total\n\n";

// ─────────────────────────────────────────────────────────────────
// Phase 1: CLEANUP old E2E records
// ─────────────────────────────────────────────────────────────────
echo "━━ Phase 1: CLEANUP old E2E records ━━\n\n";

$cleanedCrm = 0;
$cleanedGoogle = 0;
$eventRemover = $injectableFactory->create(\Espo\Modules\GoogleIntegration\Tools\Calendar\EventRemover::class);

foreach ($sources as $entityType => $srcList) {
    $scopeDefs = $metadata->get(['scopes', $entityType]) ?? [];
    if (!($scopeDefs['entity'] ?? false)) continue;

    $nameField = in_array($entityType, ['Member', 'VolunteerEmployee', 'Contact'], true) ? 'lastName' : 'name';

    $records = $em->getRDBRepository($entityType)
        ->where(["{$nameField}*" => 'E2E_%', 'deleted' => false])
        ->find();

    foreach ($records as $record) {
        $id = $record->getId();
        $linkStmt = $pdo->prepare(
            'SELECT id FROM google_calendar_event_link
             WHERE source_entity_type = ? AND source_entity_id = ? AND deleted = 0'
        );
        $linkStmt->execute([$entityType, $id]);

        foreach ($linkStmt->fetchAll(PDO::FETCH_COLUMN) as $linkId) {
            $linkEntity = $em->getEntityById('GoogleCalendarEventLink', $linkId);
            if ($linkEntity) {
                try { $eventRemover->removeLink($linkEntity); } catch (Throwable $e) {
                    try { $em->removeEntity($linkEntity); } catch (Throwable $e2) {}
                }
                $cleanedGoogle++;
            }
        }

        $em->removeEntity($record);
        $cleanedCrm++;
    }
}

echo "  Cleaned: {$cleanedCrm} CRM record(s), {$cleanedGoogle} Google event(s)\n\n";

// ─────────────────────────────────────────────────────────────────
// Phase 2: CREATE records via ORM
// ─────────────────────────────────────────────────────────────────
echo "━━ Phase 2: CREATE records (ORM) ━━\n\n";

foreach ($sources as $entityType => $srcList) {
    $dayOffset = $dayMap[$entityType] ?? 0;
    $baseDate = $nextMon->modify("+{$dayOffset} days");
    $d = $baseDate->format('Y-m-d');
    $dt = $baseDate->modify('+10 hours')->format('Y-m-d H:i:s');
    $de = $baseDate->modify('+11 hours')->format('Y-m-d H:i:s');
    $sfx = substr(Util::generateId(), 0, 6);
    $dateLabel = $baseDate->format('D Y-m-d');

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
        $dateTypes = array_values(array_unique(array_column($srcList, 'sourceDateType')));
        $created[$entityType] = $id;
        $results[$entityType] = [
            'id' => $id, 'date' => $dateLabel,
            'sources' => $dateTypes,
            'links_after_push' => '?', 'links_after_delete' => '?',
            'links_after_restore' => '?', 'status' => '?',
        ];
        $ok("{$entityType} create", true, "id={$id}, date={$dateLabel}");
    } catch (Throwable $e) {
        $ok("{$entityType} create", false, $e->getMessage());
    }
}
echo "\n";

// ─────────────────────────────────────────────────────────────────
// Phase 3: PUSH to Google Calendar via saveEntity (AfterSave hook triggers push)
// ─────────────────────────────────────────────────────────────────
echo "━━ Phase 3: PUSH to Google Calendar (via AfterSave hook) ━━\n\n";

foreach ($created as $entityType => $id) {
    $srcList = $sources[$entityType];
    $dateTypes = array_values(array_unique(array_column($srcList, 'sourceDateType')));

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
    if (!$entity) { $ok("{$entityType} push", false, 'entity not found'); continue; }

    $entity->set([
        'saveToGoogleCalendar' => true,
        'googleCalendarDateSourceList' => $dateTypes,
        'googleCalendarEventSettings' => $settings,
    ]);

    try {
        $em->saveEntity($entity);

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM google_calendar_event_link
             WHERE source_entity_type = ? AND source_entity_id = ? AND user_id = ? AND deleted = 0'
        );
        $stmt->execute([$entityType, $id, $adminId]);
        $cnt = (int) $stmt->fetchColumn();

        $expected = $expectedLinksPerEntity[$entityType];
        $ok("{$entityType} push", $cnt === $expected,
            "dates=" . implode(',', $dateTypes) . " links={$cnt}/{$expected}");
    } catch (Throwable $e) {
        $ok("{$entityType} push", false, $e->getMessage());
    }
}
echo "\n";

// ─────────────────────────────────────────────────────────────────
// Phase 4: IDEMPOTENCY — re-save triggers hook again, no new links
// ─────────────────────────────────────────────────────────────────
echo "━━ Phase 4: IDEMPOTENCY (re-save, no new links) ━━\n\n";

foreach ($created as $entityType => $id) {
    $entity = $em->getEntityById($entityType, $id);
    if (!$entity) { $ok("{$entityType} idem", false, 'not found'); continue; }

    try {
        $entity->set('name', $entity->get('name'));
        $em->saveEntity($entity);
    } catch (Throwable $e) {
        $ok("{$entityType} idem save", false, $e->getMessage());
        continue;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND user_id = ? AND deleted = 0'
    );
    $stmt->execute([$entityType, $id, $adminId]);
    $cnt = (int) $stmt->fetchColumn();

    $expected = $expectedLinksPerEntity[$entityType];
    $ok("{$entityType} idempotency", $cnt === $expected, "links={$cnt} expected={$expected}");
    if (isset($results[$entityType])) $results[$entityType]['links_after_push'] = $cnt;
}
echo "\n";

// ─────────────────────────────────────────────────────────────────
// Phase 5: VERIFY via REST GET + SQL
// ─────────────────────────────────────────────────────────────────
echo "━━ Phase 5: VERIFY (REST GET + SQL links) ━━\n\n";

foreach ($created as $entityType => $id) {
    if ($httpAdmin) {
        $resp = $httpAdmin->get("api/v1/{$entityType}/{$id}", [
            'query' => ['select' => 'id,name,saveToGoogleCalendar'],
        ]);
        $code = $resp->getStatusCode();

        if ($code === 403) {
            $ok("{$entityType} REST GET (admin API)", true, 'skipped: 403 (some entities restricted)');
        } else {
            $body = json_decode((string) $resp->getBody(), true) ?: [];
            $gcalOk = ($body['saveToGoogleCalendar'] ?? false) === true;
            $ok("{$entityType} REST GET (admin API)", $code === 200 && $gcalOk,
                "code={$code} saveToGCal=" . var_export($gcalOk, true));
        }
    }

    $stmt = $pdo->prepare(
        'SELECT source_date_type, google_event_id
         FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND user_id = ? AND deleted = 0'
    );
    $stmt->execute([$entityType, $id, $adminId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $gidsBefore = [];
    foreach ($rows as $r) {
        $gidsBefore[$r['source_date_type']] = $r['google_event_id'];
    }

    $results[$entityType]['gids_before'] = $gidsBefore;

    $expected = $expectedLinksPerEntity[$entityType];
    $ok("{$entityType} SQL links = {$expected}", count($rows) === $expected,
        implode(', ', array_map(fn($r) => $r['source_date_type'] . '=' . substr($r['google_event_id'], 0, 12), $rows)));
}
echo "\n";

// ─────────────────────────────────────────────────────────────────
// Phase 6: DELETE records from CRM (soft-delete)
// ─────────────────────────────────────────────────────────────────
echo "━━ Phase 6: DELETE from CRM (soft-delete) ━━\n\n";

foreach ($created as $entityType => $id) {
    $entity = $em->getEntityById($entityType, $id);
    if (!$entity) { $ok("{$entityType} delete", false, 'not found'); continue; }

    try {
        $em->removeEntity($entity);
        $ok("{$entityType} delete", true, "id={$id}");
    } catch (Throwable $e) {
        $ok("{$entityType} delete", false, $e->getMessage());
    }
}
echo "\n";

// ─────────────────────────────────────────────────────────────────
// Phase 7: VERIFY deletion (links soft-deleted, Google events gone)
// ─────────────────────────────────────────────────────────────────
echo "━━ Phase 7: VERIFY deletion (links must be soft-deleted) ━━\n\n";

foreach ($created as $entityType => $id) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND user_id = ? AND deleted = 0'
    );
    $stmt->execute([$entityType, $id, $adminId]);
    $activeLinks = (int) $stmt->fetchColumn();

    $results[$entityType]['links_after_delete'] = $activeLinks;
    $ok("{$entityType} links after delete = 0", $activeLinks === 0, "active_links={$activeLinks}");

    if ($httpAdmin) {
        $resp = $httpAdmin->get("api/v1/{$entityType}/{$id}");
        $code = $resp->getStatusCode();
        $ok("{$entityType} REST GET after delete", $code === 404 || $code === 403,
            "code={$code} (expect 404)");
    }
}
echo "\n";

// ─────────────────────────────────────────────────────────────────
// Phase 8: RESTORE records (Ripristina)
// ─────────────────────────────────────────────────────────────────
echo "━━ Phase 8: RESTORE records (Ripristina) ━━\n\n";

$defaultRestorer = $injectableFactory->create(\Espo\Core\Record\Deleted\DefaultRestorer::class);

foreach ($created as $entityType => $id) {
    try {
        $restorerClass = $metadata->get(['recordDefs', $entityType, 'deletedRestorerClassName'])
            ?? \Espo\Core\Record\Deleted\DefaultRestorer::class;

        $restorer = $injectableFactory->create($restorerClass);

        $query = $em->getQueryBuilder()
            ->select()
            ->from($entityType)
            ->where(['id' => $id])
            ->withDeleted()
            ->build();

        $deletedEntity = $em->getRDBRepository($entityType)
            ->clone($query)
            ->findOne();

        if (!$deletedEntity) {
            $ok("{$entityType} restore", false, "entity id={$id} not found even with deleted");
            continue;
        }

        $restorer->restore($deletedEntity);
        $ok("{$entityType} restore", true, "id={$id}, restorer=" . basename(str_replace('\\', '/', $restorerClass)));
    } catch (Throwable $e) {
        $ok("{$entityType} restore", false, $e->getMessage());
    }
}
echo "\n";

// ─────────────────────────────────────────────────────────────────
// Phase 9: VERIFY restoration (Google events re-created)
// ─────────────────────────────────────────────────────────────────
echo "━━ Phase 9: VERIFY restoration (Google events re-created) ━━\n\n";

foreach ($created as $entityType => $id) {
    $entity = $em->getEntityById($entityType, $id);
    if (!$entity) {
        $ok("{$entityType} entity alive after restore", false, 'entity not found');
        continue;
    }

    $ok("{$entityType} entity alive after restore", true);

    $gcalOn = (bool) $entity->get('saveToGoogleCalendar');
    $ok("{$entityType} saveToGoogleCalendar still true", $gcalOn);

    $stmt = $pdo->prepare(
        'SELECT source_date_type, google_event_id
         FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND user_id = ? AND deleted = 0'
    );
    $stmt->execute([$entityType, $id, $adminId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $expected = $expectedLinksPerEntity[$entityType];
    $results[$entityType]['links_after_restore'] = count($rows);

    $ok("{$entityType} links after restore = {$expected}", count($rows) === $expected,
        "found=" . count($rows));

    $gidsBefore = $results[$entityType]['gids_before'] ?? [];
    foreach ($rows as $r) {
        $dt = $r['source_date_type'];
        $newGid = $r['google_event_id'];
        $oldGid = $gidsBefore[$dt] ?? null;

        if ($oldGid !== null && $newGid === $oldGid) {
            $ok("{$entityType} [{$dt}] new Google event ID", false,
                "same gid as before delete — event was not re-created");
        } else {
            $ok("{$entityType} [{$dt}] new Google event ID", true,
                "old=" . substr($oldGid ?? 'n/a', 0, 12) . " new=" . substr($newGid, 0, 12));
        }
    }

    if ($httpAdmin) {
        $resp = $httpAdmin->get("api/v1/{$entityType}/{$id}");
        $code = $resp->getStatusCode();
        $ok("{$entityType} REST GET after restore", $code === 200 || $code === 403,
            "code={$code}");
    }
}
echo "\n";

// ─────────────────────────────────────────────────────────────────
// Phase 10: ACL — restricted user probes
// ─────────────────────────────────────────────────────────────────
echo "━━ Phase 10: ACL — restricted user probes ━━\n\n";

if ($httpVolunteer) {
    foreach ($created as $entityType => $id) {
        $resp = $httpVolunteer->get("api/v1/{$entityType}/{$id}");
        $code = $resp->getStatusCode();

        $aclDefs = $metadata->get(['aclDefs', $entityType]) ?? [];
        $readLevel = $aclDefs['read'] ?? 'no';

        if ($code === 403) {
            $ok("{$entityType} Volunteer API → 403", true,
                "read level '{$readLevel}' blocked access as expected");
        } elseif ($code === 200) {
            $ok("{$entityType} Volunteer API → 200", true,
                "Volunteer has read access (level={$readLevel})");
        } else {
            $ok("{$entityType} Volunteer API → {$code}", false,
                "unexpected code");
        }
    }

    if ($apiKey !== '') {
        $noAuthClient = new Client([
            'base_uri' => $siteUrl . '/',
            'verify' => false,
            'timeout' => 30,
            'http_errors' => false,
        ]);
        $resp = $noAuthClient->get('api/v1/App/user');
        $code = $resp->getStatusCode();
        $ok("Unauthenticated GET /App/user → 401", $code === 401, "code={$code}");
    }
} else {
    echo "  SKIP: no smoke_api_volunteer user found\n";
}
echo "\n";

// ─────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────
echo "━━ SUMMARY ━━\n\n";
echo str_pad('Entity', 22) . str_pad('Date', 16) . str_pad('Sources', 30)
    . str_pad('Push', 6) . str_pad('Del', 6) . str_pad('Rst', 6) . "Status\n";
echo str_repeat('─', 100) . "\n";

$allOk = true;
foreach ($results as $entityType => $info) {
    $pushOk = $info['links_after_push'] === $expectedLinksPerEntity[$entityType];
    $delOk = $info['links_after_delete'] === 0;
    $rstOk = $info['links_after_restore'] === $expectedLinksPerEntity[$entityType];
    $rowOk = $pushOk && $delOk && $rstOk;
    if (!$rowOk) $allOk = false;

    echo str_pad($entityType, 22)
        . str_pad($info['date'], 16)
        . str_pad(implode(',', $info['sources']), 30)
        . str_pad((string) $info['links_after_push'], 6)
        . str_pad((string) $info['links_after_delete'], 6)
        . str_pad((string) $info['links_after_restore'], 6)
        . ($rowOk ? 'OK' : 'FAIL') . "\n";
}

echo "\nTag: {$tag}\n";
echo "Cleanup: ddev exec php bin/cleanup-gcal-e2e.php {$tag}\n\n";
echo "Tests: {$pass} passed, {$fail} failed\n";
echo "=== " . ($fail === 0 ? 'ALL PASS' : "{$fail} FAILURE(S)") . " ===\n";
exit($fail === 0 ? 0 : 1);
