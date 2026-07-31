<?php

namespace Espo\Modules\VolunteerActivityDispatch;

use Espo\Core\Application;
use Espo\Modules\VolunteerActivityDispatch\Tools\Installer;

class AfterInstall
{
    public function run(Application $app): void
    {
        (new Installer())->runPostInstall($app->getContainer());
    }
}
