<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine;

use Espo\Core\Application;
use Espo\Modules\WorkflowEngine\Tools\Installer;

class AfterInstall
{
    public function run(Application $app): void
    {
        (new Installer())->runPostInstall($app->getContainer());
    }
}
