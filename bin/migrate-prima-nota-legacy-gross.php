<?php
/**
 * One-shot: backfill PrimaNota commission triangle for legacy net-only rows
 * and normalize donationPaymentProvider to the enum keys.
 *
 * For rows with amount_gross IS NULL:
 *   amount_gross = amount (lordo = former netto)
 *   commission_amount = 0
 *   commission_percent = 0
 *
 * Provider map (free-text → enum):
 *   Stripe stays Stripe; empty stays empty; unknown / Manual → Other
 *
 * Idempotent. Does NOT push itself — run after manual QA approval on each env:
 *   ddev exec php bin/migrate-prima-nota-legacy-gross.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\Repository\Option\SaveOption;

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

$repo = $em->getRDBRepository('PrimaNota');

$allowedProviders = [
    'Stripe',
    'SatispayDirect',
    'FivePerMille',
    'BankTransfer',
    'Cash',
    'Other',
];

$updatedGross = 0;
$skipped = 0;
$updatedProvider = 0;

$rows = $repo->where(['amountGross' => null])->find();

foreach ($rows as $entity) {
    $amount = $entity->get('amount');
    if ($amount === null || $amount === '') {
        $skipped++;
        continue;
    }

    $currency = $entity->get('amountCurrency') ?: 'EUR';

    $entity->set([
        'amountGross' => (float) $amount,
        'amountGrossCurrency' => $currency,
        'commissionAmount' => 0.0,
        'commissionAmountCurrency' => $currency,
        'commissionPercent' => 0.0,
    ]);

    $em->saveEntity($entity, [SaveOption::SKIP_ALL => true]);
    $updatedGross++;
}

$all = $repo->where(['donationPaymentProvider!=' => null])->find();

foreach ($all as $entity) {
    $raw = trim((string) ($entity->get('donationPaymentProvider') ?? ''));
    if ($raw === '') {
        continue;
    }

    $normalized = match (strtolower($raw)) {
        'stripe' => 'Stripe',
        'satispaydirect', 'satispay direct', 'satispay direct (not stripe)', 'satispay' => 'SatispayDirect',
        'fivepermille', '5x1000', '5 x 1000', '5 per mille', '5xmille' => 'FivePerMille',
        'banktransfer', 'bank transfer', 'bonifico' => 'BankTransfer',
        'cash', 'contanti' => 'Cash',
        'other', 'altro', 'manual' => 'Other',
        default => in_array($raw, $allowedProviders, true) ? $raw : 'Other',
    };

    if ($normalized === $raw) {
        continue;
    }

    $entity->set('donationPaymentProvider', $normalized);
    $em->saveEntity($entity, [SaveOption::SKIP_ALL => true]);
    $updatedProvider++;
}

echo "migrate-prima-nota-legacy-gross\n";
echo "  updated_gross={$updatedGross}\n";
echo "  skipped_null_amount={$skipped}\n";
echo "  updated_provider={$updatedProvider}\n";
echo "  remaining_null_gross=" . $repo->where(['amountGross' => null])->count() . "\n";
echo "DONE\n";
exit(0);
