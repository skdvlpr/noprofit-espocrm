<?php

declare(strict_types=1);

namespace Espo\Modules\BugTracker;

use Espo\Core\Application;
use Espo\Modules\BugTracker\Tools\Installer;

class AfterInstall
{
    public function run(Application $app): void
    {
        (new Installer())->runPostInstall($app->getContainer());
    }
}
