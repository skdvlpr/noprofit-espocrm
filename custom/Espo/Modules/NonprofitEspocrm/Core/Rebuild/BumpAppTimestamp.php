<?php

namespace Espo\Modules\NonprofitEspocrm\Core\Rebuild;

use Espo\Core\DataManager;
use Espo\Core\Rebuild\RebuildAction;

/**
 * Bumps client asset cache-bust timestamps on every rebuild:
 * - appTimestamp → cssList / AMD templates & JS (?r= in ClientManager)
 * - cacheTimestamp → theme stylesheet in main.html (?r= on #main-stylesheet)
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
        $this->dataManager->updateCacheTimestamp();
    }
}
