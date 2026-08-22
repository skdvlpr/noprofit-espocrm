<?php

declare(strict_types=1);


require __DIR__ . '/lib/refuse-production.php';


include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Core\InjectableFactory;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\NonprofitEspocrm\Classes\FieldProcessing\Opportunity\FundraisingProgressLoader;

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->getByClass(EntityManager::class);
$factory = $app->getContainer()->getByClass(InjectableFactory::class);
$loader = $factory->create(FundraisingProgressLoader::class);

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    echo '  [' . ($pass ? 'PASS' : 'FAIL') . '] ' . $name . ($detail !== '' ? " — $detail" : '') . "\n";
};

$opp = $em->getNewEntity('Opportunity');
$opp->set([
    'name' => 'Smoke Fundraising Progress',
    'stage' => 'Fundraising',
    'amount' => 9520,
    'amountCurrency' => 'EUR',
]);
$em->saveEntity($opp);

$pn = $em->getNewEntity('PrimaNota');
$pn->set([
    'description' => 'Smoke donation',
    'entryType' => 'Income',
    'amountGross' => 1255.22,
    'commissionAmount' => 0,
    'transactionDate' => date('Y-m-d'),
    'financingId' => $opp->getId(),
]);
$em->saveEntity($pn);

$loaded = $em->getEntityById('Opportunity', $opp->getId());
$params = Params::create()->withSelect([
    'fundraisingCollectedAmount',
    'fundraisingTargetAmount',
    'fundraisingProgressPercent',
]);
$loader->process($loaded, $params);

$collected = (float) $loaded->get('fundraisingCollectedAmount');
$percent = (int) $loaded->get('fundraisingProgressPercent');

$ok('collected amount', abs($collected - 1255.22) < 0.01, (string) $collected);
$ok('progress percent', $percent === 13, (string) $percent);

$oppClosed = $em->getNewEntity('Opportunity');
$oppClosed->set([
    'name' => 'Smoke Fundraising Closed Won',
    'stage' => 'Closed Won',
    'amount' => 1000,
    'amountCurrency' => 'EUR',
]);
$em->saveEntity($oppClosed);

$pn2 = $em->getNewEntity('PrimaNota');
$pn2->set([
    'description' => 'Smoke closed won',
    'entryType' => 'Income',
    'amountGross' => 500,
    'commissionAmount' => 0,
    'transactionDate' => date('Y-m-d'),
    'financingId' => $oppClosed->getId(),
]);
$em->saveEntity($pn2);

$loadedClosed = $em->getEntityById('Opportunity', $oppClosed->getId());
$loader->process($loadedClosed, $params);
$ok('closed won collected', (float) $loadedClosed->get('fundraisingCollectedAmount') === 500.0);

$pnExpense = $em->getNewEntity('PrimaNota');
$pnExpense->set([
    'description' => 'Smoke expense',
    'entryType' => 'Expense',
    'amountGross' => 300,
    'commissionAmount' => 0,
    'transactionDate' => date('Y-m-d'),
    'financingId' => $opp->getId(),
]);
$em->saveEntity($pnExpense);

$loadedNet = $em->getEntityById('Opportunity', $opp->getId());
$loader->process($loadedNet, $params);
$ok('net collected after expense', abs((float) $loadedNet->get('fundraisingCollectedAmount') - 955.22) < 0.01);

$em->removeEntity($pnExpense);

$em->removeEntity($pn2);
$em->removeEntity($oppClosed);

$em->removeEntity($pn);
$em->removeEntity($opp);

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
