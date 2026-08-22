<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Safehouse domain formulas and entities (converted from bin/smoke-safehouse.php).
 * Runs on isolated build/test instance with db_test (canonical Espo integration).
 */
class SafehouseDomainTest extends SafehouseBaseTestCase
{
    public function testContactPersonnelStatusAndMealCountFormulas(): void
    {
        $em = $this->getEntityManager();

        $ve = $em->getNewEntity('Contact');
        $ve->set([
            'firstName' => 'Test',
            'lastName' => 'Active',
            'contactType' => 'Employee',
            'contractType' => 'Permanent',
            'startDate' => date('Y-m-d', strtotime('-30 days')),
            'endDate' => date('Y-m-d', strtotime('+30 days')),
            'weeklyHours' => 40,
        ]);
        $em->saveEntity($ve);

        $this->assertSame('Active', $ve->get('personnelStatus'));
        $this->assertSame((float) round(40 * 4.33, 1), (float) $ve->get('monthlyHours'));

        $ve2 = $em->getNewEntity('Contact');
        $ve2->set([
            'firstName' => 'Test',
            'lastName' => 'Expired',
            'contactType' => 'Volunteer',
            'startDate' => date('Y-m-d', strtotime('-60 days')),
            'endDate' => date('Y-m-d', strtotime('-1 days')),
            'weeklyHours' => 8,
        ]);
        $em->saveEntity($ve2);

        $this->assertSame('Inactive', $ve2->get('personnelStatus'));

        $mb = $em->getNewEntity('Contact');
        $mb->set([
            'firstName' => 'Test',
            'lastName' => 'Member',
            'contactType' => 'MemberContact',
            'joinDate' => date('Y-m-d', strtotime('-365 days')),
        ]);
        $em->saveEntity($mb);

        $this->assertSame('Active', $mb->get('personnelStatus'));

        $mc = $em->getNewEntity('MealCount');
        $mc->set(['date' => date('Y-m-d'), 'adults' => 25, 'minors' => 10]);
        $em->saveEntity($mc);

        $this->assertSame(35, (int) $mc->get('totalMeals'));
        $this->assertSame(35 * 1.5, (float) $mc->get('foodCost'));
    }
}
