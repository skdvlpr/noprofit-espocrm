<?php

/**
 * E2E Google Calendar checks for production CRM entities (Meeting, Call, Member, …).
 * Creates realistic T- prefixed records with explicit date source list, verifies push
 * idempotency + REST; cleans up CRM + Google.
 *
 * GCalSmoke* custom entities: bin/test-google-calendar-smoke-entities.php
 *
 * Usage:
 *   ddev exec php bin/test-google-calendar-all-entities.php
 */

declare(strict_types=1);

require __DIR__ . '/lib/GcalTestFixtures.php';

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventRemover;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\Layout\LayoutProvider;
use GuzzleHttp\Client;

$app = new Application();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Config $config */
$config = $container->getByClass(Config::class);
/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);
/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);

$metadata->init(true);

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $m = $pass ? 'PASS' : 'FAIL';
    echo "  [$m] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$countGooglePanels = static function (mixed $layout): int {
    if (!is_array($layout)) {
        return 0;
    }

    $n = 0;

    foreach ($layout as $panel) {
        if (is_object($panel)) {
            $panel = json_decode(json_encode($panel), true);
        }

        if (is_array($panel) && ($panel['name'] ?? null) === 'GoogleCalendar') {
            $n++;
        }
    }

    return $n;
};

$suffix = GcalTestFixtures::makeSuffix();
$tag = GcalTestFixtures::TEST_PREFIX . $suffix;
$testIds = [];
$admin = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'admin', 'deleted' => false])
    ->findOne();

if ($admin === null) {
    fwrite(STDERR, "FAIL: admin user not found.\n");
    exit(1);
}

/** @var ApplicationUser $applicationUser */
$applicationUser = $container->getByClass(ApplicationUser::class);
$applicationUser->setUser($admin);

$dateSourceProvider = $injectableFactory->create(DateSourceProvider::class);
$eventPusher = $injectableFactory->create(EventPusher::class);
$eventRemover = $injectableFactory->create(EventRemover::class);
$layoutProvider = $injectableFactory->create(LayoutProvider::class);

/** @var array<string, list<array{sourceDateType: string, dateField: string, endDateField?: mixed, allDay?: bool}>> $sourcesByEntity */
$sourcesByEntity = [];

foreach ($em->getRDBRepository('CalendarDateSource')
    ->where(['isActive' => true, 'deleted' => false])
    ->order('sortOrder')
    ->find() as $row) {
    $type = (string) $row->get('targetEntityType');

    if ($type === '') {
        continue;
    }

    $sourcesByEntity[$type][] = [
        'sourceDateType' => (string) ($row->get('sourceDateType') ?? 'main'),
        'dateField' => (string) ($row->get('dateField') ?? ''),
        'endDateField' => $row->get('endDateField'),
        'allDay' => (bool) $row->get('allDay'),
    ];
}

if (!empty($_SERVER['GCAL_SMOKE_TEST_ONLY'])) {
    $sourcesByEntity = GcalTestFixtures::filterSourcesByScope($sourcesByEntity, 'smoke');
} else {
    $sourcesByEntity = GcalTestFixtures::filterSourcesByScope($sourcesByEntity, 'production');
}

ksort($sourcesByEntity);

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
$apiUser = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'smoke_api_catalog', 'deleted' => false])
    ->findOne();

$http = null;

if ($apiUser !== null && is_string($apiUser->get('apiKey')) && $apiUser->get('apiKey') !== '') {
    $http = new Client([
        'base_uri' => $siteUrl . '/',
        'verify' => false,
        'timeout' => 45,
        'http_errors' => false,
        'headers' => [
            'X-Api-Key' => (string) $apiUser->get('apiKey'),
            'Accept' => 'application/json',
        ],
    ]);
}

$baseDate = new DateTimeImmutable('+2 days', new DateTimeZone('UTC'));
$eventDate = $baseDate->format('Y-m-d');
$eventDateTime = $baseDate->modify('+10 hours')->format('Y-m-d H:i:s');
$eventDateTimeEnd = $baseDate->modify('+11 hours')->format('Y-m-d H:i:s');

$title = !empty($_SERVER['GCAL_SMOKE_TEST_ONLY'])
    ? 'Google Calendar smoke entities test (GCalSmoke*)'
    : 'Google Calendar all-entities test (production)';

echo "=== {$title} ===\n";
echo "Tag: {$tag} | event date: {$eventDate} | admin: {$admin->getId()}\n\n";

foreach ($sourcesByEntity as $entityType => $sources) {
    echo "[{$entityType}]\n";

    $sourceDateTypes = array_values(array_unique(array_map(
        static fn (array $s): string => $s['sourceDateType'],
        $sources
    )));

    $layoutJson = $layoutProvider->get($entityType, 'detail');
    $layout = $layoutJson !== null ? Json::decode($layoutJson) : [];
    $ok(
        "{$entityType} layout detail has one GoogleCalendar panel",
        $countGooglePanels($layout) === 1,
        'panels=' . $countGooglePanels($layout)
    );

    $dayOffset = GcalTestFixtures::dayOffset($entityType);
    $entityBaseDate = $baseDate->modify("+{$dayOffset} days");

    $record = GcalTestFixtures::createRecord($em, $entityType, [
        'suffix' => $suffix,
        'baseDate' => $entityBaseDate,
        'adminId' => $admin->getId(),
    ], $sources);

    if ($record === null) {
        $ok("{$entityType} create T- record", false, 'skipped (could not create)');
        echo "\n";
        continue;
    }

    $testIds[$entityType] = $record->getId();
    $ok("{$entityType} create T- record", true, 'id=' . $record->getId());

    $record->set([
        'saveToGoogleCalendar' => true,
        'googleCalendarDateSourceList' => $sourceDateTypes,
        'googleCalendarEventSettings' => GcalTestFixtures::buildEventSettings($sourceDateTypes),
    ]);
    GcalTestFixtures::fillDateFields($record, $sources, $eventDate, $eventDateTime, $eventDateTimeEnd);

    try {
        $em->saveEntity($record);
        $eventPusher->pushIfRequested($record, $admin);
        $eventPusher->pushIfRequested($record, $admin);
    } catch (Throwable $e) {
        $ok("{$entityType} double push (EventPusher as admin)", false, $e->getMessage());
        echo "\n";
        continue;
    }

    $ok("{$entityType} double push (EventPusher as admin)", true);

    $record = $em->getEntityById($entityType, $record->getId()) ?? $record;
    $linkCountAfter = countLinks($em, $entityType, $record->getId(), $admin->getId());
    $expectedLinks = count($sourceDateTypes);
    $ok(
        "{$entityType} link count matches selected date sources",
        $linkCountAfter === $expectedLinks,
        "links={$linkCountAfter} expected={$expectedLinks}"
    );

    $ok(
        "{$entityType} created GoogleCalendarEventLink when push enabled",
        $linkCountAfter >= 1,
        "links={$linkCountAfter} (admin Google OAuth must be connected)"
    );

    $perSource = [];

    foreach (
        $em->getRDBRepository('GoogleCalendarEventLink')
            ->where([
                'sourceEntityType' => $entityType,
                'sourceEntityId' => $record->getId(),
                'userId' => $admin->getId(),
                'deleted' => false,
            ])
            ->find() as $link
    ) {
        $key = (string) ($link->get('sourceDateType') ?? '');
        $perSource[$key] = ($perSource[$key] ?? 0) + 1;
    }

    foreach ($sourceDateTypes as $sourceDateType) {
        $canonical = $dateSourceProvider->canonicalSourceDateType($entityType, $sourceDateType);
        $dupe = 0;

        foreach ($perSource as $storedKey => $cnt) {
            if ($dateSourceProvider->canonicalSourceDateType($entityType, $storedKey) === $canonical) {
                $dupe += $cnt;
            }
        }

        $ok(
            "{$entityType} at most one link per source {$sourceDateType}",
            $dupe <= 1,
            "canonical={$canonical} count={$dupe}"
        );
    }

    if ($http !== null) {
        $rGet = $http->get("api/v1/{$entityType}/{$record->getId()}", [
            'query' => ['select' => 'id,name,saveToGoogleCalendar,googleCalendarDateSourceList'],
        ]);
        $restCode = $rGet->getStatusCode();

        if ($restCode === 403 && ($entityType === 'Campaign' || str_starts_with($entityType, 'GCalSmoke'))) {
            $ok(
                "{$entityType} GET via REST (smoke_api_catalog)",
                true,
                'skipped: API user lacks read ACL (run bin/setup-roles.php for GCalSmoke*)'
            );
        } else {
            $ok(
                "{$entityType} GET via REST → 200",
                $restCode === 200,
                'code=' . $restCode . ' ' . $rGet->getHeaderLine('X-Status-Reason')
            );
        }
    }

    echo "\n";
}

echo "Strict selection (empty date list → no Google events)\n";

try {
    $strictEntity = GcalTestFixtures::createRecord($em, 'Meeting', [
        'suffix' => $suffix . 'strict',
        'baseDate' => $baseDate,
        'adminId' => $admin->getId(),
        'variant' => 'strict',
    ], $sourcesByEntity['Meeting'] ?? []);

    if ($strictEntity !== null) {
        $strictEntity->set([
            'saveToGoogleCalendar' => true,
            'googleCalendarDateSourceList' => [],
            'googleCalendarEventSettings' => [],
        ]);

        $getSelected = new ReflectionMethod($eventPusher, 'getSelectedDateSourceTypes');
        $getSelected->setAccessible(true);
        $selected = $getSelected->invoke($eventPusher, $strictEntity, $sourcesByEntity['Meeting'] ?? []);
        $ok('empty googleCalendarDateSourceList yields no selected types', $selected === [], 'got=' . implode(',', $selected));

        $buildEvents = new ReflectionMethod($eventPusher, 'buildCalendarDateSourceGoogleEvents');
        $buildEvents->setAccessible(true);
        $built = $buildEvents->invoke($eventPusher, $strictEntity, $sourcesByEntity['Meeting'] ?? []);
        $ok('empty date list builds zero Google events', count($built) === 0, 'count=' . count($built));

        $testIds['Meeting_strict'] = $strictEntity->getId();
    } else {
        $ok('strict selection Meeting create', false, 'could not create');
    }
} catch (Throwable $e) {
    $ok('strict selection smoke', false, $e->getMessage());
}

echo "\nCleanup (CRM + Google)\n";

$pdo = $em->getPDO();

foreach ($testIds as $entityType => $id) {
    if ($entityType === 'Meeting_strict') {
        $entityType = 'Meeting';
    }

    try {
        $entity = $em->getEntityById($entityType, $id);

        if ($entity === null) {
            $ok("{$entityType} cleanup record {$id}", true, 'already gone');
            continue;
        }

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
                    $em->removeEntity($linkEntity);
                }
            }
        }

        $em->removeEntity($entity);
        $ok("{$entityType} cleanup record {$id}", true);
    } catch (Throwable $e) {
        $ok("{$entityType} cleanup record {$id}", false, $e->getMessage());
    }
}

echo "\n=== " . ($fail === 0 ? 'ALL PASS' : "{$fail} FAILURE(S)") . " ===\n";
echo 'Cleanup prefix: ' . GcalTestFixtures::TEST_PREFIX . "\n";
exit($fail === 0 ? 0 : 1);

function countLinks(EntityManager $em, string $entityType, string $entityId, string $userId): int
{
    return $em->getRDBRepository('GoogleCalendarEventLink')
        ->where([
            'sourceEntityType' => $entityType,
            'sourceEntityId' => $entityId,
            'userId' => $userId,
            'deleted' => false,
        ])
        ->count();
}
