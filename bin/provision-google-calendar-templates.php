<?php

/**
 * Backfill CalendarTemplate rows and integration template metadata for all active
 * CalendarDateSource targets. Safe to re-run (idempotent).
 *
 * Usage: ddev exec php bin/provision-google-calendar-templates.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceEntityTypesReader;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DefaultCalendarTemplateProvisioner;
use Espo\ORM\EntityManager;

$app = new Application();
$container = $app->getContainer();
$entityManager = $container->getByClass(EntityManager::class);

$provisioner = $container->getByClass(InjectableFactory::class)
    ->create(DefaultCalendarTemplateProvisioner::class);

$entityTypes = (new DateSourceEntityTypesReader())->readActiveTargetEntityTypes();
$created = 0;

foreach ($entityTypes as $entityType) {
    $before = $entityManager
        ->getRDBRepository('CalendarTemplate')
        ->where(['targetEntityType' => $entityType, 'deleted' => false])
        ->count();

    $provisioner->ensureForEntityType($entityType);

    $after = $entityManager
        ->getRDBRepository('CalendarTemplate')
        ->where(['targetEntityType' => $entityType, 'deleted' => false])
        ->count();

    if ($after > $before) {
        $created++;
        echo "Created default CalendarTemplate for {$entityType}\n";
    }
}

(new DateSourceEntityTypesReader())->writeCacheFromDatabase();
$container->getByClass(DataManager::class)->rebuild();

echo 'Done. Active date-source entity types: ' . count($entityTypes) . ", new templates: {$created}\n";
echo "Rebuild complete — refresh Admin → Integrations → Google Calendar & Drive.\n";
