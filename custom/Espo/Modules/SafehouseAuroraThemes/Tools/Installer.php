<?php

namespace Espo\Modules\SafehouseAuroraThemes\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;

/**
 * Post-install provisioning for the Safehouse Aurora themes package.
 *
 * Themes are pure metadata + client assets, so the only required action is a
 * metadata rebuild so the theme list and cssList are picked up immediately.
 * The package deliberately does NOT change the active theme — users opt in via
 * Administration → User Interface. (SafehouseCrm's own Installer may set the
 * default theme when the CRM is installed.)
 */
class Installer
{
    public function runPostInstall(Container $container): void
    {
        $container->getByClass(DataManager::class)->rebuild();
    }
}
