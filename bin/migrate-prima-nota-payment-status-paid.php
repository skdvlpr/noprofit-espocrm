<?php
/**
 * Backfill PrimaNota.paymentStatus Planned → Inviato for historical ledger rows.
 *
 * Legacy helper (pre Inviato rename). Prefer
 * bin/migrate-prima-nota-payment-status-inviato.php for Paid/PaidOut migration.
 *
 * Why: dashlets count only Inviato (and legacy null). Field default for manual
 * rows is Inviato; Stripe ingest uses Planned until payout.
 *
 * Policy:
 *   - paymentStatus = Planned
 *   - NOT Stripe-sourced (donationPaymentProvider != Stripe)
 *   - transactionDate IS NULL OR transactionDate <= today (Europe/Rome)
 *   → set Inviato
 *   - Never touch Cancelled / Refunded / Disputed / Problematic / Inviato
 *   - Stripe Planned rows stay Planned until payout.paid
 *
 * Usage (DDEV only — refuse-production blocks prod paths):
 *   ddev exec php bin/migrate-prima-nota-payment-status-paid.php --dry-run
 *   ddev exec php bin/migrate-prima-nota-payment-status-paid.php
 */

declare(strict_types=1);

require __DIR__ . '/lib/refuse-production.php';

include dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv, true);

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');

$candidates = $em->getRDBRepository('PrimaNota')
    ->where([
        'paymentStatus' => 'Planned',
        'AND' => [
            [
                'OR' => [
                    ['donationPaymentProvider!=' => 'Stripe'],
                    ['donationPaymentProvider' => null],
                ],
            ],
            [
                'OR' => [
                    ['transactionDate' => null],
                    ['transactionDate<=' => $today],
                ],
            ],
        ],
    ])
    ->order('transactionDate', 'ASC')
    ->find();

$updated = 0;
$samples = [];

foreach ($candidates as $entity) {
    if (count($samples) < 15) {
        $samples[] = [
            'id' => (string) $entity->getId(),
            'transactionDate' => (string) ($entity->get('transactionDate') ?? ''),
            'entryType' => (string) ($entity->get('entryType') ?? ''),
            'amount' => $entity->get('amount'),
        ];
    }

    if (! $dryRun) {
        $entity->set('paymentStatus', 'Inviato');
        $em->saveEntity($entity, [
            SaveOption::SKIP_ALL => true,
            SaveOption::SILENT => true,
        ]);
    }

    $updated++;
}

echo ($dryRun ? 'DRY-RUN' : 'APPLIED') . " PrimaNota Planned→Inviato (non-Stripe)\n";
echo "  today (Europe/Rome): {$today}\n";
echo "  rows: {$updated}\n";
echo "  sample:\n";
foreach ($samples as $row) {
    echo '    ' . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

if ($dryRun) {
    echo "Re-run without --dry-run to apply.\n";
}

exit(0);
