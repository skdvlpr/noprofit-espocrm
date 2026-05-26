<?php

declare(strict_types=1);

/**
 * Rebuild DB columns for Google Calendar fields on one or all calendar-capable entities.
 *
 * Usage:
 *   ddev exec php bin/rebuild-google-calendar-entity-schema.php Account
 *   ddev exec php bin/rebuild-google-calendar-entity-schema.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CapableEntityTypeResolver;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarSchemaProvisioner;

$entityType = $argv[1] ?? null;

$app = new Application();
$app->setupSystemUser();

/** @var InjectableFactory $injectableFactory */
$injectableFactory = $app->getContainer()->getByClass(InjectableFactory::class);
$schemaProvisioner = $injectableFactory->create(GoogleCalendarSchemaProvisioner::class);
$resolver = $injectableFactory->create(CapableEntityTypeResolver::class);

$types = is_string($entityType) && $entityType !== ''
    ? [$entityType]
    : $resolver->getProvisionableEntityTypes();

foreach ($types as $type) {
    echo "Rebuilding Google Calendar schema for {$type}...\n";
    $schemaProvisioner->provisionEntityType($type);
}

echo "Done.\n";
