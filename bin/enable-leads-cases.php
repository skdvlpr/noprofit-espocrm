<?php
/**
 * Rollback for bin/disable-leads-cases.php — re-add `Lead` and `Case`
 * to `tabList` and `quickCreateList` (appended at the end of each list).
 *
 * Idempotent: re-running has no effect once tabs are present.
 *
 * Usage:
 *   php bin/enable-leads-cases.php
 *   ddev exec php bin/enable-leads-cases.php
 *
 * After running: Admin → Repair → Rebuild → Clear Cache; browser hard-refresh.
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

$entitiesToRestore = ['Lead', 'Case'];

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

    $toAdd = array_filter(
        $entitiesToRestore,
        static function (string $item) use ($current): bool {
            return !in_array($item, $current, true);
        }
    );

    if (count($toAdd) === 0) {
        $report[$param] = "no change (already present)";
        continue;
    }

    $newList = array_merge($current, array_values($toAdd));

    $configWriter->set($param, $newList);
    $report[$param] = sprintf(
        "added [%s] (%d -> %d)",
        implode(', ', $toAdd),
        count($current),
        count($newList)
    );
    $anyChange = true;
}

if ($anyChange) {
    $configWriter->save();
    echo "Config updated. Run Admin -> Repair -> Rebuild -> Clear Cache, then hard-refresh.\n";
} else {
    echo "Nothing to change. Lead and Case are already present in tabList and quickCreateList.\n";
}

foreach ($report as $param => $line) {
    echo "  - $param: $line\n";
}

echo "\nDone.\n";
