<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\InjectableFactory;
use Espo\Modules\NonprofitEspocrm\Core\Rebuild\DropRetiredPartyTables;
use Espo\Modules\NonprofitEspocrm\Core\Rebuild\ProvisionShiftPlanning;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Rebuild action registration + constructibility (no process() — avoids data mutations).
 */
class RebuildActionsTest extends SafehouseBaseTestCase
{
    public function testRebuildActionsRegisteredInMetadata(): void
    {
        $actionList = $this->getMetadata()->get(['app', 'rebuild', 'actionClassNameList']) ?? [];

        $this->assertContains(DropRetiredPartyTables::class, $actionList);
        $this->assertContains(ProvisionShiftPlanning::class, $actionList);
    }

    public function testRebuildActionClassesConstructViaInjectableFactory(): void
    {
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);

        $drop = $factory->create(DropRetiredPartyTables::class);
        $provision = $factory->create(ProvisionShiftPlanning::class);

        $this->assertInstanceOf(DropRetiredPartyTables::class, $drop);
        $this->assertInstanceOf(ProvisionShiftPlanning::class, $provision);
    }
}
