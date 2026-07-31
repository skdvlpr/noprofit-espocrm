<?php
/**
 * Migrate PrimaNota.paymentStatus Paid/PaidOut → Inviato.
 *
 * Safe rule: always map legacy counted statuses to Inviato. Do not demote
 * Stripe rows to Planned when stripePayoutId is empty — that field is new and
 * empty on historical banked donations.
 *
 * Usage (DDEV only — refuse-production blocks prod paths):
 *   ddev exec php bin/migrate-prima-nota-payment-status-inviato.php --dry-run
 *   ddev exec php bin/migrate-prima-nota-payment-status-inviato.php
 *
 * Production upgrades: Tools\Installer::runPostInstall() runs the same migrator.
 */

declare(strict_types=1);

require __DIR__ . '/lib/refuse-production.php';

include dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Modules\NonprofitEspocrm\Tools\PrimaNota\PaymentStatusLegacyMigrator;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv, true);

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

$counts = (new PaymentStatusLegacyMigrator())->migrate($em, $dryRun);

echo ($dryRun ? 'DRY-RUN' : 'APPLIED') . " PrimaNota Paid/PaidOut → Inviato\n";
foreach ($counts as $label => $n) {
    echo "  {$label}: {$n}\n";
}

if ($dryRun) {
    echo "Re-run without --dry-run to apply.\n";
}

exit(0);
