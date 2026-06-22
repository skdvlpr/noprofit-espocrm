<?php

namespace Espo\Modules\SafehouseAuroraThemes;

use Espo\Core\Application;
use Espo\Modules\SafehouseAuroraThemes\Tools\Installer;

/**
 * In-tree post-install hook (dev / direct installs). Mirrors the ZIP package
 * scripts/AfterInstall.php so both flows delegate to the same provisioner.
 */
class AfterInstall
{
    public function run(Application $app): void
    {
        (new Installer())->runPostInstall($app->getContainer());
    }
}
