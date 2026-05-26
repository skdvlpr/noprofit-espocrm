<?php

/**
 * Registers three SafehouseCrm smoke entities for Google Calendar E2E and provisions
 * CalendarDateSource + DB columns + layouts.
 *
 * Usage:
 *   ddev exec php rebuild.php
 *   ddev exec php bin/provision-gcal-smoke-entities.php
 *   ddev exec php clear_cache.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarLayoutProvisioner;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarSchemaProvisioner;
use Espo\ORM\EntityManager;

/** @var list<array<string, mixed>> */
const GCAL_SMOKE_DATE_SOURCES = [
    [
        'name' => 'GCalSmokeAllDay — event date',
        'targetEntityType' => 'GCalSmokeAllDay',
        'dateField' => 'eventDate',
        'endDateField' => null,
        'sourceDateType' => 'main',
        'label' => 'Event date',
        'allDay' => true,
        'sortOrder' => 9001,
    ],
    [
        'name' => 'GCalSmokeDateTime — interval',
        'targetEntityType' => 'GCalSmokeDateTime',
        'dateField' => 'dateStart',
        'endDateField' => 'dateEnd',
        'sourceDateType' => 'main',
        'label' => 'Interval',
        'allDay' => false,
        'sortOrder' => 9002,
    ],
    [
        'name' => 'GCalSmokeTwinDate — primary',
        'targetEntityType' => 'GCalSmokeTwinDate',
        'dateField' => 'primaryDate',
        'endDateField' => null,
        'sourceDateType' => 'primaryDate',
        'label' => 'Primary date',
        'allDay' => true,
        'sortOrder' => 9003,
    ],
    [
        'name' => 'GCalSmokeTwinDate — review',
        'targetEntityType' => 'GCalSmokeTwinDate',
        'dateField' => 'reviewDate',
        'endDateField' => null,
        'sourceDateType' => 'reviewDate',
        'label' => 'Review date',
        'allDay' => true,
        'sortOrder' => 9004,
    ],
];

/** @var list<string> */
const GCAL_SMOKE_ENTITY_TYPES = [
    'GCalSmokeAllDay',
    'GCalSmokeDateTime',
    'GCalSmokeTwinDate',
];

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);

echo "Step 1: rebuild metadata (new entity tables)\n";
$container->getByClass(DataManager::class)->rebuild();

$repo = $em->getRDBRepository('CalendarDateSource');

echo "Step 2: CalendarDateSource rows\n";

foreach (GCAL_SMOKE_DATE_SOURCES as $source) {
    $target = (string) $source['targetEntityType'];
    $key = (string) $source['sourceDateType'];

    $existing = $repo
        ->where([
            'targetEntityType' => $target,
            'sourceDateType' => $key,
            'deleted' => false,
        ])
        ->findOne();

    if ($existing !== null) {
        echo "  exists: {$target}/{$key}\n";
        continue;
    }

    $em->saveEntity($em->createEntity('CalendarDateSource', array_merge([
        'isActive' => true,
        'calendarViewEnabled' => true,
    ], $source)));
    echo "  created: {$target}/{$key}\n";
}

$schemaProvisioner = $injectableFactory->create(GoogleCalendarSchemaProvisioner::class);
$layoutProvisioner = $injectableFactory->create(GoogleCalendarLayoutProvisioner::class);

echo "Step 3: clear cache (load CalendarDateSource into metadata)\n";
passthru('php clear_cache.php', $clearExit);

if ($clearExit !== 0) {
    fwrite(STDERR, "WARN: clear_cache.php exit code {$clearExit}\n");
}

$container->getByClass(\Espo\Core\Utils\Metadata::class)->init(true);

echo "Step 4: Google schema + layouts\n";

foreach (GCAL_SMOKE_ENTITY_TYPES as $entityType) {
    $schemaProvisioner->provisionEntityType($entityType);
    $layoutProvisioner->provisionEntityType($entityType);
    echo "  provisioned: {$entityType}\n";
}

echo "Step 5: clear cache\n";
passthru('php clear_cache.php', $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "WARN: clear_cache.php exit code {$exitCode}\n");
}

echo "Step 6: refresh role ACL for smoke API user (Admin role)\n";
passthru('php bin/setup-roles.php', $rolesExit);

if ($rolesExit !== 0) {
    fwrite(STDERR, "WARN: setup-roles.php exit code {$rolesExit}\n");
}

echo "Done. Run: ddev exec php bin/test-google-calendar-smoke-entities.php\n";
