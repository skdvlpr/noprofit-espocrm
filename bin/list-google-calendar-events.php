<?php

/**
 * List upcoming Google Calendar events via Google API to find orphans.
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

$timeMin = (new DateTime('2026-05-01'))->format('c');
$timeMax = (new DateTime('2026-07-01'))->format('c');

$url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?'
    . http_build_query([
        'timeMin' => $timeMin,
        'timeMax' => $timeMax,
        'maxResults' => 250,
        'singleEvents' => 'true',
        'orderBy' => 'startTime',
        'showDeleted' => 'false',
    ]);

$response = $client->request($url);

if (!is_array($response) || !isset($response['items'])) {
    echo "No items or unexpected response\n";
    var_dump(array_keys($response ?? []));
    exit(1);
}

$events = $response['items'];
echo "Found " . count($events) . " events (May-June 2026)\n\n";

foreach ($events as $ev) {
    $id = $ev['id'] ?? '?';
    $summary = $ev['summary'] ?? '(no title)';
    $status = $ev['status'] ?? '?';
    $start = $ev['start']['dateTime'] ?? $ev['start']['date'] ?? '?';

    $isE2E = stripos($summary, 'E2E_') !== false || stripos($summary, 'smoke') !== false;
    $marker = $isE2E ? ' *** TEST ***' : '';

    echo "  [{$status}] {$start}  {$summary}  (gid={$id}){$marker}\n";
}

echo "\nDone.\n";
