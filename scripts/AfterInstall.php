<?php

use Espo\Core\Container;
use Espo\Modules\NonprofitEspocrm\Tools\Installer;

/**
 * Extension-package post-install script.
 *
 * Espo Extension Manager runs this class's `run(Container, params)` method
 * after copying the package files into place. We delegate to
 * {@see Installer} which centralises the post-install actions:
 *
 *   - ensure Safehouse custom entities are visible in `tabList`;
 *   - hide `Case` from `tabList` and `quickCreateList`;
 *   - restore `Lead` and reorder domain entities into the top `$CRM` block;
 *   - place reporting entities under `$Rendicontazione`;
 *   - provision the canonical roles (Admin, Employee, Manager, Volunteer,
 *     Member) and the `Administration` team idempotently;
 *   - rebuild metadata so the changes are picked up immediately.
 *
 * The in-tree module class `Espo\Modules\NonprofitEspocrm\AfterInstall`
 * delegates to the same Installer so both install flows stay in sync.
 */
class AfterInstall
{
    /**
     * @param array<string, mixed> $params Installer parameters from Espo's
     *                                     Extension Manager.
     */
    public function run(Container $container, array $params): void
    {
        $installer = new Installer();
        $installer->runPostInstall($container);
    }
}
