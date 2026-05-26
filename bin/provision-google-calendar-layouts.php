<?php

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarLayoutProvisioner;

$app = new Application();
$app->setupSystemUser();

$app->getContainer()
    ->getByClass(InjectableFactory::class)
    ->create(GoogleCalendarLayoutProvisioner::class)
    ->provisionAll();

echo "Google Calendar layouts provisioned.\n";
