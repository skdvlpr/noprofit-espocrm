<?php
/**
 * Smoke: Safehouse Aurora themes module is the single source of the two themes,
 * resolves in metadata, and ships in BOTH the standalone themes ZIP and the
 * bundled SafehouseCrm ZIP.
 *
 * Usage:
 *   ddev exec php bin/smoke-safehouse-aurora-themes.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Metadata;

$root = dirname(__DIR__);
$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    echo '  [' . ($pass ? 'PASS' : 'FAIL') . "] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$moduleDir = $root . '/custom/Espo/Modules/SafehouseAuroraThemes';

// 1. Module structure.
$required = [
    'manifest.json',
    'Resources/module.json',
    'Resources/metadata/themes/SafehouseAurora.json',
    'Resources/metadata/themes/SafehouseAuroraLight.json',
    'Resources/metadata/app/client.json',
    'Tools/Installer.php',
    'AfterInstall.php',
];
foreach ($required as $rel) {
    $ok("module file $rel", is_file("$moduleDir/$rel"));
}

// 2. Single source: SafehouseCrm must NOT define the themes anymore.
$crmThemes = $root . '/custom/Espo/Modules/SafehouseCrm/Resources/metadata/themes';
$ok(
    'SafehouseCrm no longer defines SafehouseAurora theme (single source)',
    !is_file("$crmThemes/SafehouseAurora.json") && !is_file("$crmThemes/SafehouseAuroraLight.json")
);

// 3. Themes resolve in merged metadata.
$app = new Application();
$app->setupSystemUser();
/** @var Metadata $metadata */
$metadata = $app->getContainer()->getByClass(Metadata::class);
$metadata->init(true);
foreach (['SafehouseAurora', 'SafehouseAuroraLight'] as $theme) {
    $stylesheet = $metadata->get(['themes', $theme, 'stylesheet']);
    $ok("theme $theme registered in metadata", is_string($stylesheet) && $stylesheet !== '', (string) $stylesheet);
}

// 4. Theme cssList entries still present in merged app metadata.
$cssList = $metadata->get(['app', 'client', 'cssList']) ?? [];
foreach ([
    'client/custom/css/safehouse-aurora/safehouse-aurora-enum-colors.css',
    'client/custom/css/safehouse-aurora/safehouse-aurora-layout.css',
] as $css) {
    $ok('merged cssList contains ' . basename($css), in_array($css, $cssList, true));
}

// 5. Build wiring: standalone build script + bundling in SafehouseCrm build.
$standalone = $root . '/bin/build-safehouse-aurora-themes.sh';
$ok('standalone build script exists', is_file($standalone));
$crmBuild = (string) @file_get_contents($root . '/bin/build.sh');
$ok('SafehouseCrm build bundles themes module', str_contains($crmBuild, 'SafehouseAuroraThemes'));
$ok('ZIP AfterInstall delegate exists', is_file($root . '/bin/packaging/SafehouseAuroraThemes-zip-AfterInstall.php'));

// 6. Build both ZIPs and assert the themes module ships in each.
$bash = trim((string) shell_exec('command -v bash')) ?: '/bin/bash';
$zipHas = static function (string $zipPath, string $needle): bool {
    if (!is_file($zipPath)) {
        return false;
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return false;
    }
    $found = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        if (str_contains((string) $zip->getNameIndex($i), $needle)) {
            $found = true;
            break;
        }
    }
    $zip->close();
    return $found;
};

$themesVersion = json_decode((string) file_get_contents("$moduleDir/manifest.json"), true)['version'] ?? '';
$crmVersion = json_decode((string) file_get_contents($root . '/custom/Espo/Modules/SafehouseCrm/manifest.json'), true)['version'] ?? '';

shell_exec(sprintf('cd %s && %s bin/build-safehouse-aurora-themes.sh 2>&1', escapeshellarg($root), escapeshellarg($bash)));
shell_exec(sprintf('cd %s && %s bin/build.sh 2>&1', escapeshellarg($root), escapeshellarg($bash)));

$themesZip = "$root/dist/safehouse-aurora-themes-v{$themesVersion}.zip";
$crmZip = "$root/dist/safehouse-crm-v{$crmVersion}.zip";

$ok('standalone ZIP built', is_file($themesZip), $themesZip);
$ok('standalone ZIP has themes module', $zipHas($themesZip, 'custom/Espo/Modules/SafehouseAuroraThemes/Resources/metadata/themes/SafehouseAurora.json'));
$ok('standalone ZIP has theme CSS', $zipHas($themesZip, 'client/custom/css/safehouse-aurora/safehouse-aurora.css'));
$ok('standalone ZIP has scripts/AfterInstall.php', $zipHas($themesZip, 'scripts/AfterInstall.php'));

$ok('SafehouseCrm ZIP built', is_file($crmZip), $crmZip);
$ok('SafehouseCrm ZIP bundles themes module', $zipHas($crmZip, 'custom/Espo/Modules/SafehouseAuroraThemes/manifest.json'));
$ok('SafehouseCrm ZIP has theme CSS', $zipHas($crmZip, 'client/custom/css/safehouse-aurora/safehouse-aurora-layout.css'));

if ($fail > 0) {
    fwrite(STDERR, "\n$fail check(s) failed.\n");
    exit(1);
}

echo "\nAll Safehouse Aurora themes package checks passed.\n";
