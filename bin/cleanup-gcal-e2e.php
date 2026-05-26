<?php

/**
 * Cleanup script for E2E Google Calendar test records.
 *
 * Finds all CRM records whose name starts with the given tag (or "E2E_" by default),
 * removes their Google Calendar events via EventRemover, then soft-deletes from CRM.
 *
 * Usage:
 *   ddev exec php bin/cleanup-gcal-e2e.php                   # cleans ALL E2E_* records
 *   ddev exec php bin/cleanup-gcal-e2e.php E2E_20260526_075603  # cleans only that tag
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventRemover;
use Espo\ORM\EntityManager;

$tagPrefix = $argv[1] ?? 'E2E_';

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$metadata = $container->getByClass(Metadata::class);
$metadata->init(true);
$injectableFactory = $container->getByClass(InjectableFactory::class);

$admin = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'admin', 'deleted' => false])
    ->findOne();

if (!$admin) {
    fwrite(STDERR, "FAIL: admin user not found\n");
    exit(1);
}

$container->getByClass(ApplicationUser::class)->setUser($admin);
$eventRemover = $injectableFactory->create(EventRemover::class);

$pdo = $em->getPDO();

$entityTypes = [];
$stmt = $pdo->prepare(
    'SELECT DISTINCT target_entity_type FROM calendar_date_source WHERE is_active = 1 AND deleted = 0'
);
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $et) {
    $entityTypes[] = (string) $et;
}
sort($entityTypes);

echo "=== Cleanup E2E Google Calendar test records ===\n";
echo "Tag prefix: \"{$tagPrefix}\"\n";
echo "Entity types: " . implode(', ', $entityTypes) . "\n\n";

$totalDeleted = 0;
$totalGoogleDeleted = 0;

foreach ($entityTypes as $entityType) {
    $scopeDefs = $metadata->get(['scopes', $entityType]) ?? [];
    if (!($scopeDefs['entity'] ?? false)) continue;

    $nameField = 'name';
    $isPerson = in_array($entityType, ['Member', 'VolunteerEmployee', 'Contact'], true);
    if ($isPerson) {
        $nameField = 'lastName';
    }

    $records = $em->getRDBRepository($entityType)
        ->where([
            "{$nameField}*" => "{$tagPrefix}%",
            'deleted' => false,
        ])
        ->find();

    $count = 0;
    foreach ($records as $record) {
        $id = $record->getId();
        $displayName = $isPerson
            ? trim(($record->get('firstName') ?? '') . ' ' . ($record->get('lastName') ?? ''))
            : (string) ($record->get('name') ?? $id);

        $linkStmt = $pdo->prepare(
            'SELECT id, google_event_id, calendar_id, user_id, source_date_type
             FROM google_calendar_event_link
             WHERE source_entity_type = ? AND source_entity_id = ? AND deleted = 0'
        );
        $linkStmt->execute([$entityType, $id]);
        $links = $linkStmt->fetchAll(PDO::FETCH_ASSOC);

        $googleCount = 0;
        foreach ($links as $linkRow) {
            $linkEntity = $em->getEntityById('GoogleCalendarEventLink', $linkRow['id']);
            if ($linkEntity) {
                try {
                    $eventRemover->removeLink($linkEntity);
                    $googleCount++;
                } catch (Throwable $e) {
                    echo "  WARN: Google delete failed for link {$linkRow['id']}: {$e->getMessage()}\n";
                    $em->removeEntity($linkEntity);
                    $googleCount++;
                }
            }
        }

        $em->removeEntity($record);
        $count++;
        $totalGoogleDeleted += $googleCount;

        echo "  [{$entityType}] {$displayName} (id={$id}) — {$googleCount} Google event(s) removed\n";
    }

    if ($count > 0) {
        echo "  → {$count} record(s) deleted\n\n";
        $totalDeleted += $count;
    }
}

// Also clean orphan Google Calendar events with E2E_ in title
echo "  Scanning Google Calendar for orphan E2E events...\n";

$clientManager = $container->getByClass(\Espo\Core\ExternalAccount\ClientManager::class);
$client = $clientManager->create(\Espo\Modules\GoogleIntegration\Tools\Installer::INTEGRATION_ID, $admin->getId());
$orphanCleaned = 0;

if ($client instanceof \Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google) {
    try {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?'
            . http_build_query([
                'timeMin' => (new DateTime('2025-01-01'))->format('c'),
                'timeMax' => (new DateTime('2027-01-01'))->format('c'),
                'maxResults' => 2500,
                'singleEvents' => 'true',
                'showDeleted' => 'false',
            ]);

        $response = $client->request($url);
        $events = $response['items'] ?? [];

        foreach ($events as $ev) {
            $summary = $ev['summary'] ?? '';
            if (stripos($summary, $tagPrefix) === false) continue;

            try {
                $client->deleteCalendarEvent($ev['id'], 'primary');
                $orphanCleaned++;
            } catch (Throwable $e) {}
        }
    } catch (Throwable $e) {
        echo "  WARN: Google Calendar scan failed: {$e->getMessage()}\n";
    }
}

if ($orphanCleaned > 0) {
    echo "  Cleaned {$orphanCleaned} orphan Google Calendar event(s)\n";
}

if ($totalDeleted === 0 && $orphanCleaned === 0) {
    echo "No records found matching \"{$tagPrefix}*\"\n";
} else {
    echo "=== DONE: {$totalDeleted} CRM record(s), {$totalGoogleDeleted} linked + {$orphanCleaned} orphan Google event(s) removed ===\n";
}
