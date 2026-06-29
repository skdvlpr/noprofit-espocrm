<?php

declare(strict_types=1);

/**
 * End-to-end local simulation of prod deploy + migration:
 * 1) restore legacy single-field metadata (like prod today)
 * 2) rebuild + seed combined subject_name rows
 * 3) apply split-field metadata (branch state)
 * 4) rebuild + run migration + verify
 *
 * Usage: ddev exec php bin/test-prima-nota-prod-migration-flow.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\File\Manager as FileManager;
use Espo\ORM\EntityManager;

const REPO_ROOT = __DIR__ . '/..';

/** @var list<string> $trackedFiles */
$trackedFiles = [
    'custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/PrimaNota.json',
    'custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/PrimaNota.json',
    'custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/PrimaNota/detail.json',
    'custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/PrimaNota/detailSmall.json',
    'custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/PrimaNota/list.json',
    'custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/PrimaNota/filters.json',
    'custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/PrimaNota.json',
    'custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/en_US/PrimaNota.json',
    'custom/Espo/Modules/NonprofitEspocrm/Hooks/PrimaNota/SubjectParty.php',
];

$backupDir = sys_get_temp_dir() . '/prima-nota-split-backup-' . getmypid();
mkdir($backupDir, 0775, true);

$runPhp = static function (string $script, array $extraArgs = []): void {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(REPO_ROOT . '/' . $script);
    foreach ($extraArgs as $arg) {
        $cmd .= ' ' . escapeshellarg($arg);
    }
    echo "\n>>> $cmd\n";
    passthru($cmd, $code);
    if ($code !== 0) {
        throw new RuntimeException("Command failed ($code): $cmd");
    }
};

$gitShow = static function (string $relPath): string {
    $cmd = 'git -C ' . escapeshellarg(REPO_ROOT) . ' show main:' . escapeshellarg($relPath);
    $content = shell_exec($cmd);
    if (!is_string($content) || $content === '') {
        throw new RuntimeException("git show failed for $relPath");
    }

    return $content;
};

$restoreFromMain = static function (array $files) use ($gitShow): void {
    foreach ($files as $relPath) {
        $target = REPO_ROOT . '/' . $relPath;
        file_put_contents($target, $gitShow($relPath));
        echo "  legacy ← main: $relPath\n";
    }
};

$restoreFromBackup = static function (array $files, string $dir): void {
    foreach ($files as $relPath) {
        $source = $dir . '/' . basename(dirname($relPath)) . '-' . basename($relPath);
        // flat backup names
        $flat = $dir . '/' . str_replace('/', '__', $relPath);
        if (!is_file($flat)) {
            throw new RuntimeException("Backup missing: $flat");
        }
        file_put_contents(REPO_ROOT . '/' . $relPath, file_get_contents($flat));
        echo "  split restored: $relPath\n";
    }
};

echo "=== PrimaNota prod migration flow (local simulation) ===\n";

try {
    echo "\n[1/6] Backup current split-field files\n";
    foreach ($trackedFiles as $relPath) {
        $flat = $backupDir . '/' . str_replace('/', '__', $relPath);
        copy(REPO_ROOT . '/' . $relPath, $flat);
    }

    echo "\n[2/6] Restore legacy single-field metadata (prod today)\n";
    $restoreFromMain($trackedFiles);
    $runPhp('clear_cache.php');
    $runPhp('rebuild.php');

    echo "\n[3/6] Seed legacy combined subject_name rows\n";
    $runPhp('bin/seed-prima-nota-legacy-combined-subject.php');

    echo "\n[4/6] Apply split-field metadata (post-deploy branch state)\n";
    $restoreFromBackup($trackedFiles, $backupDir);
    $runPhp('clear_cache.php');
    $runPhp('rebuild.php');

    echo "\n[5/6] Run migration script\n";
    $runPhp('bin/migrate-prima-nota-subject-beneficiary.php', ['--dry-run']);
    $runPhp('bin/migrate-prima-nota-subject-beneficiary.php');

    echo "\n[6/6] Verify results\n";
    $app = new Application();
    $app->setupSystemUser();
    /** @var EntityManager $em */
    $em = $app->getContainer()->getByClass(EntityManager::class);
    $pdo = $em->getPDO();

    $fail = 0;
    $ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
        if (!$pass) {
            $fail++;
        }
        echo '  [' . ($pass ? 'PASS' : 'FAIL') . '] ' . $name . ($detail !== '' ? " — $detail" : '') . "\n";
    };

    $rows = $pdo->query(
        'SELECT subject_name, beneficiary_name FROM prima_nota WHERE deleted = 0 ORDER BY transaction_date ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $ok('seed rows present', count($rows) === 12, 'count=' . count($rows));

    $expectations = [
        'Gofound.me' => 'Safe House',
        'Acme Corp' => 'Mario Rossi',
        'Fondazione ABC' => 'Safe House',
        'Comune di Roma' => 'Safe House',
        'Fornitore XYZ' => 'Safe House',
        'Anna Verdi' => 'Safe House',
        'Banca Intesa' => 'Safe House',
        'Org Multi' => 'Parte A - Parte B',
        'Enel' => 'Safe House',
        'Volontario Rossi' => 'Safe House',
        'Partner EU' => 'Safe House',
    ];

    foreach ($expectations as $subject => $beneficiary) {
        $match = null;
        foreach ($rows as $row) {
            if (($row['subject_name'] ?? '') === $subject) {
                $match = $row;
                break;
            }
        }
        $ok(
            "split \"$subject\" / \"$beneficiary\"",
            is_array($match) && ($match['beneficiary_name'] ?? '') === $beneficiary,
            is_array($match)
                ? 'beneficiary=' . ($match['beneficiary_name'] ?? '')
                : 'row not found'
        );
    }

    $single = null;
    foreach ($rows as $row) {
        if (($row['subject_name'] ?? '') === 'Solo Soggetto Unico') {
            $single = $row;
            break;
        }
    }
    $ok(
        'single-name row unchanged',
        is_array($single)
            && ($single['beneficiary_name'] ?? null) === null,
        'beneficiary=' . var_export($single['beneficiary_name'] ?? null, true)
    );

    $unsplit = (int) $pdo->query(
        "SELECT COUNT(*) FROM prima_nota WHERE deleted = 0"
        . " AND subject_name LIKE '% - %'"
        . " AND (beneficiary_name IS NULL OR TRIM(beneficiary_name) = '')"
    )->fetchColumn();
    $ok('no unsplit combined rows remain', $unsplit === 0, "remaining=$unsplit");

    echo $fail === 0 ? "\nALL PASS — safe to deploy + migrate on prod\n" : "\nFAILED: $fail\n";
    exit($fail === 0 ? 0 : 1);
} finally {
    array_map('unlink', glob($backupDir . '/*') ?: []);
    @rmdir($backupDir);
}
