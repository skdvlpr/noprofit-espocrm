<?php

/**
 * Hard-purge soft-deleted test records (remove from DB entirely).
 *
 * Usage: ddev exec php bin/purge-deleted-test-records.php [--dry-run]
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\InjectableFactory;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventRemover;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv, true);

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$injectableFactory = $container->getByClass(InjectableFactory::class);
$pdo = $em->getPDO();

$admin = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'admin', 'deleted' => false])
    ->findOne();

if (!$admin) {
    fwrite(STDERR, "admin not found\n");
    exit(1);
}

$container->getByClass(ApplicationUser::class)->setUser($admin);
$eventRemover = $injectableFactory->create(EventRemover::class);

/** @var list<array{table: string, entity: string, sql: string, exclude?: list<string>}> */
$queries = [
    [
        'table' => 'member',
        'entity' => 'Member',
        'sql' => "SELECT id, CONCAT(first_name,' ',last_name) AS label FROM member WHERE deleted=1 AND (
            last_name LIKE '%ASSociato%' OR last_name LIKE '%GAY%'
            OR id IN (SELECT entity_id FROM entity_email_address eea
                INNER JOIN email_address ea ON ea.id = eea.email_address_id
                WHERE eea.entity_type='Member' AND ea.name LIKE '%test_associato%')
        )",
    ],
    [
        'table' => 'volunteer_employee',
        'entity' => 'VolunteerEmployee',
        'sql' => "SELECT id, CONCAT(first_name,' ',last_name) AS label FROM volunteer_employee WHERE deleted=1 AND (
            first_name LIKE 'Test%' OR first_name LIKE 'TEst%' OR last_name LIKE 'Test%'
            OR last_name LIKE 'TEst%' OR last_name LIKE '%Manager%' OR last_name LIKE 'Dip Test%'
            OR last_name LIKE 'Test E2E_%'
        )",
    ],
    [
        'table' => 'opportunity',
        'entity' => 'Opportunity',
        'sql' => "SELECT id, name AS label FROM opportunity WHERE deleted=1 AND (
            name LIKE '%Test%' OR name LIKE '%TESTING%' OR name LIKE '%finanz%'
        )",
    ],
    [
        'table' => 'call',
        'entity' => 'Call',
        'sql' => "SELECT id, name AS label FROM `call` WHERE deleted=1 AND (
            name LIKE '%TEST%' OR name LIKE '%Test%'
        )",
    ],
    [
        'table' => 'meeting',
        'entity' => 'Meeting',
        'sql' => "SELECT id, name AS label FROM meeting WHERE deleted=1 AND (
            name LIKE '%Test%' OR name LIKE 'E2E_%' OR name LIKE 'BLOCK3_%'
            OR name LIKE 'Cursor Google%' OR name = '12345'
        )",
    ],
    [
        'table' => 'account',
        'entity' => 'Account',
        'sql' => "SELECT id, name AS label FROM account WHERE deleted=1 AND name LIKE 'Test SRL%'",
    ],
    [
        'table' => 'g_cal_smoke_all_day',
        'entity' => 'GCalSmokeAllDay',
        'sql' => "SELECT id, name AS label FROM g_cal_smoke_all_day WHERE deleted=1",
    ],
    [
        'table' => 'g_cal_smoke_date_time',
        'entity' => 'GCalSmokeDateTime',
        'sql' => "SELECT id, name AS label FROM g_cal_smoke_date_time WHERE deleted=1",
    ],
    [
        'table' => 'g_cal_smoke_twin_date',
        'entity' => 'GCalSmokeTwinDate',
        'sql' => "SELECT id, name AS label FROM g_cal_smoke_twin_date WHERE deleted=1",
    ],
];

echo $dryRun ? "=== DRY RUN purge deleted test records ===\n\n" : "=== Purge deleted test records ===\n\n";

$purged = 0;

foreach ($queries as $q) {
    $entityType = $q['entity'];
    $stmt = $pdo->query($q['sql']);
    if ($stmt === false) {
        continue;
    }

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = $row['id'];
        $label = $row['label'];
        echo "[{$entityType}] {$label} (id={$id})\n";

        if ($dryRun) {
            continue;
        }

        $linkStmt = $pdo->prepare(
            'SELECT id FROM google_calendar_event_link WHERE source_entity_type = ? AND source_entity_id = ?'
        );
        $linkStmt->execute([$entityType, $id]);
        foreach ($linkStmt->fetchAll(PDO::FETCH_COLUMN) as $linkId) {
            $link = $em->getEntityById('GoogleCalendarEventLink', $linkId);
            if ($link) {
                try {
                    if (!$link->get('deleted')) {
                        $eventRemover->removeLink($link);
                    } else {
                        $em->getRDBRepository('GoogleCalendarEventLink')->deleteFromDb($linkId, false);
                    }
                } catch (Throwable) {
                    $em->getRDBRepository('GoogleCalendarEventLink')->deleteFromDb($linkId, false);
                }
            }
        }

        $repo = $em->getRDBRepository($entityType);
        try {
            $repo->deleteFromDb($id, true);
            $purged++;
        } catch (Throwable $e) {
            echo "  WARN purge {$id}: {$e->getMessage()}\n";
        }
    }
}

// Orphan active links to missing/deleted smoke entities
$orphanStmt = $pdo->query(
    "SELECT l.id, l.source_entity_type, l.source_entity_id, l.google_event_id
     FROM google_calendar_event_link l
     WHERE l.deleted = 0
       AND (l.source_entity_type LIKE 'GCalSmoke%' OR l.source_entity_id LIKE '6a15%')"
);
foreach ($orphanStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "[orphan link] {$row['source_entity_type']}:{$row['source_entity_id']} gid={$row['google_event_id']}\n";
    if (!$dryRun) {
        $link = $em->getEntityById('GoogleCalendarEventLink', $row['id']);
        if ($link) {
            try {
                $eventRemover->removeLink($link);
            } catch (Throwable) {
                $em->getRDBRepository('GoogleCalendarEventLink')->deleteFromDb($row['id'], false);
            }
        }
    }
}

echo "\n=== " . ($dryRun ? 'Dry run complete' : "Purged {$purged} record(s)") . " ===\n";
