<?php

/**
 * Purge Google Calendar CRM rows + linked/orphan Google events.
 *
 * Default: pattern-based test data only (T-, E2E_, legacy smoke names).
 * --purge-all: delete ALL records for calendar-capable entities (incl. user data).
 *
 * Preserves: CalendarDateSource, CalendarTemplate, Integration config, roles, users.
 *
 * Usage:
 *   ddev exec php bin/cleanup-all-gcal-test-data.php
 *   ddev exec php bin/cleanup-all-gcal-test-data.php --dry-run
 *   ddev exec php bin/cleanup-all-gcal-test-data.php --purge-all
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
$purgeAll = in_array('--purge-all', $argv ?? [], true);

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
    'FONDO DI SFONDO%',
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
    'SELECT DISTINCT target_entity_type FROM calendar_date_source WHERE deleted = 0'
)->fetchAll(PDO::FETCH_COLUMN) as $et) {
    $entityTypes[] = (string) $et;
}
foreach (['Meeting', 'Call', 'Task', 'Member', 'VolunteerEmployee', 'MealCount', 'Document'] as $core) {
    if (!in_array($core, $entityTypes, true)) {
        $entityTypes[] = $core;
    }
}
sort($entityTypes);

$modeLabel = $purgeAll ? 'PURGE ALL calendar-capable CRM records' : 'pattern-based test data';
echo '=== Cleanup Google Calendar data (' . $modeLabel . ') ===' . ($dryRun ? ' (DRY RUN)' : '') . "\n\n";

$totalCrm = 0;
$totalGoogle = 0;

/**
 * @return array<string, \Espo\ORM\Entity>
 */
function collectRecordsForEntity(
    EntityManager $em,
    string $entityType,
    bool $purgeAll,
    array $namePrefixes,
    array $extraPatterns,
    array $personPatterns
): array {
    $nameField = GcalTestFixtures::nameField($entityType);
    $isPerson = in_array($entityType, ['Member', 'VolunteerEmployee', 'Contact'], true);
    $records = [];

    if ($purgeAll) {
        foreach ($em->getRDBRepository($entityType)
            ->where(['deleted' => false])
            ->find() as $record) {
            $records[$record->getId()] = $record;
        }

        return $records;
    }

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

    return $records;
}

function removeGoogleLinksForRecord(
    EntityManager $em,
    EventRemover $eventRemover,
    PDO $pdo,
    string $entityType,
    string $id,
    bool $dryRun
): int {
    $removed = 0;
    $linkStmt = $pdo->prepare(
        'SELECT id FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND deleted = 0'
    );
    $linkStmt->execute([$entityType, $id]);

    foreach ($linkStmt->fetchAll(PDO::FETCH_COLUMN) as $linkId) {
        if ($dryRun) {
            $removed++;
            continue;
        }

        $linkEntity = $em->getEntityById('GoogleCalendarEventLink', $linkId);
        if ($linkEntity !== null) {
            try {
                $eventRemover->removeLink($linkEntity);
            } catch (Throwable) {
                $em->removeEntity($linkEntity);
            }
            $removed++;
        }
    }

    return $removed;
}

foreach ($entityTypes as $entityType) {
    $scopeDefs = $metadata->get(['scopes', $entityType]) ?? [];
    if (!($scopeDefs['entity'] ?? false)) {
        continue;
    }

    $isPerson = in_array($entityType, ['Member', 'VolunteerEmployee', 'Contact'], true);
    $records = collectRecordsForEntity(
        $em,
        $entityType,
        $purgeAll,
        $namePrefixes,
        $extraPatterns,
        $personPatterns
    );

    if ($records === []) {
        continue;
    }

    echo "[{$entityType}] " . count($records) . " record(s)\n";

    foreach ($records as $record) {
        $id = $record->getId();
        $display = $isPerson
            ? trim((string) $record->get('firstName') . ' ' . (string) $record->get('lastName'))
            : (string) ($record->get('name') ?? $id);

        $totalGoogle += removeGoogleLinksForRecord($em, $eventRemover, $pdo, $entityType, $id, $dryRun);

        if (!$dryRun) {
            $em->removeEntity($record);
        }

        $totalCrm++;
        echo "  - {$display} ({$id})\n";
    }
}

if ($purgeAll) {
    echo "\nPurging remaining GoogleCalendarEventLink rows...\n";
    $orphanLinks = $pdo->query(
        'SELECT id FROM google_calendar_event_link WHERE deleted = 0'
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($orphanLinks as $linkId) {
        if ($dryRun) {
            $totalGoogle++;
            continue;
        }

        $linkEntity = $em->getEntityById('GoogleCalendarEventLink', (string) $linkId);
        if ($linkEntity === null) {
            continue;
        }

        try {
            $eventRemover->removeLink($linkEntity);
        } catch (Throwable) {
            $em->removeEntity($linkEntity);
        }
        $totalGoogle++;
    }
}

echo "\nScanning Google Calendar for Espo-linked / orphan test events...\n";

/** @var list<string> $googleNeedles */
$googleNeedles = array_merge(
    $namePrefixes,
    ['T-', 'E2E_', 'SmokeGCal', 'BLOCK3_', 'BLOCK4_', 'Test call', 'Periodo dell\'evento', 'FONDO DI SPONDO', 'FONDO DI SFONDO', 'New Task1222', 'Task da fare', 'Tipologia']
);

$orphanCleaned = 0;
$clientManager = $container->getByClass(ClientManager::class);
$client = $clientManager->create(GoogleIntegrationInstaller::INTEGRATION_ID, $admin->getId());

if ($client instanceof Google) {
    try {
        $pageToken = null;

        do {
            $query = [
                'timeMin' => (new DateTime('2025-01-01'))->format('c'),
                'timeMax' => (new DateTime('2028-01-01'))->format('c'),
                'maxResults' => 2500,
                'singleEvents' => 'true',
                'showDeleted' => 'false',
            ];

            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?' . http_build_query($query);
            $response = $client->request($url);

            foreach ($response['items'] ?? [] as $ev) {
                $summary = (string) ($ev['summary'] ?? '');
                $private = $ev['extendedProperties']['private'] ?? [];
                $isEspoOwned = is_array($private)
                    && (
                        isset($private['espocrmEntityType'])
                        || isset($private['espocrmEntityId'])
                        || isset($private['espocrmUserId'])
                    );

                $match = $purgeAll ? $isEspoOwned : false;

                if (!$match) {
                    foreach ($googleNeedles as $needle) {
                        if ($needle !== '' && stripos($summary, $needle) !== false) {
                            $match = true;
                            break;
                        }
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

            $pageToken = $response['nextPageToken'] ?? null;
        } while ($pageToken !== null);
    } catch (Throwable $e) {
        echo "  WARN: Google scan failed: {$e->getMessage()}\n";
    }
} else {
    echo "  WARN: Google client unavailable — orphan scan skipped\n";
}

echo "\n=== DONE: {$totalCrm} CRM record(s), {$totalGoogle} linked + {$orphanCleaned} orphan Google event(s)"
    . ($dryRun ? ' (dry run)' : '') . " ===\n";
