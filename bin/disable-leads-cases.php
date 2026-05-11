<?php
/**
 * One-shot maintenance script: hide `Lead` and `Case` from the navbar
 * by removing them from `tabList` and `quickCreateList` in config.
 *
 * - Does NOT delete entities or data.
 * - Does NOT touch ACL — direct API access (e.g. /api/v1/Lead) keeps working
 *   for users who already had access.
 * - Idempotent: re-running has no effect once tabs are removed.
 *
 * Pair with bin/enable-leads-cases.php to roll back.
 *
 * Usage (host or container):
 *   php bin/disable-leads-cases.php
 *   ddev exec php bin/disable-leads-cases.php
 *
 * After running: Admin → Repair → Rebuild → Clear Cache; browser hard-refresh.
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

$entitiesToHide = ['Lead', 'Case'];

$app = new Application();
$container = $app->getContainer();

/** @var Config $config */
$config = $container->getByClass(Config::class);

/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);

$configWriter = $injectableFactory->create(ConfigWriter::class);

$paramsToFilter = ['tabList', 'quickCreateList'];

$report = [];
$anyChange = false;

foreach ($paramsToFilter as $param) {
    $current = $config->get($param) ?? [];

    if (!is_array($current)) {
        $report[$param] = "skip (not an array)";
        continue;
    }

    $filtered = array_values(array_filter(
        $current,
        static function ($item) use ($entitiesToHide): bool {
            return !(is_string($item) && in_array($item, $entitiesToHide, true));
        }
    ));

    $removed = array_values(array_filter(
        $current,
        static function ($item) use ($entitiesToHide): bool {
            return is_string($item) && in_array($item, $entitiesToHide, true);
        }
    ));

    if (count($filtered) === count($current)) {
        $report[$param] = "no change (Lead/Case not present)";
        continue;
    }

    $configWriter->set($param, $filtered);
    $report[$param] = sprintf(
        "removed [%s] (%d -> %d)",
        implode(', ', $removed),
        count($current),
        count($filtered)
    );
    $anyChange = true;
}

if ($anyChange) {
    $configWriter->save();
    echo "Config updated. Run Admin -> Repair -> Rebuild -> Clear Cache, then hard-refresh.\n";
} else {
    echo "Nothing to change. Lead and Case are already absent from tabList and quickCreateList.\n";
}

foreach ($report as $param => $line) {
    echo "  - $param: $line\n";
}

echo "\nDone.\n";
