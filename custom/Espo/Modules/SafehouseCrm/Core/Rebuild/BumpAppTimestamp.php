<?php

namespace Espo\Modules\SafehouseCrm\Core\Rebuild;

use Espo\Core\DataManager;
use Espo\Core\Rebuild\RebuildAction;

/**
 * Bumps config appTimestamp on every rebuild so browser cache bust (?r=) picks up
 * custom JS/TPL/CSS without disabling nginx expires or developer mode.
 *
 * @noinspection PhpUnused
 */
class BumpAppTimestamp implements RebuildAction
{
    public function __construct(
        private DataManager $dataManager
    ) {}

    public function process(): void
    {
        $this->dataManager->updateAppTimestamp();
    }
}
