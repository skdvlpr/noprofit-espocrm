<?php

/**
 * One-shot cleanup for manual UI Google Calendar test clutter (non-E2E_ prefix).
 *
 * Usage: ddev exec php bin/cleanup-manual-gcal-tests.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google as GoogleClient;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventRemover;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\EntityManager;

/** @var list<array{entity: string, field: string, pattern: string}> */
$targets = [
    ['entity' => 'Opportunity', 'field' => 'name', 'pattern' => 'FONDO DI TESTING%'],
    ['entity' => 'Call', 'field' => 'name', 'pattern' => 'Call TO TEST%'],
    ['entity' => 'VolunteerEmployee', 'field' => 'lastName', 'pattern' => 'Test Manager%'],
    ['entity' => 'VolunteerEmployee', 'field' => 'lastName', 'pattern' => 'TEst TEst%'],
    ['entity' => 'Member', 'field' => 'lastName', 'pattern' => 'SerGAY ASSociato%'],
];

/** Google event title substrings (orphans without CRM row). */
$googleTitleNeedles = [
    'FONDO DI TESTING',
    'Call TO TEST',
    'Test Manager',
    'TEst TEst',
    'SerGAY ASSociato',
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

if (!$admin) {
    fwrite(STDERR, "FAIL: admin not found\n");
    exit(1);
}

$container->getByClass(ApplicationUser::class)->setUser($admin);
$eventRemover = $injectableFactory->create(EventRemover::class);
$pdo = $em->getPDO();

echo "=== Cleanup manual Google Calendar UI test records ===\n\n";

$crmDeleted = 0;
$googleFromLinks = 0;

foreach ($targets as $t) {
    $entityType = $t['entity'];
    $field = $t['field'];
    $pattern = $t['pattern'];

    $records = $em->getRDBRepository($entityType)
        ->where([
            "{$field}*" => $pattern,
            'deleted' => false,
        ])
        ->find();

    foreach ($records as $record) {
        $id = $record->getId();
        $display = in_array($entityType, ['Member', 'VolunteerEmployee'], true)
            ? trim(($record->get('firstName') ?? '') . ' ' . ($record->get('lastName') ?? ''))
            : (string) ($record->get('name') ?? $id);

        $linkStmt = $pdo->prepare(
            'SELECT id FROM google_calendar_event_link
             WHERE source_entity_type = ? AND source_entity_id = ? AND deleted = 0'
        );
        $linkStmt->execute([$entityType, $id]);

        $gCount = 0;
        foreach ($linkStmt->fetchAll(PDO::FETCH_COLUMN) as $linkId) {
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
            $gCount++;
        }

        $em->removeEntity($record);
        $crmDeleted++;
        $googleFromLinks += $gCount;
        echo "  [{$entityType}] {$display} — {$gCount} Google link(s)\n";
    }
}

echo "\nScanning Google Calendar for orphan manual test events...\n";

$client = $container->getByClass(ClientManager::class)
    ->create(Installer::INTEGRATION_ID, $admin->getId());

$orphanDeleted = 0;

if ($client instanceof GoogleClient) {
    $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?'
        . http_build_query([
            'timeMin' => (new DateTime('2025-01-01'))->format('c'),
            'timeMax' => (new DateTime('2027-01-01'))->format('c'),
            'maxResults' => 2500,
            'singleEvents' => 'true',
            'showDeleted' => 'false',
        ]);

    $response = $client->request($url);
    foreach ($response['items'] ?? [] as $ev) {
        $summary = $ev['summary'] ?? '';
        $match = false;
        foreach ($googleTitleNeedles as $needle) {
            if (stripos($summary, $needle) !== false) {
                $match = true;
                break;
            }
        }
        if (!$match) {
            continue;
        }

        try {
            $client->deleteCalendarEvent($ev['id'], 'primary');
            echo "  DEL Google: {$summary}\n";
            $orphanDeleted++;
        } catch (Throwable $e) {
            echo "  ERR Google: {$summary} — {$e->getMessage()}\n";
        }
    }
}

echo "\n=== DONE: {$crmDeleted} CRM, {$googleFromLinks} linked, {$orphanDeleted} orphan Google ===\n";
