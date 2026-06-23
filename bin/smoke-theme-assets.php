<?php
/**
 * Smoke: Safehouse Aurora theme + cssList assets are served with cache-bust query params.
 *
 * Verifies:
 *   - Theme stylesheets use cacheTimestamp (?r= on main HTML link)
 *   - cssList entries use appTimestamp
 *   - @import-free partials (layout, enum-colors) are reachable
 *
 * Usage:
 *   ddev exec php bin/smoke-theme-assets.php
 */

declare(strict_types=1);

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
$cacheTimestamp = (string) ($config->get('cacheTimestamp') ?? '');

if ($appTimestamp === '') {
    fwrite(STDERR, "FAIL: config appTimestamp is empty.\n");
    exit(1);
}

if ($cacheTimestamp === '') {
    fwrite(STDERR, "FAIL: config cacheTimestamp is empty.\n");
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

$cssList = $metadata->get(['app', 'client', 'cssList']) ?? [];
$expectedCss = [
    'client/custom/css/safehouse-aurora/safehouse-aurora-enum-colors.css',
    'client/custom/css/safehouse-aurora/safehouse-aurora-layout.css',
];

foreach ($expectedCss as $path) {
    $ok('cssList contains ' . basename($path), in_array($path, $cssList, true));
}

$themes = [
    'SafehouseAurora' => $metadata->get(['themes', 'SafehouseAurora', 'stylesheet']),
    'SafehouseAuroraLight' => $metadata->get(['themes', 'SafehouseAuroraLight', 'stylesheet']),
];

$client = new Client([
    'base_uri' => $siteUrl,
    'http_errors' => false,
    'timeout' => 15,
    'verify' => false,
]);

echo "Base URL: $siteUrl\n";
echo "appTimestamp: $appTimestamp\n";
echo "cacheTimestamp: $cacheTimestamp\n";

foreach ($themes as $label => $stylesheet) {
    if (!is_string($stylesheet) || $stylesheet === '') {
        $ok("$label stylesheet metadata", false, 'missing');
        continue;
    }

    $url = '/' . ltrim($stylesheet, '/') . '?r=' . rawurlencode($cacheTimestamp);
    $response = $client->get($url);
    $body = (string) $response->getBody();

    $ok("$label theme CSS HTTP 200", $response->getStatusCode() === 200, "url=$url");

    $ok(
        "$label theme has no @import layout (cssList path)",
        !str_contains($body, "@import url('safehouse-aurora-layout.css')")
            && !str_contains($body, '@import url("safehouse-aurora-layout.css")'),
    );
}

$cssAssets = [
    'enum-colors' => [
        'path' => '/client/custom/css/safehouse-aurora/safehouse-aurora-enum-colors.css',
        'needles' => ['.label-state', 'selectize-dropdown'],
    ],
    'layout' => [
        'path' => '/client/custom/css/safehouse-aurora/safehouse-aurora-layout.css',
        'needles' => ['#navbar', 'list-container', '--navbar-muted-icon-color'],
    ],
];

foreach ($cssAssets as $label => $asset) {
    $url = $asset['path'] . '?r=' . rawurlencode($appTimestamp);
    $response = $client->get($url);
    $status = $response->getStatusCode();
    $body = (string) $response->getBody();

    $ok("$label HTTP 200 (appTimestamp)", $status === 200, "url=$url");

    foreach ($asset['needles'] as $needle) {
        $ok("$label contains $needle", str_contains($body, $needle));
    }
}

// Theme-leak guard: every top-level selector in the globally-appended
// (cssList) Safehouse stylesheets must be scoped under data-theme-name so it
// never bleeds into native Espo themes. Regression guard for the Aurora-leak
// bug (native themes rendered flat/monochrome because unscoped body rules +
// aurora-only CSS vars applied on every theme).
$scopedFiles = [
    'enum-colors' => __DIR__ . '/../client/custom/css/safehouse-aurora/safehouse-aurora-enum-colors.css',
    'layout' => __DIR__ . '/../client/custom/css/safehouse-aurora/safehouse-aurora-layout.css',
];

$findUnscopedTopLevelSelectors = static function (string $css): array {
    $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? '';
    $bad = [];
    $depth = 0;
    $buf = '';
    $len = strlen($css);
    for ($i = 0; $i < $len; $i++) {
        $c = $css[$i];
        if ($c === '{') {
            $sel = trim($buf);
            $buf = '';
            if ($depth === 0 && $sel !== '' && $sel[0] !== '@') {
                if (!str_contains($sel, 'data-theme-name')) {
                    $bad[] = substr($sel, 0, 90);
                }
            }
            $depth++;
        } elseif ($c === '}') {
            $depth--;
            $buf = '';
        } else {
            $buf .= $c;
        }
    }
    return $bad;
};

foreach ($scopedFiles as $label => $path) {
    if (!is_file($path)) {
        $ok("$label file present for leak guard", false, $path);
        continue;
    }
    $unscoped = $findUnscopedTopLevelSelectors((string) file_get_contents($path));
    $ok(
        "$label has no unscoped top-level selectors",
        $unscoped === [],
        $unscoped === [] ? '' : ('leaks: ' . implode(' | ', array_slice($unscoped, 0, 5)))
    );
}

// Main HTML must reference theme + cssList with cache-bust query (?r=).
$htmlResponse = $client->get('/');
$html = (string) $htmlResponse->getBody();
$ok('main HTML HTTP 200', $htmlResponse->getStatusCode() === 200);

$themeHrefOk = (bool) preg_match(
    '#id=[\'"]main-stylesheet[\'"][^>]*href="[^"]+safehouse-aurora[^"]+\?r=\d+"#',
    $html
) || (bool) preg_match(
    '#href="([^"]+safehouse-aurora[^"]+\?r=\d+)"[^>]*id=[\'"]main-stylesheet[\'"]#',
    $html
);
$activeTheme = (string) ($config->get('theme') ?? '');
$isSafehouseTheme = str_starts_with($activeTheme, 'SafehouseAurora');

if ($isSafehouseTheme) {
    $ok('main HTML has cache-busted theme stylesheet', $themeHrefOk);
} else {
    $ok(
        'main HTML has cache-busted theme stylesheet',
        true,
        'skipped (active theme=' . ($activeTheme !== '' ? $activeTheme : 'default') . ')'
    );
}

$layoutHrefOk = (bool) preg_match('#safehouse-aurora-layout\.css\?r=\d+#', $html);
$ok('main HTML links layout.css with ?r=', $layoutHrefOk);

if ($fail > 0) {
    fwrite(STDERR, "\n$fail check(s) failed.\n");
    exit(1);
}

echo "\nAll theme asset checks passed.\n";
