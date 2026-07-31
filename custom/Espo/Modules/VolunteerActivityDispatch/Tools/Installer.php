<?php

namespace Espo\Modules\VolunteerActivityDispatch\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;

/**
 * Lightweight post-install: rebuild metadata/cache so Task scheduler overlays load.
 */
class Installer
{
    public function runPostInstall(Container $container): void
    {
        /** @var DataManager $dataManager */
        $dataManager = $container->getByClass(DataManager::class);
        $dataManager->rebuild();
    }
}
