<?php
/**
 * Smoke: PrimaNota Stripe commission ↔ percent interdependence.
 *
 * Usage: ddev exec php bin/smoke-prima-nota-stripe-commission.php
 *
 * Does NOT delete QA-STRIPE-MOCK* manual-test rows.
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Metadata;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
$em = $container->get('entityManager');
/** @var Metadata $metadata */
$metadata = $container->get('metadata');

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    echo '  [' . ($pass ? 'PASS' : 'FAIL') . '] ' . $name . ($detail !== '' ? " — $detail" : '') . "\n";
};

echo "PrimaNota Stripe commission metadata\n";
$ok('amountGross field', $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'amountGross', 'type']) === 'currency');
$ok('commissionAmount field', $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'commissionAmount', 'type']) === 'currency');
$ok('commissionPercent field', $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'commissionPercent', 'type']) === 'float');
$ok('commissionAmount default 0', (float) ($metadata->get(['entityDefs', 'PrimaNota', 'fields', 'commissionAmount', 'default']) ?? -1) === 0.0);
$ok('commissionPercent default 0', (float) ($metadata->get(['entityDefs', 'PrimaNota', 'fields', 'commissionPercent', 'default']) ?? -1) === 0.0);

$created = [];

$fromPercent = $em->getNewEntity('PrimaNota');
$fromPercent->set([
    'description' => 'Smoke Stripe gross+percent',
    'entryType' => 'Income',
    'internalClassification' => 'Donation',
    'donationPaymentProvider' => 'Stripe',
    'donationPaymentReference' => 'SMOKE-STRIPE-' . date('His'),
    'amountGross' => 100.0,
    'amountGrossCurrency' => 'EUR',
    'commissionPercent' => 2.9,
    'transactionDate' => date('Y-m-d'),
]);
$em->saveEntity($fromPercent);
$created[] = $fromPercent;

$ok('net from gross+percent', abs((float) $fromPercent->get('amount') - 97.1) < 0.001, 'amount=' . $fromPercent->get('amount'));
$ok('fee from percent', abs((float) $fromPercent->get('commissionAmount') - 2.9) < 0.001, 'fee=' . $fromPercent->get('commissionAmount'));
$ok('amountIn uses net', abs((float) $fromPercent->get('amountIn') - 97.1) < 0.001, 'amountIn=' . $fromPercent->get('amountIn'));

$fromFee = $em->getNewEntity('PrimaNota');
$fromFee->set([
    'description' => 'Smoke Stripe gross+fee',
    'entryType' => 'Income',
    'internalClassification' => 'Donation',
    'donationPaymentProvider' => 'Stripe',
    'donationPaymentReference' => 'SMOKE-STRIPE-FEE-' . date('His'),
    'amountGross' => 50.0,
    'amountGrossCurrency' => 'EUR',
    'commissionAmount' => 1.55,
    'commissionAmountCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
]);
$em->saveEntity($fromFee);
$created[] = $fromFee;

$ok('net from gross+fee', abs((float) $fromFee->get('amount') - 48.45) < 0.001, 'amount=' . $fromFee->get('amount'));
$ok('percent derived from fee', abs((float) $fromFee->get('commissionPercent') - 3.1) < 0.01, 'pct=' . $fromFee->get('commissionPercent'));

$emptyFee = $em->getNewEntity('PrimaNota');
$emptyFee->set([
    'description' => 'Smoke Stripe empty commission → 0',
    'entryType' => 'Income',
    'internalClassification' => 'Donation',
    'donationPaymentProvider' => 'Stripe',
    'donationPaymentReference' => 'SMOKE-STRIPE-EMPTY-' . date('His'),
    'amountGross' => 80.0,
    'amountGrossCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
]);
$em->saveEntity($emptyFee);
$created[] = $emptyFee;
$ok('empty commission → percent 0', abs((float) $emptyFee->get('commissionPercent')) < 0.001, 'pct=' . $emptyFee->get('commissionPercent'));
$ok('empty commission → fee 0', abs((float) $emptyFee->get('commissionAmount')) < 0.001, 'fee=' . $emptyFee->get('commissionAmount'));
$ok('empty commission → net equals gross', abs((float) $emptyFee->get('amount') - 80.0) < 0.001, 'amount=' . $emptyFee->get('amount'));

$legacy = $em->getNewEntity('PrimaNota');
$legacy->set([
    'description' => 'Smoke legacy net-only amount',
    'entryType' => 'Income',
    'amount' => 33.0,
    'amountCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
]);
$em->saveEntity($legacy);
$created[] = $legacy;
$ok('legacy amount unchanged without gross', abs((float) $legacy->get('amount') - 33.0) < 0.001);

$zeroNet = $em->getNewEntity('PrimaNota');
$zeroNet->set([
    'description' => 'Smoke Stripe commission equals gross',
    'entryType' => 'Income',
    'internalClassification' => 'Donation',
    'donationPaymentProvider' => 'Stripe',
    'donationPaymentReference' => 'SMOKE-STRIPE-ZERO-' . date('His'),
    'amountGross' => 10.0,
    'amountGrossCurrency' => 'EUR',
    'commissionAmount' => 10.0,
    'commissionAmountCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
]);
$em->saveEntity($zeroNet);
$created[] = $zeroNet;
$ok('net zero allowed when fee equals gross', abs((float) $zeroNet->get('amount')) < 0.001, 'amount=' . $zeroNet->get('amount'));
$ok('amountIn zero when net zero', abs((float) $zeroNet->get('amountIn')) < 0.001);

// Critical: edit fee currency on existing row → percent must recalculate
$editFee = $em->getNewEntity('PrimaNota');
$editFee->set([
    'description' => 'Smoke edit fee updates percent',
    'entryType' => 'Income',
    'internalClassification' => 'Donation',
    'donationPaymentProvider' => 'Stripe',
    'donationPaymentReference' => 'SMOKE-STRIPE-EDIT-FEE-' . date('His'),
    'amountGross' => 100.0,
    'amountGrossCurrency' => 'EUR',
    'commissionPercent' => 2.9,
    'transactionDate' => date('Y-m-d'),
]);
$em->saveEntity($editFee);
$created[] = $editFee;
$editFee = $em->getEntityById('PrimaNota', $editFee->getId());
$editFee->set('commissionAmount', 5.0);
$editFee->set('commissionAmountCurrency', 'EUR');
$em->saveEntity($editFee);
$editFee = $em->getEntityById('PrimaNota', $editFee->getId());
$ok('edit fee → percent recalculated', abs((float) $editFee->get('commissionPercent') - 5.0) < 0.01, 'pct=' . $editFee->get('commissionPercent'));
$ok('edit fee → net recalculated', abs((float) $editFee->get('amount') - 95.0) < 0.001, 'amount=' . $editFee->get('amount'));

// Edit percent on existing → fee must recalculate
$editPct = $em->getNewEntity('PrimaNota');
$editPct->set([
    'description' => 'Smoke edit percent updates fee',
    'entryType' => 'Income',
    'internalClassification' => 'Donation',
    'donationPaymentProvider' => 'Stripe',
    'donationPaymentReference' => 'SMOKE-STRIPE-EDIT-PCT-' . date('His'),
    'amountGross' => 100.0,
    'amountGrossCurrency' => 'EUR',
    'commissionAmount' => 2.9,
    'commissionAmountCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
]);
$em->saveEntity($editPct);
$created[] = $editPct;
$editPct = $em->getEntityById('PrimaNota', $editPct->getId());
$editPct->set('commissionPercent', 4.0);
$em->saveEntity($editPct);
$editPct = $em->getEntityById('PrimaNota', $editPct->getId());
$ok('edit percent → fee recalculated', abs((float) $editPct->get('commissionAmount') - 4.0) < 0.001, 'fee=' . $editPct->get('commissionAmount'));
$ok('edit percent → net recalculated', abs((float) $editPct->get('amount') - 96.0) < 0.001, 'amount=' . $editPct->get('amount'));

// Edit gross, keep percent → fee refreshes from rate
$editGross = $em->getNewEntity('PrimaNota');
$editGross->set([
    'description' => 'Smoke edit gross keeps percent rate',
    'entryType' => 'Income',
    'internalClassification' => 'Donation',
    'donationPaymentProvider' => 'Stripe',
    'donationPaymentReference' => 'SMOKE-STRIPE-EDIT-GROSS-' . date('His'),
    'amountGross' => 100.0,
    'amountGrossCurrency' => 'EUR',
    'commissionPercent' => 10.0,
    'transactionDate' => date('Y-m-d'),
]);
$em->saveEntity($editGross);
$created[] = $editGross;
$editGross = $em->getEntityById('PrimaNota', $editGross->getId());
$editGross->set('amountGross', 200.0);
$editGross->set('amountGrossCurrency', 'EUR');
$em->saveEntity($editGross);
$editGross = $em->getEntityById('PrimaNota', $editGross->getId());
$ok('edit gross → fee from percent', abs((float) $editGross->get('commissionAmount') - 20.0) < 0.001, 'fee=' . $editGross->get('commissionAmount'));
$ok('edit gross → percent kept', abs((float) $editGross->get('commissionPercent') - 10.0) < 0.01, 'pct=' . $editGross->get('commissionPercent'));
$ok('edit gross → net', abs((float) $editGross->get('amount') - 180.0) < 0.001, 'amount=' . $editGross->get('amount'));

// Clear lordo + fee currency → both become 0 and % resets (critical UX)
$clearAll = $em->getNewEntity('PrimaNota');
$clearAll->set([
    'description' => 'Smoke clear gross and fee resets percent',
    'entryType' => 'Income',
    'internalClassification' => 'Donation',
    'donationPaymentProvider' => 'Stripe',
    'donationPaymentReference' => 'SMOKE-STRIPE-CLEAR-' . date('His'),
    'amountGross' => 100.0,
    'amountGrossCurrency' => 'EUR',
    'commissionPercent' => 22.049,
    'transactionDate' => date('Y-m-d'),
]);
$em->saveEntity($clearAll);
$created[] = $clearAll;
$clearAll = $em->getEntityById('PrimaNota', $clearAll->getId());
$keptNet = (float) $clearAll->get('amount');
$clearAll->set('amountGross', null);
$clearAll->set('commissionAmount', null);
$em->saveEntity($clearAll);
$clearAll = $em->getEntityById('PrimaNota', $clearAll->getId());
$ok('clear gross → amountGross is 0', abs((float) $clearAll->get('amountGross')) < 0.001, 'gross=' . $clearAll->get('amountGross'));
$ok('clear fee → commissionAmount is 0', abs((float) $clearAll->get('commissionAmount')) < 0.001, 'fee=' . $clearAll->get('commissionAmount'));
$ok('clear gross/fee → percent reset to 0', abs((float) $clearAll->get('commissionPercent')) < 0.001, 'pct=' . $clearAll->get('commissionPercent'));
$ok('clear gross/fee → net preserved', abs((float) $clearAll->get('amount') - $keptNet) < 0.001, 'amount=' . $clearAll->get('amount'));

$formulaPath = __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/formula/PrimaNota.json';
$formulaData = is_file($formulaPath) ? (json_decode((string) file_get_contents($formulaPath), true) ?: []) : [];
$formulaScript = (string) ($formulaData['beforeSaveCustomScript'] ?? '');
$ok('formula uses isAttributeChanged for fee', str_contains($formulaScript, "isAttributeChanged('commissionAmount')"));
$ok('formula uses isAttributeChanged for percent', str_contains($formulaScript, "isAttributeChanged('commissionPercent')"));
$ok('formula sets amount net from gross', str_contains($formulaScript, 'amountGross - commissionAmount'));

foreach ($created as $row) {
    $em->removeEntity($row);
}

$qaLeft = $em->getRDBRepository('PrimaNota')
    ->where(['donationPaymentReference*' => 'QA-STRIPE-MOCK%'])
    ->find();
$qaCount = iterator_count($qaLeft);
$ok('QA Stripe mock rows kept', $qaCount >= 1, 'count=' . $qaCount);

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
