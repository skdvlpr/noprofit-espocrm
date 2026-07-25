<?php
/**
 * One-shot: backfill PrimaNota commission triangle for legacy net-only rows.
 *
 * For rows with amount_gross IS NULL:
 *   amount_gross = amount (lordo = former netto)
 *   commission_amount = 0
 *   commission_percent = 0
 *
 * Idempotent. Does NOT push itself — run after manual QA approval on each env:
 *   ddev exec php bin/migrate-prima-nota-legacy-gross.php
 *   # prod: php bin/migrate-prima-nota-legacy-gross.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\Repository\Option\SaveOption;

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

$repo = $em->getRDBRepository('PrimaNota');
$rows = $repo
    ->where(['amountGross' => null])
    ->find();

$updated = 0;
$skipped = 0;

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
    $updated++;
}

echo "migrate-prima-nota-legacy-gross\n";
echo "  updated={$updated}\n";
echo "  skipped_null_amount={$skipped}\n";
echo "  remaining_null_gross=" . $repo->where(['amountGross' => null])->count() . "\n";
echo $updated >= 0 ? "DONE\n" : "FAILED\n";
exit(0);
