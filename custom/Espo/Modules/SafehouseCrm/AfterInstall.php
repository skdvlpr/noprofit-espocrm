<?php

namespace Espo\Modules\SafehouseCrm;

use Espo\Core\Application;
use Espo\Modules\SafehouseCrm\Tools\Installer;

/**
 * Entrypoint invoked when the module ships pre-installed (dev workflows or
 * direct in-tree installs). Delegates to {@see Installer} which holds the
 * single source of truth for post-install provisioning. The ZIP-install
 * entrypoint at `scripts/AfterInstall.php` delegates to the same Installer.
 */
class AfterInstall
{
    public function run(Application $app): void
    {
        $container = $app->getContainer();
        $installer = new Installer();
        $installer->runPostInstall($container);
    }
}
