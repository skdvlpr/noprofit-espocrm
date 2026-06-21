<?php
/**
 * Epic 7.0-QA — runs the full Epic 7 smoke suite (Lead + Rendicontazione + reporting).
 *
 * Usage: ddev exec php bin/smoke-epic7-qa.php
 */

declare(strict_types=1);

$scripts = [
    'bin/smoke-installer.php',
    'bin/smoke-lead-restore.php',
    'bin/smoke-lead-convert.php',
    'bin/smoke-rendicontazione.php',
    'bin/smoke-export-totals.php',
    'bin/smoke-association-mealcount.php',
    'bin/smoke-mealcount-email-export.php',
];

$root = dirname(__DIR__);
$failures = 0;

foreach ($scripts as $script) {
    $path = $root . '/' . $script;
    echo "\n========== $script ==========\n";

    if (!is_readable($path)) {
        echo "  [FAIL] Script not found: $path\n";
        $failures++;
        continue;
    }

    passthru('php ' . escapeshellarg($path), $exitCode);

    if ($exitCode !== 0) {
        $failures++;
    }
}

echo "\n========== Epic 7.0-QA summary ==========\n";
echo $failures === 0 ? "ALL PASS\n" : "FAILED ($failures script(s))\n";
exit($failures > 0 ? 1 : 0);
