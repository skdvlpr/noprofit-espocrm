<?php

declare(strict_types=1);


require __DIR__ . '/lib/refuse-production.php';


/**
 * Smoke: Safehouse kanban frontend assets are served and cache-busted via appTimestamp.
 *
 * Verifies:
 *   - BumpAppTimestamp is registered in merged rebuild metadata
 *   - kanban-item.tpl/js and kanban-card.css return 200 with ?r={appTimestamp}
 *   - Template/JS contain expected layout markers (table layout, hasStatItems)
 *
 * Usage:
 *   ddev exec php bin/smoke-kanban-assets.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use GuzzleHttp\Client;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var Config $config */
$config = $container->getByClass(Config::class);
/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);
$metadata->init(true);

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: config siteUrl is empty.\n");
    exit(1);
}

$appTimestamp = (string) ($config->get('appTimestamp') ?? '');
if ($appTimestamp === '') {
    fwrite(STDERR, "FAIL: config appTimestamp is empty.\n");
    exit(1);
}

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $m = $pass ? 'PASS' : 'FAIL';
    echo "  [$m] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$actionList = $metadata->get(['app', 'rebuild', 'actionClassNameList']) ?? [];
$ok(
    'BumpAppTimestamp registered',
    in_array('Espo\\Modules\\NonprofitEspocrm\\Core\\Rebuild\\BumpAppTimestamp', $actionList, true),
    'actionClassNameList count=' . count($actionList)
);

$client = new Client([
    'base_uri' => $siteUrl,
    'http_errors' => false,
    'timeout' => 15,
    'verify' => false,
]);

$assets = [
    'kanban-item.tpl' => [
        'path' => '/client/custom/modules/nonprofit-espocrm/res/templates/record/kanban-item.tpl',
        'needles' => ['kanban-props-grid', 'kanban-dates-grid', 'kanban-stage-chip', 'hasStatItems'],
    ],
    'kanban-item.js' => [
        'path' => '/client/custom/modules/nonprofit-espocrm/src/views/record/kanban-item.js',
        'needles' => ['hasStatItems', 'statItems', 'STAGE_EMOJI', 'getStageInfo'],
    ],
    'kanban-card.css' => [
        'path' => '/client/custom/modules/nonprofit-espocrm/res/css/kanban-card.css',
        'needles' => ['kanban-props-grid', 'safehouse-kanban-card', 'kanban-stage-chip', 'kanban-prob-pill'],
    ],
];

echo "Base URL: $siteUrl\n";
echo "appTimestamp: $appTimestamp\n";

foreach ($assets as $label => $asset) {
    $url = $asset['path'] . '?r=' . rawurlencode($appTimestamp);
    $response = $client->get($url);
    $status = $response->getStatusCode();
    $body = (string) $response->getBody();

    $ok("$label HTTP 200", $status === 200, "status=$status url=$url");

    foreach ($asset['needles'] as $needle) {
        $ok("$label contains $needle", str_contains($body, $needle));
    }
}

$dashletTplPath = 'client/custom/res/templates/dashlet.tpl';
$dashletTpl = is_readable($dashletTplPath) ? file_get_contents($dashletTplPath) : '';

$ok('dashlet.tpl exists', $dashletTpl !== '');
$ok(
    'dashlet.tpl fix-position wrapper for Espo uiAppInit',
    str_contains($dashletTpl, 'pull-right fix-position') &&
    !str_contains($dashletTpl, 'btn-group pull-right fix-position')
);
$ok(
    'dashlet-dropdown.js removed from scriptList',
    !is_readable('client/custom/modules/nonprofit-espocrm/lib/dashlet-dropdown.js')
);

$reportingStatsCss = is_readable('client/custom/modules/nonprofit-espocrm/res/css/reporting-stats.css')
    ? file_get_contents('client/custom/modules/nonprofit-espocrm/res/css/reporting-stats.css')
    : '';

$ok('reporting-stats.css exists', $reportingStatsCss !== '');
$ok(
    'reporting-stats theme-agnostic tokens',
    str_contains($reportingStatsCss, 'var(--panel-default-border') &&
    str_contains($reportingStatsCss, 'var(--brand-primary') &&
    !str_contains($reportingStatsCss, '#dfe2e5') &&
    !str_contains($reportingStatsCss, '#e74c3c')
);
$ok(
    'reporting-stats tunable tint variables',
    str_contains($reportingStatsCss, '--sh-reporting-cell-tint') &&
    str_contains($reportingStatsCss, 'color-mix')
);

if ($fail > 0) {
    fwrite(STDERR, "\n$fail check(s) failed.\n");
    exit(1);
}

echo "\nAll kanban asset checks passed.\n";
