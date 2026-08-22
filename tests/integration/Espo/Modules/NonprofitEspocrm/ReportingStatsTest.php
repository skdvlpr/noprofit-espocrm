<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\InjectableFactory;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\AssociationMealCountStatsProvider;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\InterventionStatsProvider;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\MealCountStatsProvider;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\PrimaNotaStatsProvider;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Reporting stats providers (converted from bin/smoke-rendicontazione.php).
 */
class ReportingStatsTest extends SafehouseBaseTestCase
{
    public function testMealCountStatsProviderSummaryAndTotals(): void
    {
        $em = $this->getEntityManager();
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $provider = $factory->create(MealCountStatsProvider::class);

        $today = date('Y-m-d');
        $meal = $em->getNewEntity('MealCount');
        $meal->set([
            'name' => 'PHPUnit-Meal-' . bin2hex(random_bytes(2)),
            'date' => $today,
            'adults' => 4,
            'minors' => 2,
        ]);
        $em->saveEntity($meal);

        $summary = $provider->getSummary();
        $this->assertNotEmpty($summary->metricList ?? []);
        $this->assertObjectHasProperty('today', $summary);
        $this->assertObjectHasProperty('month', $summary);
        $this->assertObjectHasProperty('year', $summary);

        $totals = $provider->getTotals(null, ['id' => $meal->getId()]);
        $this->assertArrayHasKey('adults', $totals);
        $this->assertSame(4, (int) $totals['adults']);
    }

    public function testPrimaNotaStatsProviderSummaryKeys(): void
    {
        $em = $this->getEntityManager();
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $provider = $factory->create(PrimaNotaStatsProvider::class);

        $pn = $em->getNewEntity('PrimaNota');
        $pn->set([
            'description' => 'PHPUnit income',
            'entryType' => 'Income',
            'amountGross' => 50.0,
            'amountGrossCurrency' => 'EUR',
            'commissionAmount' => 0.0,
            'amount' => 50.0,
            'paymentStatus' => 'Inviato',
            'transactionDate' => date('Y-m-d'),
        ]);
        $em->saveEntity($pn);

        $summary = $provider->getSummary();
        $this->assertObjectHasProperty('today', $summary);
        $this->assertObjectHasProperty('month', $summary);
        $this->assertObjectHasProperty('bankBalance', $summary);
        $this->assertObjectHasProperty('amountIn', $summary->today);
        $this->assertObjectHasProperty('managementBalance', $summary->today);

        $totals = $provider->getTotals();
        $this->assertArrayHasKey('amountIn', $totals);
        $this->assertArrayHasKey('amountOut', $totals);
        $this->assertArrayHasKey('managementBalance', $totals);
    }

    public function testInterventionStatsProviderTotals(): void
    {
        $em = $this->getEntityManager();
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $provider = $factory->create(InterventionStatsProvider::class);

        $contact = $em->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'PHPUnit',
            'lastName' => 'InterventionStats',
        ]);
        $em->saveEntity($contact);

        $intervention = $em->getNewEntity('Intervention');
        $intervention->set([
            'description' => 'PHPUnit intervention',
            'department' => 'StreetUnit',
            'interventionDate' => date('Y-m-d'),
            'interventionCount' => 3,
            'parentType' => 'Contact',
            'parentId' => $contact->getId(),
        ]);
        $em->saveEntity($intervention);

        $totals = $provider->getTotals(null);
        $this->assertArrayHasKey('interventionCount', $totals);
        $this->assertArrayHasKey('recordCount', $totals);
        $this->assertGreaterThanOrEqual(1, (int) $totals['recordCount']);
    }

    public function testAssociationMealCountStatsProviderSummary(): void
    {
        $em = $this->getEntityManager();
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $provider = $factory->create(AssociationMealCountStatsProvider::class);

        $row = $em->getNewEntity('AssociationMealCount');
        $row->set([
            'name' => 'PHPUnit-Assoc-' . bin2hex(random_bytes(2)),
            'date' => date('Y-m-d'),
            'portionCount' => 12,
        ]);
        $em->saveEntity($row);

        $summary = $provider->getSummary();
        $this->assertNotEmpty($summary->metricList ?? []);
        $this->assertObjectHasProperty('today', $summary);
        $this->assertObjectHasProperty('month', $summary);
        $this->assertObjectHasProperty('year', $summary);

        $totals = $provider->getTotals(null, ['id' => $row->getId()]);
        $this->assertArrayHasKey('portionCount', $totals);
        $this->assertSame(12, (int) $totals['portionCount']);
    }
}
