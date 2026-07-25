<?php
/**
 * Smoke: PrimaNota Stripe commission → net amount.
 *
 * Usage: ddev exec php bin/smoke-prima-nota-stripe-commission.php
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

$formulaPath = __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/formula/PrimaNota.json';
$formulaData = is_file($formulaPath) ? (json_decode((string) file_get_contents($formulaPath), true) ?: []) : [];
$formulaScript = (string) ($formulaData['beforeSaveCustomScript'] ?? '');
$ok('formula derives commissionAmount from percent', str_contains($formulaScript, 'amountGross * commissionPercent'));
$ok('formula derives commissionPercent from amount', str_contains($formulaScript, 'commissionAmount * 100.0 / amountGross'));
$ok('formula sets amount net from gross', str_contains($formulaScript, 'amountGross - commissionAmount'));

foreach ($created as $row) {
    $em->removeEntity($row);
}

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
