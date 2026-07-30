<?php
/**
 * Migrate PrimaNota.paymentStatus to Inviato / Planned model.
 *
 * Rules:
 *   PaidOut → Inviato (already bank-paid Stripe or legacy label)
 *   Paid + Stripe platform + no stripePayoutId → Planned (awaiting payout)
 *   Paid (manual / other / already has payout id) → Inviato
 *
 * Cash totals count only Inviato (+ legacy null).
 *
 * Usage (DDEV only — refuse-production blocks prod paths):
 *   ddev exec php bin/migrate-prima-nota-payment-status-inviato.php --dry-run
 *   ddev exec php bin/migrate-prima-nota-payment-status-inviato.php
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

$counts = [
    'PaidOut→Inviato' => 0,
    'Paid+Stripe→Planned' => 0,
    'Paid→Inviato' => 0,
];
$samples = [];

$legacy = $em->getRDBRepository('PrimaNota')
    ->where([
        'paymentStatus' => ['Paid', 'PaidOut'],
    ])
    ->order('createdAt', 'ASC')
    ->find();

foreach ($legacy as $entity) {
    $from = (string) ($entity->get('paymentStatus') ?? '');
    $platform = (string) ($entity->get('donationPaymentProvider') ?? '');
    $payoutId = trim((string) ($entity->get('stripePayoutId') ?? ''));

    if ($from === 'PaidOut') {
        $to = 'Inviato';
        $bucket = 'PaidOut→Inviato';
    } elseif (
        $from === 'Paid'
        && $platform === 'Stripe'
        && $payoutId === ''
    ) {
        $to = 'Planned';
        $bucket = 'Paid+Stripe→Planned';
    } else {
        $to = 'Inviato';
        $bucket = 'Paid→Inviato';
    }

    if (count($samples) < 20) {
        $samples[] = [
            'id' => (string) $entity->getId(),
            'from' => $from,
            'to' => $to,
            'platform' => $platform,
            'stripePayoutId' => $payoutId,
        ];
    }

    if (! $dryRun) {
        $entity->set('paymentStatus', $to);
        $em->saveEntity($entity, [
            SaveOption::SKIP_ALL => true,
            SaveOption::SILENT => true,
        ]);
    }

    $counts[$bucket]++;
}

echo ($dryRun ? 'DRY-RUN' : 'APPLIED') . " PrimaNota Paid/PaidOut → Inviato/Planned\n";
foreach ($counts as $label => $n) {
    echo "  {$label}: {$n}\n";
}
echo "  sample:\n";
foreach ($samples as $row) {
    echo '    ' . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

if ($dryRun) {
    echo "Re-run without --dry-run to apply.\n";
}

exit(0);
