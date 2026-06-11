<?php

/**
 * Fix wall-clock times on existing T- Call/Meeting manual seed rows and re-push to Google.
 * Does not delete records.
 *
 * Usage:
 *   ddev exec php bin/refresh-gcal-manual-timed.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\InjectableFactory;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\ORM\EntityManager;

/** @var array<string, array{date: string, time: string, endTime: string}> $fixes */
$fixes = [
    'Call' => ['date' => '2026-06-15', 'time' => '10:00:00', 'endTime' => '10:45:00'],
    'Meeting' => ['date' => '2026-06-16', 'time' => '11:00:00', 'endTime' => '12:00:00'],
];

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$injectableFactory = $container->getByClass(InjectableFactory::class);

$admin = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'admin', 'deleted' => false])
    ->findOne();

if ($admin === null) {
    fwrite(STDERR, "FAIL: admin user not found\n");
    exit(1);
}

$container->getByClass(ApplicationUser::class)->setUser($admin);
$eventPusher = $injectableFactory->create(EventPusher::class);

echo "=== Refresh T- Call/Meeting times + Google push ===\n\n";

$fail = 0;

foreach ($fixes as $entityType => $slot) {
    $record = $em->getRDBRepository($entityType)
        ->where(['name*' => 'T-%', 'deleted' => false])
        ->order('createdAt', 'DESC')
        ->findOne();

    if ($record === null) {
        echo "  [SKIP] {$entityType} — no T- record found\n";
        $fail++;
        continue;
    }

    $dateStart = $slot['date'] . ' ' . $slot['time'];
    $dateEnd = $slot['date'] . ' ' . $slot['endTime'];

    $record->set([
        'dateStart' => $dateStart,
        'dateEnd' => $dateEnd,
        'saveToGoogleCalendar' => true,
    ]);

    try {
        $em->saveEntity($record);
        $eventPusher->pushIfRequested($record, $admin);
    } catch (Throwable $e) {
        echo "  [FAIL] {$entityType} id={$record->getId()} — {$e->getMessage()}\n";
        $fail++;
        continue;
    }

    echo "  [OK] {$entityType} id={$record->getId()} name={$record->get('name')}\n";
    echo "       dateStart={$dateStart} dateEnd={$dateEnd}\n";
}

echo "\n=== " . ($fail === 0 ? 'REFRESH COMPLETE' : "{$fail} FAILURES") . " ===\n";

exit($fail === 0 ? 0 : 1);
