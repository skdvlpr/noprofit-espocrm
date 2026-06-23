<?php
/**
 * Re-apply Safehouse navbar tab order via {@see Installer}:
 *   - `$CRM` (Principali): Lead → Contact → Account → Opportunity → Member → VolunteerEmployee
 *   - `$CRM` (Principali): Lead → … → VolunteerEmployee → Case → `$Rendicontazione`
 *   - default theme: Safehouse Aurora Light (when still on stock Espo theme)
 *
 * Idempotent: re-running has no effect once the order matches.
 *
 * Usage:
 *   php bin/reorder-safehouse-tabs.php
 *   ddev exec php bin/reorder-safehouse-tabs.php
 *
 * After running: Admin → Repair → Rebuild → Clear Cache; browser hard-refresh.
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\Modules\NonprofitEspocrm\Tools\Installer;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

$installer = new Installer();
$installer->runPostInstall($container);

$config = $container->getByClass(Config::class);
$config->update();

$tabList = $config->get('tabList') ?? [];

echo "Safehouse tabList refreshed via Installer.\n";
echo "Run Admin -> Repair -> Rebuild -> Clear Cache, then hard-refresh.\n\n";

echo "Order (first 14 entries):\n";
foreach (array_slice($tabList, 0, 14) as $i => $item) {
    if (is_object($item)) {
        $type = $item->type ?? 'object';
        $text = $item->text ?? '(no text)';
        echo sprintf("  [%2d] %s: %s\n", $i, $type, var_export($text, true));
    } else {
        echo sprintf("  [%2d] %s\n", $i, $item);
    }
}
echo "Done.\n";
