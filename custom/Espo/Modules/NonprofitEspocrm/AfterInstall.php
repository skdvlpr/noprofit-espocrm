<?php

namespace Espo\Modules\NonprofitEspocrm;

use Espo\Core\Application;
use Espo\Modules\NonprofitEspocrm\Tools\Installer;

/**
 * In-tree post-install (dev / pre-installed tree). Same orchestration as
 * `scripts/AfterInstall.php` for the unified suite ZIP.
 */
class AfterInstall
{
    public function run(Application $app): void
    {
        $container = $app->getContainer();
        (new Installer())->runPostInstall($container);

        $siblingInstallers = [
            \Espo\Modules\GoogleIntegration\Tools\Installer::class,
            \Espo\Modules\WorkflowEngine\Tools\Installer::class,
            \Espo\Modules\BugTracker\Tools\Installer::class,
            \Espo\Modules\SafehouseAuroraThemes\Tools\Installer::class,
        ];

        foreach ($siblingInstallers as $className) {
            if (!class_exists($className)) {
                continue;
            }

            (new $className())->runPostInstall($container);
        }
    }
}
