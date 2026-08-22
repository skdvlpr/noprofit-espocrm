<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\NonprofitEspocrm\Tools\Calendar\SafehouseCalendarDateSourceDefaults;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * CalendarDateSource protection hooks for Safehouse CRM calendar seeds.
 */
class CalendarCdsProtectionTest extends SafehouseBaseTestCase
{
    public function testBeforeRemoveBlocksDeletingSafehouseSeed(): void
    {
        $em = $this->getEntityManager();
        $seed = SafehouseCalendarDateSourceDefaults::sources()[0];

        $cds = $em->getRDBRepository('CalendarDateSource')->where([
            'targetEntityType' => $seed['targetEntityType'],
            'sourceDateType' => $seed['sourceDateType'],
        ])->findOne();

        $this->assertNotNull($cds, 'Safehouse CalendarDateSource seed must exist after install.');

        try {
            $em->removeEntity($cds);
            $this->fail('Expected Forbidden when removing Safehouse CalendarDateSource seed.');
        } catch (Forbidden $e) {
            $this->assertStringContainsString('required for the CRM calendar', $e->getMessage());
        }
    }

    public function testBeforeSaveProtectCrmCalendarSeedsKeepsSlotSeedActive(): void
    {
        $em = $this->getEntityManager();

        $cds = $em->getRDBRepository('CalendarDateSource')->where([
            'targetEntityType' => 'ActivityOfferSlot',
            'sourceDateType' => 'main',
        ])->findOne();

        $this->assertNotNull($cds, 'ActivityOfferSlot CDS seed must exist after shift planning provision.');

        $cds->set([
            'isActive' => false,
            'calendarViewEnabled' => true,
        ]);
        $em->saveEntity($cds);

        $reloaded = $em->getEntityById('CalendarDateSource', $cds->getId());
        $this->assertTrue((bool) $reloaded->get('isActive'));
        $this->assertFalse((bool) $reloaded->get('calendarViewEnabled'));
    }
}
