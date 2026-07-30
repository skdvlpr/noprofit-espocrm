<?php
/**
 * Set PrimaNota opening cash balance used by Saldo di cassa (CRM-S16).
 *
 * Stored in Espo config:
 *   primaNotaOpeningCashBalance — float EUR
 *   primaNotaOpeningCashAsOf    — optional Y-m-d; ledger rows after this date are summed
 *
 * Usage (DDEV):
 *   ddev exec php bin/set-prima-nota-opening-cash.php 1234.56
 *   ddev exec php bin/set-prima-nota-opening-cash.php 1234.56 --as-of=2026-01-01
 */

declare(strict_types=1);

require __DIR__ . '/lib/refuse-production.php';

include dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\PrimaNotaStatsProvider;

$amountArg = $argv[1] ?? null;
$asOf = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--as-of=')) {
        $asOf = substr($arg, strlen('--as-of='));
    }
}

if ($amountArg === null || ! is_numeric($amountArg)) {
    fwrite(STDERR, "Usage: php bin/set-prima-nota-opening-cash.php <amount> [--as-of=YYYY-MM-DD]\n");
    exit(1);
}

if ($asOf !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf) !== 1) {
    fwrite(STDERR, "Invalid --as-of date (expected YYYY-MM-DD).\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var Config $config */
$config = $container->getByClass(Config::class);
/** @var ConfigWriter $writer */
$writer = $container->getByClass(ConfigWriter::class);

$amount = round((float) $amountArg, 2);
$writer->set(PrimaNotaStatsProvider::CONFIG_OPENING_CASH, $amount);

if ($asOf !== null) {
    $writer->set(PrimaNotaStatsProvider::CONFIG_OPENING_CASH_AS_OF, $asOf);
}

$writer->save();

echo "Set " . PrimaNotaStatsProvider::CONFIG_OPENING_CASH . "={$amount}\n";
if ($asOf !== null) {
    echo "Set " . PrimaNotaStatsProvider::CONFIG_OPENING_CASH_AS_OF . "={$asOf}\n";
} else {
    $existing = $config->get(PrimaNotaStatsProvider::CONFIG_OPENING_CASH_AS_OF);
    echo "asOf (unchanged): " . ($existing !== null && $existing !== '' ? (string) $existing : '(none)') . "\n";
}

exit(0);
