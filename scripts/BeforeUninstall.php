<?php

use Espo\Core\Container;

class BeforeUninstall
{
    public function run(Container $container, array $params): void
    {
        // Navigation restore logic belongs with the task that changes navigation.
    }
}
