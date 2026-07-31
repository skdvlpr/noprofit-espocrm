<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;

class Installer
{
    public function runPostInstall(Container $container): void
    {
        $container->getByClass(DataManager::class)->rebuild();
    }
}
