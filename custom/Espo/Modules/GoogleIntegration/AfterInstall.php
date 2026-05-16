<?php

namespace Espo\Modules\GoogleIntegration;

use Espo\Core\Application;
use Espo\Modules\GoogleIntegration\Tools\Installer;

/**
 * In-tree / dev install entrypoint. Standalone ZIP installs use `scripts/AfterInstall.php`
 * inside the `google-integration` package (see `bin/build-google-integration.sh`).
 */
class AfterInstall
{
    public function run(Application $app): void
    {
        (new Installer())->runPostInstall($app->getContainer());
    }
}
