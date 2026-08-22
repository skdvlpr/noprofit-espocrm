<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Core\InjectableFactory;
use Espo\Modules\NonprofitEspocrm\Classes\FieldProcessing\Opportunity\FundraisingProgressLoader;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Opportunity fundraising progress loader (converted from bin/smoke-fundraising-progress.php).
 */
class FundraisingProgressTest extends SafehouseBaseTestCase
{
    public function testFundraisingProgressFromPrimaNotaIncome(): void
    {
        $em = $this->getEntityManager();
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $loader = $factory->create(FundraisingProgressLoader::class);
        $params = Params::create()->withSelect([
            'fundraisingCollectedAmount',
            'fundraisingTargetAmount',
            'fundraisingProgressPercent',
        ]);

        $opp = $em->getNewEntity('Opportunity');
        $opp->set([
            'name' => 'PHPUnit Fundraising Progress',
            'stage' => 'Fundraising',
            'amount' => 9520,
            'amountCurrency' => 'EUR',
        ]);
        $em->saveEntity($opp);

        $pn = $em->getNewEntity('PrimaNota');
        $pn->set([
            'description' => 'PHPUnit donation',
            'entryType' => 'Income',
            'amountGross' => 1255.22,
            'commissionAmount' => 0,
            'transactionDate' => date('Y-m-d'),
            'financingId' => $opp->getId(),
        ]);
        $em->saveEntity($pn);

        $loaded = $em->getEntityById('Opportunity', $opp->getId());
        $loader->process($loaded, $params);

        $this->assertEqualsWithDelta(1255.22, (float) $loaded->get('fundraisingCollectedAmount'), 0.01);
        $this->assertSame(13, (int) $loaded->get('fundraisingProgressPercent'));

        $pnExpense = $em->getNewEntity('PrimaNota');
        $pnExpense->set([
            'description' => 'PHPUnit expense',
            'entryType' => 'Expense',
            'amountGross' => 300,
            'commissionAmount' => 0,
            'transactionDate' => date('Y-m-d'),
            'financingId' => $opp->getId(),
        ]);
        $em->saveEntity($pnExpense);

        $loadedNet = $em->getEntityById('Opportunity', $opp->getId());
        $loader->process($loadedNet, $params);

        $this->assertEqualsWithDelta(955.22, (float) $loadedNet->get('fundraisingCollectedAmount'), 0.01);
    }
}
