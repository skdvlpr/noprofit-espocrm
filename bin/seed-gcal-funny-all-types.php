<?php

/**
 * Purge test junk, then seed T- records with funny names for EVERY CalendarDateSource entity.
 * Pushes to Google Calendar. Does NOT delete seeded rows (morning manual QA).
 *
 * Usage:
 *   ddev exec php bin/seed-gcal-funny-all-types.php
 *   ddev exec php bin/seed-gcal-funny-all-types.php --skip-cleanup
 */

declare(strict_types=1);

require __DIR__ . '/lib/GcalTestFixtures.php';

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\InjectableFactory;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventRemover;
use Espo\ORM\EntityManager;

const SEED_TAG = 'T-funny-morning';
const DATE_FROM = '2026-06-14';
const DATE_TO = '2026-06-27';

$skipCleanup = in_array('--skip-cleanup', $argv ?? [], true);

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
$adminId = $admin->getId();
$eventRemover = $injectableFactory->create(EventRemover::class);
$eventPusher = $injectableFactory->create(EventPusher::class);
$pdo = $em->getPDO();

/** @var array<string, list<array{sourceDateType: string, dateField: string, endDateField?: mixed, allDay?: bool}>> $sources */
$sources = [];

foreach ($em->getRDBRepository('CalendarDateSource')
    ->where(['isActive' => true, 'deleted' => false])
    ->order('sortOrder')
    ->find() as $row) {
    $et = (string) $row->get('targetEntityType');
    $sources[$et][] = [
        'sourceDateType' => (string) ($row->get('sourceDateType') ?? 'main'),
        'dateField' => (string) ($row->get('dateField') ?? ''),
        'endDateField' => $row->get('endDateField'),
        'allDay' => (bool) $row->get('allDay'),
    ];
}

$sources = GcalTestFixtures::filterSourcesByScope($sources, 'all');
ksort($sources);

/** @var array<string, array{date: string, time?: string, endTime?: string}> $schedule */
$schedule = [
    'Account' => ['date' => '2026-06-14'],
    'Call' => ['date' => '2026-06-15', 'time' => '10:00:00', 'endTime' => '10:45:00'],
    'Meeting' => ['date' => '2026-06-16', 'time' => '11:00:00', 'endTime' => '12:00:00'],
    'Task' => ['date' => '2026-06-17', 'time' => '17:00:00', 'endTime' => '17:30:00'],
    'Opportunity' => ['date' => '2026-06-18'],
    'VolunteerEmployee' => ['date' => '2026-06-19'],
    'Member' => ['date' => '2026-06-21'],
    'Campaign' => ['date' => '2026-06-22'],
    'GCalSmokeAllDay' => ['date' => '2026-06-23'],
    'GCalSmokeDateTime' => ['date' => '2026-06-24', 'time' => '14:00:00', 'endTime' => '15:00:00'],
    'GCalSmokeTwinDate' => ['date' => '2026-06-25'],
];

echo "=== Google Calendar funny seed (all types) ===\n";
echo "Tag: " . SEED_TAG . " | dates: " . DATE_FROM . " .. " . DATE_TO . "\n";
echo "Entities: " . count($sources) . " (production + GCalSmoke*)\n\n";

if (!$skipCleanup) {
    echo "── Phase 1: full test-data cleanup ──\n";
    passthru('php ' . escapeshellarg(__DIR__ . '/cleanup-all-gcal-test-data.php'), $cleanupExit);

    if ($cleanupExit !== 0) {
        fwrite(STDERR, "WARN: cleanup exited with code {$cleanupExit}\n");
    }

    echo "\n";
}

echo "── Phase 2: create funny T- records + push Google (KEPT) ──\n\n";

$suffix = substr(SEED_TAG, -6) . 'fn';
/** @var list<array<string, mixed>> $created */
$created = [];
$fail = 0;

foreach ($sources as $entityType => $srcList) {
    $slot = $schedule[$entityType] ?? ['date' => '2026-06-20'];
    $baseDate = new DateTimeImmutable($slot['date'], new DateTimeZone('UTC'));

    if (isset($slot['time'])) {
        $baseDate = new DateTimeImmutable($slot['date'] . ' ' . $slot['time'], new DateTimeZone('UTC'));
    }

    $attrs = GcalTestFixtures::buildAttributes($entityType, [
        'suffix' => $suffix,
        'baseDate' => $baseDate,
        'endTime' => $slot['endTime'] ?? null,
        'adminId' => $adminId,
        'sources' => $srcList,
        'titleStyle' => 'funny',
    ]);

    if ($entityType === 'Opportunity') {
        $attrs['presentationDate'] = '2026-06-18';
        $attrs['closeDate'] = '2026-06-24';
    }

    if ($entityType === 'VolunteerEmployee') {
        $attrs['startDate'] = '2026-06-19';
        $attrs['endDate'] = '2026-06-26';
    }

    if ($entityType === 'Task') {
        $attrs['dateStart'] = $slot['date'];
        if (isset($slot['time'], $slot['endTime'])) {
            $attrs['dateEnd'] = $slot['date'] . ' ' . $slot['time'];
            $attrs['dateEndDate'] = $slot['date'];
        }
    }

    if ($entityType === 'GCalSmokeTwinDate') {
        $attrs['primaryDate'] = '2026-06-25';
        $attrs['reviewDate'] = '2026-06-26';
    }

    $entity = $em->getNewEntity($entityType);
    $entity->set($attrs);

    try {
        $em->saveEntity($entity);
    } catch (Throwable $e) {
        echo "  [FAIL] {$entityType} create — {$e->getMessage()}\n";
        $fail++;
        continue;
    }

    $dateTypes = array_values(array_unique(array_column($srcList, 'sourceDateType')));
    $entity->set([
        'saveToGoogleCalendar' => true,
        'googleCalendarDateSourceList' => $dateTypes,
        'googleCalendarEventSettings' => GcalTestFixtures::buildEventSettings($dateTypes),
    ]);
    $em->saveEntity($entity);

    try {
        $eventPusher->pushIfRequested($entity, $admin);
    } catch (Throwable $e) {
        echo "  [WARN] {$entityType} push — {$e->getMessage()}\n";
    }

    $linkStmt = $pdo->prepare(
        'SELECT source_date_type FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND user_id = ? AND deleted = 0'
    );
    $linkStmt->execute([$entityType, $entity->getId(), $adminId]);
    $links = $linkStmt->fetchAll(PDO::FETCH_COLUMN);

    $nameField = GcalTestFixtures::nameField($entityType);
    $isPerson = in_array($entityType, ['Member', 'VolunteerEmployee', 'Contact'], true);
    $displayName = $isPerson
        ? trim((string) $entity->get('firstName') . ' ' . (string) $entity->get($nameField))
        : (string) ($entity->get($nameField) ?? $entity->get('name') ?? '');

    $created[] = [
        'entityType' => $entityType,
        'id' => $entity->getId(),
        'name' => $displayName,
        'slot' => $slot['date'],
        'links' => count($links),
        'expected' => count($dateTypes),
    ];

    $status = count($links) === count($dateTypes) ? 'OK' : 'WARN';
    echo "  [{$status}] {$entityType} links=" . count($links) . '/' . count($dateTypes)
        . " | {$displayName}\n";
}

echo "\n── Summary (KEPT for morning QA) ──\n";
echo str_pad('Entity', 22) . str_pad('Date', 12) . str_pad('Links', 8) . "Name / ID\n";
echo str_repeat('-', 95) . "\n";

foreach ($created as $row) {
    echo str_pad($row['entityType'], 22)
        . str_pad($row['slot'], 12)
        . str_pad($row['links'] . '/' . $row['expected'], 8)
        . $row['name'] . ' / ' . $row['id'] . "\n";
}

echo "\nGoogle Calendar: " . DATE_FROM . "–" . DATE_TO . " (admin TZ).\n";
echo "Cleanup later: ddev exec php bin/cleanup-all-gcal-test-data.php\n";
echo "\n=== " . ($fail === 0 ? 'SEED COMPLETE' : "{$fail} CREATE FAILURES") . " ===\n";

exit($fail === 0 ? 0 : 1);
