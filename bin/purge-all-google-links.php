<?php

/**
 * Purge ALL GoogleCalendarEventLink records: delete events from Google Calendar, then soft-delete links.
 * Use for a completely clean start.
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\InjectableFactory;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventRemover;
use Espo\ORM\EntityManager;

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
$stmt = $pdo->query(
    'SELECT id, source_entity_type, source_entity_id, source_date_type, google_event_id
     FROM google_calendar_event_link WHERE deleted = 0'
);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Purge ALL Google Calendar event links ===\n";
echo "Found " . count($rows) . " active link(s)\n\n";

$ok = 0;
$err = 0;

foreach ($rows as $r) {
    $linkEntity = $em->getEntityById('GoogleCalendarEventLink', $r['id']);
    if (!$linkEntity) {
        echo "  SKIP: link {$r['id']} not found\n";
        continue;
    }

    $label = "{$r['source_entity_type']}:{$r['source_entity_id']} [{$r['source_date_type']}] gid={$r['google_event_id']}";

    try {
        $eventRemover->removeLink($linkEntity);
        echo "  OK: {$label}\n";
        $ok++;
    } catch (Throwable $e) {
        echo "  ERR: {$label} — {$e->getMessage()}\n";
        try {
            $em->removeEntity($linkEntity);
        } catch (Throwable $e2) {}
        $err++;
    }
}

echo "\n=== DONE: {$ok} removed, {$err} errors ===\n";
