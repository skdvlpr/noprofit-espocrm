<?php

/**
 * Remove CalendarDateSource / CalendarTemplate from navbar tabList (one-shot / idempotent).
 *
 * Usage: ddev exec php bin/prune-google-calendar-config-tabs.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\GoogleIntegration\Tools\Installer;

$app = new Application();
$container = $app->getContainer();

$container->getByClass(InjectableFactory::class)
    ->create(Installer::class)
    ->pruneGoogleCalendarConfigFromNavigation($container);

echo "Removed CalendarDateSource and CalendarTemplate from tabList / quickCreateList.\n";
echo "Run: ddev exec php clear_cache.php && ddev exec php rebuild.php\n";
