<?php

use Espo\Core\Container;
use Espo\Modules\NonprofitEspocrm\Tools\Installer;

/**
 * Extension-package post-install script for the unified suite ZIP.
 *
 * Espo Extension Manager runs `run(Container, params)` after copying files.
 * Orchestrates module Installers that remain separately extractable:
 *
 *   1. NonprofitEspocrm (navbar, Safehouse defaults, shift planning, …)
 *   2. GoogleIntegration (when present in the package)
 *   3. WorkflowEngine (when present)
 *   4. BugTracker (when present)
 *   5. SafehouseAuroraThemes (when present)
 *
 * Roles / teams are NOT auto-provisioned — Administration → Roles only.
 */
class AfterInstall
{
    /**
     * @param array<string, mixed> $params
     */
    public function run(Container $container, array $params): void
    {
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
