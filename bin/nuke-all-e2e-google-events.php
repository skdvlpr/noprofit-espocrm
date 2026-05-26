<?php

/**
 * Delete ALL Google Calendar events that contain "E2E_" in their title.
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google as GoogleClient;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\EntityManager;

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$clientManager = $container->getByClass(ClientManager::class);

$admin = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'admin', 'deleted' => false])
    ->findOne();

if (!$admin) { fwrite(STDERR, "admin not found\n"); exit(1); }
$container->getByClass(ApplicationUser::class)->setUser($admin);

$client = $clientManager->create(Installer::INTEGRATION_ID, $admin->getId());
if (!$client instanceof GoogleClient) {
    fwrite(STDERR, "Google client not available\n");
    exit(1);
}

$timeMin = (new DateTime('2025-01-01'))->format('c');
$timeMax = (new DateTime('2027-01-01'))->format('c');

$url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?'
    . http_build_query([
        'timeMin' => $timeMin,
        'timeMax' => $timeMax,
        'maxResults' => 2500,
        'singleEvents' => 'true',
        'showDeleted' => 'false',
    ]);

$response = $client->request($url);
$events = $response['items'] ?? [];

echo "Found " . count($events) . " events total\n\n";

$ok = 0;
$err = 0;
$skip = 0;

foreach ($events as $ev) {
    $gid = $ev['id'] ?? '';
    $summary = $ev['summary'] ?? '(no title)';

    if (stripos($summary, 'E2E_') === false && stripos($summary, 'smoke') === false && stripos($summary, 'RST_TEST') === false) {
        $skip++;
        continue;
    }

    try {
        $client->deleteCalendarEvent($gid, 'primary');
        echo "  DEL: {$summary}\n";
        $ok++;
    } catch (Throwable $e) {
        echo "  ERR: {$summary} — {$e->getMessage()}\n";
        $err++;
    }
}

echo "\nDone: {$ok} deleted, {$err} errors\n";
