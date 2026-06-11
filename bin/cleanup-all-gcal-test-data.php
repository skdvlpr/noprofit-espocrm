<?php

/**
 * Purge ALL Google Calendar test CRM rows + linked/orphan Google events.
 * Preserves: CalendarDateSource, CalendarTemplate, Integration config, roles, users.
 *
 * Usage:
 *   ddev exec php bin/cleanup-all-gcal-test-data.php
 *   ddev exec php bin/cleanup-all-gcal-test-data.php --dry-run
 */

declare(strict_types=1);

require __DIR__ . '/lib/GcalTestFixtures.php';

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventRemover;
use Espo\Modules\GoogleIntegration\Tools\Installer as GoogleIntegrationInstaller;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv ?? [], true);

/** @var list<string> $namePrefixes */
$namePrefixes = GcalTestFixtures::cleanupPrefixes(true);

/** @var list<string> $extraPatterns SQL LIKE patterns per non-person entities */
$extraPatterns = [
    'Test call%',
    'Test Call%',
    'Call TO TEST%',
    'Test Google%',
    'Cursor Google push smoke%',
    'FONDO DI SPONDO%',
    'FONDO DI TESTING%',
    'New Task1222%',
    'Task da fare%',
    'Tipologia%',
    'Test finanziamento%',
    'Finanziamento pasti%',
    'Finanziamemto pasti%',
    'SmokeGCal%',
    '12345',
];

/** @var list<string> $personPatterns lastName / firstName */
$personPatterns = [
    'T-%',
    'E2E_%',
    'SmokeGCal%',
    'Test Manager%',
    'TEst TEst%',
    'Test Volontario%',
    'Test Dipendente%',
    'Dip Test%',
    'SerGAY ASSociato%',
    '%test_associato%',
    'Smoke GCal%',
    'GCal %',
];

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$metadata = $container->getByClass(Metadata::class);
$metadata->init(true);
$injectableFactory = $container->getByClass(InjectableFactory::class);

$admin = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'admin', 'deleted' => false])
    ->findOne();

if ($admin === null) {
    fwrite(STDERR, "FAIL: admin user not found\n");
    exit(1);
}

$container->getByClass(ApplicationUser::class)->setUser($admin);
$eventRemover = $injectableFactory->create(EventRemover::class);
$pdo = $em->getPDO();

$entityTypes = [];
foreach ($pdo->query(
    'SELECT DISTINCT target_entity_type FROM calendar_date_source WHERE is_active = 1 AND deleted = 0'
)->fetchAll(PDO::FETCH_COLUMN) as $et) {
    $entityTypes[] = (string) $et;
}
foreach (['Meeting', 'Call', 'Task'] as $core) {
    if (!in_array($core, $entityTypes, true)) {
        $entityTypes[] = $core;
    }
}
sort($entityTypes);

echo '=== Cleanup ALL Google Calendar test data ===' . ($dryRun ? ' (DRY RUN)' : '') . "\n\n";

$totalCrm = 0;
$totalGoogle = 0;

foreach ($entityTypes as $entityType) {
    $scopeDefs = $metadata->get(['scopes', $entityType]) ?? [];
    if (!($scopeDefs['entity'] ?? false)) {
        continue;
    }

    $nameField = GcalTestFixtures::nameField($entityType);
    $isPerson = in_array($entityType, ['Member', 'VolunteerEmployee', 'Contact'], true);
    $records = [];

    foreach ($namePrefixes as $prefix) {
        foreach ($em->getRDBRepository($entityType)
            ->where(["{$nameField}*" => "{$prefix}%", 'deleted' => false])
            ->find() as $record) {
            $records[$record->getId()] = $record;
        }
    }

    $patterns = $isPerson ? $personPatterns : array_merge($namePrefixes, $extraPatterns);
    foreach ($patterns as $pattern) {
        foreach ($em->getRDBRepository($entityType)
            ->where(["{$nameField}*" => $pattern, 'deleted' => false])
            ->find() as $record) {
            $records[$record->getId()] = $record;
        }
    }

    if ($isPerson) {
        foreach (['Test%', 'TEst%', 'Smoke%'] as $fp) {
            foreach ($em->getRDBRepository($entityType)
                ->where(['firstName*' => $fp, 'deleted' => false])
                ->find() as $record) {
                $records[$record->getId()] = $record;
            }
        }
    }

    if ($records === []) {
        continue;
    }

    echo "[{$entityType}] " . count($records) . " record(s)\n";

    foreach ($records as $record) {
        $id = $record->getId();
        $display = $isPerson
            ? trim((string) $record->get('firstName') . ' ' . (string) $record->get('lastName'))
            : (string) ($record->get('name') ?? $id);

        $linkStmt = $pdo->prepare(
            'SELECT id FROM google_calendar_event_link
             WHERE source_entity_type = ? AND source_entity_id = ? AND deleted = 0'
        );
        $linkStmt->execute([$entityType, $id]);

        foreach ($linkStmt->fetchAll(PDO::FETCH_COLUMN) as $linkId) {
            if ($dryRun) {
                $totalGoogle++;
                continue;
            }

            $linkEntity = $em->getEntityById('GoogleCalendarEventLink', $linkId);
            if ($linkEntity !== null) {
                try {
                    $eventRemover->removeLink($linkEntity);
                } catch (Throwable) {
                    $em->removeEntity($linkEntity);
                }
                $totalGoogle++;
            }
        }

        if (!$dryRun) {
            $em->removeEntity($record);
        }

        $totalCrm++;
        echo "  - {$display} ({$id})\n";
    }
}

echo "\nScanning Google Calendar for orphan test events...\n";

/** @var list<string> $googleNeedles */
$googleNeedles = array_merge(
    $namePrefixes,
    ['T-', 'E2E_', 'SmokeGCal', 'BLOCK3_', 'BLOCK4_', 'Test call', 'Periodo dell\'evento', 'FONDO DI SPONDO', 'New Task1222', 'Task da fare', 'Tipologia']
);

$orphanCleaned = 0;
$clientManager = $container->getByClass(ClientManager::class);
$client = $clientManager->create(GoogleIntegrationInstaller::INTEGRATION_ID, $admin->getId());

if ($client instanceof Google) {
    try {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?' . http_build_query([
            'timeMin' => (new DateTime('2025-01-01'))->format('c'),
            'timeMax' => (new DateTime('2028-01-01'))->format('c'),
            'maxResults' => 2500,
            'singleEvents' => 'true',
            'showDeleted' => 'false',
        ]);

        $response = $client->request($url);

        foreach ($response['items'] ?? [] as $ev) {
            $summary = (string) ($ev['summary'] ?? '');
            $match = false;

            foreach ($googleNeedles as $needle) {
                if ($needle !== '' && stripos($summary, $needle) !== false) {
                    $match = true;
                    break;
                }
            }

            if (!$match) {
                continue;
            }

            if ($dryRun) {
                echo "  [orphan] {$summary}\n";
                $orphanCleaned++;
                continue;
            }

            try {
                $client->deleteCalendarEvent($ev['id'], 'primary');
                $orphanCleaned++;
                echo "  [orphan removed] {$summary}\n";
            } catch (Throwable $e) {
                echo "  [WARN] orphan delete failed: {$summary} — {$e->getMessage()}\n";
            }
        }
    } catch (Throwable $e) {
        echo "  WARN: Google scan failed: {$e->getMessage()}\n";
    }
} else {
    echo "  WARN: Google client unavailable — orphan scan skipped\n";
}

echo "\n=== DONE: {$totalCrm} CRM record(s), {$totalGoogle} linked + {$orphanCleaned} orphan Google event(s)"
    . ($dryRun ? ' (dry run)' : '') . " ===\n";
