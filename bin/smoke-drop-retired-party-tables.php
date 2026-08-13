<?php

declare(strict_types=1);

require __DIR__ . '/lib/refuse-production.php';

/**
 * Smoke: DropRetiredPartyTables must not DROP leftover party tables that still
 * have live rows. Production rebuild previously destroyed unmigrated
 * VolunteerEmployee / Member data because the Contact STI migrator is
 * DDEV-only and excluded from prod rsync.
 *
 * Usage:
 *   ddev exec php bin/smoke-drop-retired-party-tables.php
 */

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $m = $pass ? 'PASS' : 'FAIL';
    echo "  [$m] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$srcPath = __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Core/Rebuild/DropRetiredPartyTables.php';
$src = is_file($srcPath) ? (string) file_get_contents($srcPath) : '';

$ok('DropRetiredPartyTables.php readable', $src !== '');
$ok(
    'exposes shouldDropRetiredTable fail-closed helper',
    str_contains($src, 'function shouldDropRetiredTable')
);
$ok(
    'counts live rows before DROP',
    str_contains($src, 'liveRowCount(') && str_contains($src, 'shouldDropRetiredTable(')
);
$ok(
    'refuses DROP when count is unknown',
    str_contains($src, 'liveRowCount=' ) && str_contains($src, 'refusing to DROP')
);
$dropPos = strpos($src, 'DROP TABLE IF EXISTS');
$guardPos = strpos($src, 'shouldDropRetiredTable');
$ok(
    'live-row guard precedes DROP TABLE',
    $guardPos !== false && $dropPos !== false && $guardPos < $dropPos
);

$rebuildPath = __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/rebuild.json';
$rebuild = json_decode((string) file_get_contents($rebuildPath), true);
$actionList = is_array($rebuild) ? ($rebuild['actionClassNameList'] ?? []) : [];
$ok(
    'rebuild.json still registers DropRetiredPartyTables',
    in_array(
        'Espo\\Modules\\NonprofitEspocrm\\Core\\Rebuild\\DropRetiredPartyTables',
        $actionList,
        true
    )
);

$migratePath = __DIR__ . '/migrate-ve-member-to-contact.php';
$migrate = is_file($migratePath) ? (string) file_get_contents($migratePath) : '';
$ok(
    'Contact STI migrator still refuses production',
    str_contains($migrate, 'refuse-production.php')
);

$bootstrap = __DIR__ . '/../bootstrap.php';

if (is_file($bootstrap)) {
    include $bootstrap;

    $app = new Espo\Core\Application();
    $app->setupSystemUser();

    $ok(
        'skip missing table',
        Espo\Modules\NonprofitEspocrm\Core\Rebuild\DropRetiredPartyTables::shouldDropRetiredTable(false, 0) === false
    );
    $ok(
        'skip live rows',
        Espo\Modules\NonprofitEspocrm\Core\Rebuild\DropRetiredPartyTables::shouldDropRetiredTable(true, 5) === false
    );
    $ok(
        'skip unknown count (fail closed)',
        Espo\Modules\NonprofitEspocrm\Core\Rebuild\DropRetiredPartyTables::shouldDropRetiredTable(true, null) === false
    );
    $ok(
        'drop only empty existing table',
        Espo\Modules\NonprofitEspocrm\Core\Rebuild\DropRetiredPartyTables::shouldDropRetiredTable(true, 0) === true
    );
}

if ($fail > 0) {
    fwrite(STDERR, "FAIL: {$fail} assertion(s) failed.\n");
    exit(1);
}

echo "OK: DropRetiredPartyTables live-row guard\n";
exit(0);
