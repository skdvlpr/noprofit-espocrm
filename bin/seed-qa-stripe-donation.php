<?php
/**
 * Mock Stripe donation → PrimaNota (kept for manual QA — does NOT delete).
 *
 * Simulates a successful Stripe payment without calling Stripe:
 *   gross 100.00 EUR, fee 2.9% → net 97.10 EUR via beforeSave formula.
 *
 * Usage:
 *   ddev exec php bin/seed-qa-stripe-donation.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

$ref = 'QA-STRIPE-MOCK-' . date('Ymd-His');

$entry = $em->getNewEntity('PrimaNota');
$entry->set([
    'entryType' => 'Income',
    'internalClassification' => 'Donation',
    'donationPaymentProvider' => 'Stripe',
    'donationPaymentReference' => $ref,
    'donationDonorCategory' => 'Individual',
    'donationComment' => 'Mock Stripe donation (no real payment). Gross 100 EUR, commission 2.9%.',
    'amountGross' => 100.0,
    'amountGrossCurrency' => 'EUR',
    'commissionPercent' => 2.9,
    'transactionDate' => date('Y-m-d'),
    'modelDClassification' => 'C',
    'subjectName' => 'QA Stripe Mock Donor',
]);

$em->saveEntity($entry);

$fresh = $em->getEntityById('PrimaNota', $entry->getId());

echo "Kept PrimaNota for manual QA (not deleted)\n";
echo "  id:                 " . $fresh->getId() . "\n";
echo "  name:               " . $fresh->get('name') . "\n";
echo "  donationReference:  " . $fresh->get('donationPaymentReference') . "\n";
echo "  amountGross:        " . $fresh->get('amountGross') . " " . $fresh->get('amountGrossCurrency') . "\n";
echo "  commissionPercent:  " . $fresh->get('commissionPercent') . "\n";
echo "  commissionAmount:   " . $fresh->get('commissionAmount') . " " . $fresh->get('commissionAmountCurrency') . "\n";
echo "  amount (net):       " . $fresh->get('amount') . " " . $fresh->get('amountCurrency') . "\n";
echo "  amountIn:           " . $fresh->get('amountIn') . "\n";
echo "  UI:                 #PrimaNota/view/" . $fresh->getId() . "\n";

$netOk = abs((float) $fresh->get('amount') - 97.1) < 0.001;
$feeOk = abs((float) $fresh->get('commissionAmount') - 2.9) < 0.001;

if (!$netOk || !$feeOk) {
    fwrite(STDERR, "FAIL: formula did not compute expected net/fee\n");
    exit(1);
}

echo "Formula check: OK (gross+percent → fee+net)\n";
