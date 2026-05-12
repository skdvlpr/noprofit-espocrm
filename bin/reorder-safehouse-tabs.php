<?php
/**
 * Move SafehouseCrm custom entities into the top "$CRM" navbar section,
 * placing them right after `Contact` (or, if `Contact` is absent, right after
 * the `$CRM` divider).
 *
 * Idempotent: re-running has no effect once the order matches.
 *
 * Affected entities (the canonical Safehouse domain set, in this order):
 *   - VolunteerEmployee
 *   - Member
 *   - MealCount
 *
 * Usage:
 *   php bin/reorder-safehouse-tabs.php
 *   ddev exec php bin/reorder-safehouse-tabs.php
 *
 * After running: Admin → Repair → Rebuild → Clear Cache; browser hard-refresh.
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

$entitiesToMove = ['VolunteerEmployee', 'Member', 'MealCount'];

$app = new Application();
$container = $app->getContainer();

/** @var Config $config */
$config = $container->getByClass(Config::class);

/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);

$configWriter = $injectableFactory->create(ConfigWriter::class);

$tabList = $config->get('tabList') ?? [];

if (!is_array($tabList) || $tabList === []) {
    echo "tabList is empty or not an array. Nothing to do.\n";
    return;
}

$without = array_values(array_filter(
    $tabList,
    static function ($item) use ($entitiesToMove): bool {
        return !(is_string($item) && in_array($item, $entitiesToMove, true));
    }
));

$insertIndex = null;
$contactIndex = null;
$crmDividerIndex = null;
foreach ($without as $i => $item) {
    if (is_string($item) && $item === 'Contact') {
        $contactIndex = $i;
    }
    if (
        $crmDividerIndex === null
        && is_object($item)
        && ($item->type ?? null) === 'divider'
        && ($item->text ?? null) === '$CRM'
    ) {
        $crmDividerIndex = $i;
    }
}

if ($contactIndex !== null) {
    $insertIndex = $contactIndex + 1;
} elseif ($crmDividerIndex !== null) {
    $insertIndex = $crmDividerIndex + 1;
} else {
    $insertIndex = 0;
}

$rebuilt = array_merge(
    array_slice($without, 0, $insertIndex),
    $entitiesToMove,
    array_slice($without, $insertIndex)
);

if ($rebuilt === $tabList) {
    echo "tabList already in desired order. Nothing to do.\n";
    return;
}

$configWriter->set('tabList', $rebuilt);
$configWriter->save();

echo "tabList reordered. Placed [" . implode(', ', $entitiesToMove) . "] at index $insertIndex.\n";
echo "Run Admin -> Repair -> Rebuild -> Clear Cache, then hard-refresh.\n\n";

echo "New order (showing first 12 entries):\n";
foreach (array_slice($rebuilt, 0, 12) as $i => $item) {
    if (is_object($item)) {
        $text = $item->text ?? '(no text)';
        echo sprintf("  [%2d] divider: %s\n", $i, var_export($text, true));
    } else {
        echo sprintf("  [%2d] %s\n", $i, $item);
    }
}
echo "Done.\n";
