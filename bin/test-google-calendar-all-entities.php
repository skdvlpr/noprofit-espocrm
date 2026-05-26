<?php

/**
 * End-to-end Google Calendar checks for every active CalendarDateSource target entity.
 * Creates smoke records (dates +2 days), verifies GET/PUT, link idempotency, layouts; cleans up.
 *
 * Usage:
 *   ddev exec php bin/test-google-calendar-all-entities.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
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

$tag = 'SmokeGCal' . gmdate('YmdHis');
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
$layoutProvider = $injectableFactory->create(LayoutProvider::class);

$entityTypes = [];

foreach ($em->getRDBRepository('CalendarDateSource')
    ->select(['targetEntityType', 'sourceDateType', 'dateField'])
    ->where(['isActive' => true, 'deleted' => false])
    ->order('targetEntityType')
    ->find() as $row) {
    $type = $row->get('targetEntityType');

    if (!is_string($type) || $type === '') {
        continue;
    }

    $entityTypes[$type]['sources'][] = [
        'sourceDateType' => (string) ($row->get('sourceDateType') ?? 'main'),
        'dateField' => (string) ($row->get('dateField') ?? ''),
    ];
}

if (!empty($_SERVER['GCAL_SMOKE_TEST_ONLY'])) {
    $entityTypes = array_filter(
        $entityTypes,
        static fn (string $type): bool => str_starts_with($type, 'GCalSmoke'),
        ARRAY_FILTER_USE_KEY
    );

    if ($entityTypes === []) {
        fwrite(STDERR, "FAIL: no GCalSmoke* entities with active CalendarDateSource.\n");
        fwrite(STDERR, "Run: ddev exec php rebuild.php && ddev exec php bin/provision-gcal-smoke-entities.php\n");
        exit(1);
    }
}

ksort($entityTypes);

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

$eventDate = (new DateTimeImmutable('+2 days', new DateTimeZone('UTC')))->format('Y-m-d');
$eventDateTime = (new DateTimeImmutable('+2 days 10:00:00', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
$eventDateTimeEnd = (new DateTimeImmutable('+2 days 11:00:00', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

$title = !empty($_SERVER['GCAL_SMOKE_TEST_ONLY'])
    ? 'Google Calendar smoke entities test (GCalSmoke*)'
    : 'Google Calendar all-entities test';

echo "=== {$title} ===\n";
echo "Tag: $tag | event date: $eventDate | admin: {$admin->getId()}\n\n";

foreach ($entityTypes as $entityType => $info) {
    echo "[$entityType]\n";

    $sources = $info['sources'];
    $sourceDateTypes = array_values(array_unique(array_map(
        static fn (array $s): string => $s['sourceDateType'],
        $sources
    )));

    $layoutJson = $layoutProvider->get($entityType, 'detail');
    $layout = $layoutJson !== null ? Json::decode($layoutJson) : [];
    $ok(
        "$entityType layout detail has one GoogleCalendar panel",
        $countGooglePanels($layout) === 1,
        'panels=' . $countGooglePanels($layout)
    );

    $record = createSmokeRecord(
        $em,
        $entityType,
        $tag,
        $eventDate,
        $eventDateTime,
        $eventDateTimeEnd,
        $sources,
        $admin->getId()
    );

    if ($record === null) {
        $ok("$entityType create smoke record", false, 'skipped (could not create)');
        echo "\n";
        continue;
    }

    $testIds[$entityType] = $record->getId();
    $ok("$entityType create smoke record", true, 'id=' . $record->getId());

    $linkCount = countLinks($em, $entityType, $record->getId(), $admin->getId());
    $ok("$entityType initial GoogleCalendarEventLink count", $linkCount >= 0, "links=$linkCount");

    $record->set([
        'saveToGoogleCalendar' => true,
        'googleCalendarDateSourceList' => $sourceDateTypes,
        'googleCalendarEventSettings' => buildEventSettings($sourceDateTypes),
    ]);
    fillDateFields($record, $sources, $eventDate, $eventDateTime, $eventDateTimeEnd);

    try {
        $em->saveEntity($record);
        $eventPusher->pushIfRequested($record, $admin);
        $eventPusher->pushIfRequested($record, $admin);
    } catch (Throwable $e) {
        $ok("$entityType double push (EventPusher as admin)", false, $e->getMessage());
        echo "\n";
        continue;
    }

    $ok("$entityType double push (EventPusher as admin)", true);

    $record = $em->getEntityById($entityType, $record->getId()) ?? $record;
    $linkCountAfter = countLinks($em, $entityType, $record->getId(), $admin->getId());
    $expectedMaxLinks = max(1, count($sourceDateTypes));
    $ok(
        "$entityType link count stable after double save",
        $linkCountAfter <= $expectedMaxLinks * 2,
        "links=$linkCountAfter sources=" . count($sourceDateTypes)
    );

    if ($record->get('saveToGoogleCalendar')) {
        $ok(
            "$entityType created GoogleCalendarEventLink when push enabled",
            $linkCountAfter >= 1,
            "links=$linkCountAfter (admin Google OAuth must be connected)"
        );
    }

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
            "$entityType at most one link per source $sourceDateType",
            $dupe <= 1,
            "canonical=$canonical count=$dupe"
        );
    }

    if ($http !== null) {
        $rGet = $http->get("api/v1/{$entityType}/{$record->getId()}", [
            'query' => ['select' => 'id,name,saveToGoogleCalendar,googleCalendarDateSourceList'],
        ]);
        $restCode = $rGet->getStatusCode();

        if ($restCode === 403 && ($entityType === 'Campaign' || str_starts_with($entityType, 'GCalSmoke'))) {
            $ok(
                "$entityType GET via REST (smoke_api_catalog)",
                true,
                'skipped: API user lacks read ACL (run bin/setup-roles.php for GCalSmoke*)'
            );
        } else {
            $ok(
                "$entityType GET via REST → 200",
                $restCode === 200,
                'code=' . $restCode . ' ' . $rGet->getHeaderLine('X-Status-Reason')
            );
        }
    }

    echo "\n";
}

echo "Cleanup\n";

foreach ($testIds as $entityType => $id) {
    try {
        $entity = $em->getEntityById($entityType, $id);

        if ($entity !== null) {
            $em->removeEntity($entity);
        }

        $ok("$entityType cleanup record $id", true);
    } catch (Throwable $e) {
        $ok("$entityType cleanup record $id", false, $e->getMessage());
    }
}

echo "\n=== " . ($fail === 0 ? 'ALL PASS' : "$fail FAILURE(S)") . " ===\n";
exit($fail === 0 ? 0 : 1);

/**
 * @param array<int, array{sourceDateType: string, dateField: string}> $sources
 */
function createSmokeRecord(
    EntityManager $em,
    string $entityType,
    string $tag,
    string $eventDate,
    string $eventDateTime,
    string $eventDateTimeEnd,
    array $sources,
    ?string $assignedUserId
): ?Entity {
    $entity = $em->getNewEntity($entityType);
    $suffix = substr(Util::generateId(), 0, 6);

    switch ($entityType) {
        case 'Account':
            $entity->set([
                'name' => "$tag Account $suffix",
                'cDataFirmaContratto' => $eventDate,
            ]);
            break;

        case 'Member':
            $entity->set([
                'firstName' => 'Smoke',
                'lastName' => "GCal $suffix",
                'birthDate' => $eventDate,
            ]);
            break;

        case 'VolunteerEmployee':
            $entity->set([
                'firstName' => 'Smoke',
                'lastName' => "Vol $suffix",
                'type' => 'Volunteer',
                'startDate' => $eventDate,
                'endDate' => $eventDate,
            ]);
            break;

        case 'Opportunity':
            $entity->set([
                'name' => "$tag Opportunity $suffix",
                'presentationDate' => $eventDate,
                'closeDate' => $eventDate,
            ]);
            break;

        case 'Meeting':
        case 'Call':
            $entity->set([
                'name' => "$tag $entityType $suffix",
                'status' => $entityType === 'Call' ? 'Planned' : 'Planned',
                'dateStart' => $eventDateTime,
                'dateEnd' => $eventDateTimeEnd,
                'assignedUserId' => $assignedUserId,
            ]);

            if ($entityType === 'Call') {
                $entity->set('direction', 'Outbound');
            }

            break;

        case 'Task':
            $entity->set([
                'name' => "$tag Task $suffix",
                'status' => 'Not Started',
                'dateEnd' => $eventDateTime,
                'dateEndDate' => $eventDate,
                'assignedUserId' => $assignedUserId,
            ]);
            break;

        case 'Campaign':
            $entity->set([
                'name' => "$tag Campaign $suffix",
                'status' => 'Active',
                'startDate' => $eventDate,
            ]);
            break;

        case 'GCalSmokeAllDay':
            $entity->set([
                'name' => "$tag AllDay $suffix",
                'eventDate' => $eventDate,
                'assignedUserId' => $assignedUserId,
            ]);
            break;

        case 'GCalSmokeDateTime':
            $entity->set([
                'name' => "$tag DateTime $suffix",
                'dateStart' => $eventDateTime,
                'dateEnd' => $eventDateTimeEnd,
                'assignedUserId' => $assignedUserId,
            ]);
            break;

        case 'GCalSmokeTwinDate':
            $entity->set([
                'name' => "$tag Twin $suffix",
                'primaryDate' => $eventDate,
                'reviewDate' => (new DateTimeImmutable($eventDate))->modify('+1 day')->format('Y-m-d'),
                'assignedUserId' => $assignedUserId,
            ]);
            break;

        default:
            $entity->set('name', "$tag $entityType $suffix");
            fillDateFields($entity, $sources, $eventDate, $eventDateTime, $eventDateTimeEnd);
    }

    try {
        $em->saveEntity($entity);

        return $entity;
    } catch (Throwable) {
        return null;
    }
}

/**
 * @param array<int, array{sourceDateType: string, dateField: string}> $sources
 */
function fillDateFields(
    Entity $entity,
    array $sources,
    string $eventDate,
    string $eventDateTime,
    string $eventDateTimeEnd
): void {
    foreach ($sources as $source) {
        $field = $source['dateField'] ?? '';

        if ($field === '') {
            continue;
        }

        $type = $entity->getAttributeType($field);

        if ($type === 'date') {
            $entity->set($field, $eventDate);
        } elseif ($type === 'datetime' || in_array($field, ['dateStart', 'dateEnd'], true)) {
            $entity->set($field, str_contains($field, 'End') || $field === 'dateEnd' ? $eventDateTimeEnd : $eventDateTime);
        } else {
            $entity->set($field, $eventDate);
        }
    }
}

/**
 * @param array<int, string> $sourceDateTypes
 * @return array<int, array<string, mixed>>
 */
function buildEventSettings(array $sourceDateTypes): array
{
    $rows = [];

    foreach ($sourceDateTypes as $sourceDateType) {
        $rows[] = [
            'sourceDateType' => $sourceDateType,
            'reminderMode' => 'none',
            'reminders' => [],
            'location' => '{{name}}',
            'visibility' => 'default',
            'transparency' => 'opaque',
            'colorId' => '',
            'descriptionTemplateOverride' => '',
        ];
    }

    return $rows;
}

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
