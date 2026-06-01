<?php

/**
 * Delete leftover manual/automated test CRM records and clear Google Calendar links.
 *
 * Usage: ddev exec php bin/cleanup-test-crm-records.php [--dry-run]
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
$listOnly = in_array('--list', $argv, true);

/** @var list<array{entity: string, field: string, patterns: list<string>}> */
$targets = [
    [
        'entity' => 'Member',
        'field' => 'lastName',
        'patterns' => ['SerGAY ASSociato%', '%test_associato%'],
    ],
    [
        'entity' => 'VolunteerEmployee',
        'field' => 'lastName',
        'patterns' => [
            'Test Manager%',
            'TEst TEst%',
            'Test Volontario%',
            'Test Dipendente%',
            'Dip Test%',
            'Test E2E_%',
        ],
    ],
    [
        'entity' => 'VolunteerEmployee',
        'field' => 'firstName',
        'patterns' => ['Test%', 'TEst%'],
    ],
    [
        'entity' => 'Opportunity',
        'field' => 'name',
        'patterns' => [
            'FONDO DI TESTING%',
            'Test finanziamento%',
            'Finanziamento pasti%',
            'Finanziamemto pasti%',
            'E2E_%',
        ],
    ],
    [
        'entity' => 'Call',
        'field' => 'name',
        'patterns' => [
            'Call TO TEST%',
            'Test call%',
            'E2E_%',
            'BLOCK3_%',
        ],
    ],
    [
        'entity' => 'Meeting',
        'field' => 'name',
        'patterns' => [
            'E2E_%',
            'BLOCK3_%',
            'Test Google%',
            'Cursor Google push smoke%',
            '12345',
        ],
    ],
    [
        'entity' => 'Account',
        'field' => 'name',
        'patterns' => ['Test SRL%', 'E2E_%'],
    ],
    [
        'entity' => 'Task',
        'field' => 'name',
        'patterns' => ['E2E_%'],
    ],
    [
        'entity' => 'Campaign',
        'field' => 'name',
        'patterns' => ['E2E_%'],
    ],
    [
        'entity' => 'GCalSmokeAllDay',
        'field' => 'name',
        'patterns' => ['E2E_%', 'GCalSmoke%'],
    ],
    [
        'entity' => 'GCalSmokeDateTime',
        'field' => 'name',
        'patterns' => ['E2E_%', 'GCalSmoke%'],
    ],
    [
        'entity' => 'GCalSmokeTwinDate',
        'field' => 'name',
        'patterns' => ['E2E_%', 'GCalSmoke%'],
    ],
];

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$injectableFactory = $container->getByClass(InjectableFactory::class);

$admin = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'admin', 'deleted' => false])
    ->findOne();

if (!$admin) {
    fwrite(STDERR, "FAIL: admin not found\n");
    exit(1);
}

$container->getByClass(ApplicationUser::class)->setUser($admin);
$eventRemover = $injectableFactory->create(EventRemover::class);
$pdo = $em->getPDO();

echo $dryRun ? "=== DRY RUN ===\n\n" : "=== Cleanup test CRM records ===\n\n";

/** Never delete (real user-linked Member). */
$excludeIds = [
    'Member:69ff936ea7471e864', // Semen Koksharov
];

$seen = [];
$deleted = 0;
$linksRemoved = 0;

foreach ($targets as $t) {
    $entityType = $t['entity'];
    $field = $t['field'];

    foreach ($t['patterns'] as $pattern) {
        $records = $em->getRDBRepository($entityType)
            ->where([
                "{$field}*" => $pattern,
                'deleted' => false,
            ])
            ->find();

        foreach ($records as $record) {
            $id = $record->getId();
            $key = $entityType . ':' . $id;
            if (isset($seen[$key]) || isset($excludeIds[$key])) {
                continue;
            }
            $seen[$key] = true;

            $display = in_array($entityType, ['Member', 'VolunteerEmployee'], true)
                ? trim(($record->get('firstName') ?? '') . ' ' . ($record->get('lastName') ?? ''))
                : (string) ($record->get('name') ?? $id);

            $linkStmt = $pdo->prepare(
                'SELECT id FROM google_calendar_event_link
                 WHERE source_entity_type = ? AND source_entity_id = ? AND deleted = 0'
            );
            $linkStmt->execute([$entityType, $id]);
            $linkIds = $linkStmt->fetchAll(PDO::FETCH_COLUMN);

            echo "[{$entityType}] {$display} (id={$id}) — " . count($linkIds) . " link(s)\n";

            if ($dryRun) {
                continue;
            }

            foreach ($linkIds as $linkId) {
                $linkEntity = $em->getEntityById('GoogleCalendarEventLink', $linkId);
                if (!$linkEntity) {
                    continue;
                }
                try {
                    $eventRemover->removeLink($linkEntity);
                } catch (Throwable $e) {
                    echo "  WARN link {$linkId}: {$e->getMessage()}\n";
                    $em->removeEntity($linkEntity);
                }
                $linksRemoved++;
            }

            $em->removeEntity($record);
            $deleted++;
        }
    }
}

// Members matched by email in email_address table
$stmt = $pdo->prepare(
    "SELECT m.id, m.first_name, m.last_name
     FROM member m
     INNER JOIN entity_email_address eea ON eea.entity_id = m.id AND eea.entity_type = 'Member' AND eea.deleted = 0
     INNER JOIN email_address ea ON ea.id = eea.email_address_id AND ea.deleted = 0
     WHERE m.deleted = 0 AND ea.name LIKE '%test_associato%'"
);
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $id = $row['id'];
    $key = 'Member:' . $id;
    if (isset($seen[$key]) || isset($excludeIds[$key])) {
        continue;
    }
    $seen[$key] = true;
    $display = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    echo "[Member] {$display} (id={$id}) — by email\n";
    if (!$dryRun) {
        $linkStmt = $pdo->prepare(
            'SELECT id FROM google_calendar_event_link WHERE source_entity_type = ? AND source_entity_id = ? AND deleted = 0'
        );
        $linkStmt->execute(['Member', $id]);
        foreach ($linkStmt->fetchAll(PDO::FETCH_COLUMN) as $linkId) {
            $linkEntity = $em->getEntityById('GoogleCalendarEventLink', $linkId);
            if ($linkEntity) {
                try {
                    $eventRemover->removeLink($linkEntity);
                } catch (Throwable) {
                    $em->removeEntity($linkEntity);
                }
                $linksRemoved++;
            }
        }
        $record = $em->getEntityById('Member', $id);
        if ($record) {
            $em->removeEntity($record);
            $deleted++;
        }
    }
}

echo "\n=== " . ($dryRun ? "Would delete {$deleted} (dry-run count skipped)" : "DONE: {$deleted} CRM, {$linksRemoved} links") . " ===\n";
