<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Modules\GoogleIntegration\Tools\Installer;

/**
 * Copied to `scripts/AfterInstall.php` inside the standalone GoogleIntegration ZIP
 * (see {@see bin/build-google-integration.sh}).
 */
class AfterInstall
{
    /**
     * @param array<string, mixed> $params
     */
    public function run(Container $container, array $params): void
    {
        (new Installer())->runPostInstall($container);
    }
}
