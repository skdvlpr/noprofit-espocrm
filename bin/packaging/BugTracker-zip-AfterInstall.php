<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Modules\BugTracker\Tools\Installer;

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
