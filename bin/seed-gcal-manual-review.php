<?php

/**
 * Purge old test CRM rows (+ Google events), then seed T- records for manual calendar QA.
 * Date window: 2026-06-14 .. 2026-06-27. Does NOT delete seeded rows.
 *
 * Preserves: CalendarDateSource, CalendarTemplate, Integration config, roles.
 *
 * Usage:
 *   ddev exec php bin/seed-gcal-manual-review.php
 *   ddev exec php bin/seed-gcal-manual-review.php --skip-cleanup
 */

declare(strict_types=1);

require __DIR__ . '/lib/GcalTestFixtures.php';

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventRemover;
use Espo\ORM\EntityManager;

const SEED_TAG = 'T-202606-manual';
const DATE_FROM = '2026-06-14';
const DATE_TO = '2026-06-27';

$skipCleanup = in_array('--skip-cleanup', $argv ?? [], true);

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

$sources = GcalTestFixtures::filterSourcesByScope($sources, 'production');
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
];

echo "=== Google Calendar manual review seed ===\n";
echo "Tag: " . SEED_TAG . " | dates: " . DATE_FROM . " .. " . DATE_TO . "\n";
echo "Entities: " . count($sources) . " (production only, no GCalSmoke*)\n\n";

if (!$skipCleanup) {
    echo "── Phase 1: purge old test records (keep CalendarDateSource/Template) ──\n";

    $prefixes = GcalTestFixtures::cleanupPrefixes(true);
    $entityTypes = array_keys($sources);
    $purgedCrm = 0;
    $purgedGoogle = 0;

    foreach ($entityTypes as $entityType) {
        $nameField = GcalTestFixtures::nameField($entityType);
        $records = [];

        foreach ($prefixes as $prefix) {
            foreach ($em->getRDBRepository($entityType)
                ->where(["{$nameField}*" => "{$prefix}%", 'deleted' => false])
                ->find() as $record) {
                $records[$record->getId()] = $record;
            }
        }

        foreach ($records as $record) {
            $id = $record->getId();
            $linkStmt = $pdo->prepare(
                'SELECT id FROM google_calendar_event_link
                 WHERE source_entity_type = ? AND source_entity_id = ? AND deleted = 0'
            );
            $linkStmt->execute([$entityType, $id]);

            foreach ($linkStmt->fetchAll(PDO::FETCH_COLUMN) as $linkId) {
                $linkEntity = $em->getEntityById('GoogleCalendarEventLink', $linkId);

                if ($linkEntity !== null) {
                    try {
                        $eventRemover->removeLink($linkEntity);
                    } catch (Throwable) {
                        try {
                            $em->removeEntity($linkEntity);
                        } catch (Throwable) {
                        }
                    }

                    $purgedGoogle++;
                }
            }

            $em->removeEntity($record);
            $purgedCrm++;
        }
    }

    echo "  Purged CRM: {$purgedCrm} | Google links/events attempted: {$purgedGoogle}\n\n";
}

echo "── Phase 2: create T- records + push to Google (no cleanup after) ──\n\n";

/** @var list<array<string, mixed>> $created */
$created = [];
$fail = 0;

foreach ($sources as $entityType => $srcList) {
    $slot = $schedule[$entityType] ?? ['date' => '2026-06-20'];
    $baseDate = new DateTimeImmutable($slot['date'], new DateTimeZone('UTC'));

    if (isset($slot['time'])) {
        $baseDate = new DateTimeImmutable($slot['date'] . ' ' . $slot['time'], new DateTimeZone('UTC'));
    }

    $suffix = substr(SEED_TAG, -6) . substr($entityType, 0, 2);
    $attrs = GcalTestFixtures::buildAttributes($entityType, [
        'suffix' => $suffix,
        'baseDate' => $baseDate,
        'endTime' => $slot['endTime'] ?? null,
        'adminId' => $adminId,
        'sources' => $srcList,
    ]);

    if ($entityType === 'Opportunity') {
        $attrs['presentationDate'] = '2026-06-18';
        $attrs['closeDate'] = '2026-06-24';
        $attrs['name'] = GcalTestFixtures::TEST_PREFIX . ' Bando fondazione estiva ' . $suffix;
    }

    if ($entityType === 'VolunteerEmployee') {
        $attrs['startDate'] = '2026-06-19';
        $attrs['endDate'] = '2026-06-26';
    }

    if ($entityType === 'Task' && isset($slot['time'], $slot['endTime'])) {
        $attrs['dateEnd'] = $slot['date'] . ' ' . $slot['time'];
        $attrs['dateEndDate'] = $slot['date'];
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
    $settings = GcalTestFixtures::buildEventSettings($dateTypes);

    $entity->set([
        'saveToGoogleCalendar' => true,
        'googleCalendarDateSourceList' => $dateTypes,
        'googleCalendarEventSettings' => $settings,
    ]);
    $em->saveEntity($entity);

    try {
        $eventPusher->pushIfRequested($entity, $admin);
    } catch (Throwable $e) {
        echo "  [WARN] {$entityType} push — {$e->getMessage()}\n";
    }

    $linkStmt = $pdo->prepare(
        'SELECT source_date_type, google_event_id FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND user_id = ? AND deleted = 0'
    );
    $linkStmt->execute([$entityType, $entity->getId(), $adminId]);
    $links = $linkStmt->fetchAll(PDO::FETCH_ASSOC);

    $nameField = GcalTestFixtures::nameField($entityType);
    $displayName = (string) ($entity->get($nameField) ?? $entity->get('name') ?? '');

    $created[] = [
        'entityType' => $entityType,
        'id' => $entity->getId(),
        'name' => $displayName,
        'dates' => implode(', ', $dateTypes),
        'links' => count($links),
        'slot' => $slot['date'],
    ];

    echo "  [OK] {$entityType} id={$entity->getId()} name={$displayName} links=" . count($links) . "\n";
}

echo "\n── Summary (records KEPT for manual QA) ──\n";
echo str_pad('Entity', 20) . str_pad('Date', 12) . str_pad('Links', 6) . "Name / ID\n";
echo str_repeat('-', 90) . "\n";

foreach ($created as $row) {
    echo str_pad($row['entityType'], 20)
        . str_pad($row['slot'], 12)
        . str_pad((string) $row['links'], 6)
        . $row['name'] . ' / ' . $row['id'] . "\n";
}

echo "\nManual check: Google Calendar " . DATE_FROM . "–" . DATE_TO . " (admin TZ).\n";
echo "Cleanup later: ddev exec php bin/cleanup-gcal-e2e.php " . GcalTestFixtures::TEST_PREFIX . "\n";
echo "\n=== " . ($fail === 0 ? 'SEED COMPLETE' : "{$fail} CREATE FAILURES") . " ===\n";

exit($fail === 0 ? 0 : 1);
