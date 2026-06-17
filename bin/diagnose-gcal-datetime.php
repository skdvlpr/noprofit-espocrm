<?php

/**
 * Diagnose CRM vs Google Calendar datetime for a seeded/exported record.
 *
 * Usage:
 *   ddev exec php bin/diagnose-gcal-datetime.php Call 6a32d3e5a9ccc4395
 *   ddev exec php bin/diagnose-gcal-datetime.php GCalSmokeDateTime 6a32d3e843a02cfdf
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\EntityManager;

$entityType = $argv[1] ?? 'Call';
$id = $argv[2] ?? null;

if ($id === null || $id === '') {
    fwrite(STDERR, "Usage: php bin/diagnose-gcal-datetime.php {EntityType} {id}\n");
    exit(1);
}

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$config = $container->getByClass(Config::class);
$dtUtil = $container->getByClass(DateTimeUtil::class);
$factory = $container->getByClass(InjectableFactory::class);

$admin = $em->getRDBRepository('User')->where(['userName' => 'admin', 'deleted' => false])->findOne();

if ($admin === null) {
    fwrite(STDERR, "FAIL: admin user not found\n");
    exit(1);
}

$container->getByClass(ApplicationUser::class)->setUser($admin);

$entity = $em->getEntityById($entityType, $id);

if ($entity === null) {
    fwrite(STDERR, "Entity not found: {$entityType}/{$id}\n");
    exit(1);
}

echo "=== {$entityType}/{$id} datetime diagnosis ===\n";
echo 'app timeZone: ' . ($config->get('timeZone') ?? 'null') . "\n\n";

foreach (['dateStart', 'dateEnd'] as $field) {
    if (!$entity->hasAttribute($field)) {
        continue;
    }

    $raw = (string) $entity->get($field);

    if ($raw === '') {
        continue;
    }

    echo "{$field} DB (UTC storage): {$raw}\n";
    echo "{$field} CRM UI (convertSystemDateTime): " . $dtUtil->convertSystemDateTime($raw) . "\n";
}

$pusher = $factory->create(EventPusher::class);
$provider = $factory->create(DateSourceProvider::class);
$buildRange = new ReflectionMethod($pusher, 'buildDateRangeFromSource');
$buildRange->setAccessible(true);

foreach ($provider->getActiveSourcesForEntityType($entityType) as $source) {
    $key = (string) ($source['sourceDateType'] ?? 'main');
    $range = $buildRange->invoke($pusher, $entity, $source);

    if ($range === null) {
        echo "\nsource {$key}: (no range)\n";
        continue;
    }

    echo "\nsource {$key} EventPusher payload:\n";
    echo '  start: ' . json_encode($range['start'], JSON_UNESCAPED_SLASHES) . "\n";
    echo '  end:   ' . json_encode($range['end'], JSON_UNESCAPED_SLASHES) . "\n";
}

$pdo = $em->getPDO();
$stmt = $pdo->prepare(
    'SELECT source_date_type, google_event_id FROM google_calendar_event_link
     WHERE source_entity_type = ? AND source_entity_id = ? AND deleted = 0'
);
$stmt->execute([$entityType, $id]);

$client = $container->getByClass(ClientManager::class)->create(Installer::INTEGRATION_ID, $admin->getId());

foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
    $sourceDateType = (string) $row['source_date_type'];
    $googleEventId = (string) $row['google_event_id'];
    echo "\nGoogle API ({$sourceDateType}) event {$googleEventId}:\n";

    if (!$client instanceof \Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google) {
        echo "  (Google client unavailable)\n";
        continue;
    }

    try {
        $ev = $client->request(
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($googleEventId)
        );
        echo '  summary: ' . ($ev['summary'] ?? '') . "\n";
        echo '  start: ' . json_encode($ev['start'] ?? [], JSON_UNESCAPED_SLASHES) . "\n";
        echo '  end:   ' . json_encode($ev['end'] ?? [], JSON_UNESCAPED_SLASHES) . "\n";
    } catch (Throwable $e) {
        echo '  ERROR: ' . $e->getMessage() . "\n";
    }
}
