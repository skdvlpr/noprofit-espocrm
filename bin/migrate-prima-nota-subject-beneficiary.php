<?php
/**
 * One-shot migration: split legacy combined subject_name
 * "{soggetto pagatore} - {beneficiario}" → subject_name + beneficiary_name.
 *
 * Separator (exact): space + hyphen + space → " - "
 *
 * Idempotent by default: only rows where beneficiary_name is empty.
 * Use --force to re-split any row whose subject_name still contains " - ".
 *
 * Usage (after rebuild):
 *   ddev exec php bin/migrate-prima-nota-subject-beneficiary.php --dry-run
 *   ddev exec php bin/migrate-prima-nota-subject-beneficiary.php
 *   ddev exec php bin/migrate-prima-nota-subject-beneficiary.php --force --dry-run
 *
 * Production:
 *   php bin/migrate-prima-nota-subject-beneficiary.php --dry-run
 *   php bin/migrate-prima-nota-subject-beneficiary.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv, true);
$force = in_array('--force', $argv, true);

/** Canonical legacy separator: " - " (ASCII space, hyphen-minus, space). */
const LEGACY_SEPARATOR = ' - ';

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);
$pdo = $em->getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Migration: PrimaNota subject_name / beneficiary_name split ===\n";
echo 'Separator: "' . LEGACY_SEPARATOR . "\" (space-hyphen-space)\n";
echo $dryRun ? "Mode: DRY RUN (no writes)\n" : "Mode: APPLY\n";
echo $force ? "Force: YES (re-split rows that still contain separator)\n\n" : "Force: NO\n\n";

$columnExists = static function (PDO $pdo, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
    );
    $stmt->execute([':t' => 'prima_nota', ':c' => $column]);

    return (bool) $stmt->fetchColumn();
};

foreach (['subject_name', 'beneficiary_name'] as $col) {
    if (!$columnExists($pdo, $col)) {
        fwrite(STDERR, "FAIL: column `$col` missing on `prima_nota`. Run rebuild first.\n");
        exit(1);
    }
}

/**
 * Normalize only non-breaking spaces; keep separator semantics exact otherwise.
 */
$normalizeLegacyValue = static function (string $value): string {
    $value = str_replace("\xC2\xA0", ' ', $value);

    return trim(preg_replace('/[ \t]+/u', ' ', $value) ?? $value);
};

/**
 * @return array{0: string, 1: string}|null
 */
$splitCombinedSubject = static function (string $value) use ($normalizeLegacyValue): ?array {
    $value = $normalizeLegacyValue($value);

    if ($value === '') {
        return null;
    }

    $pos = strpos($value, LEGACY_SEPARATOR);

    if ($pos === false) {
        return null;
    }

    $subject = trim(substr($value, 0, $pos));
    $beneficiary = trim(substr($value, $pos + strlen(LEGACY_SEPARATOR)));

    if ($subject === '' || $beneficiary === '') {
        return null;
    }

    return [$subject, $beneficiary];
};

$whereBeneficiaryEmpty = '(beneficiary_name IS NULL OR TRIM(beneficiary_name) = \'\')';
$selectSql = $force
    ? <<<SQL
SELECT id, subject_name, beneficiary_name
FROM prima_nota
WHERE deleted = 0
  AND subject_name IS NOT NULL
  AND TRIM(subject_name) != ''
  AND subject_name LIKE '% - %'
ORDER BY transaction_date ASC, id ASC
SQL
    : <<<SQL
SELECT id, subject_name, beneficiary_name
FROM prima_nota
WHERE deleted = 0
  AND subject_name IS NOT NULL
  AND TRIM(subject_name) != ''
  AND subject_name LIKE '% - %'
  AND {$whereBeneficiaryEmpty}
ORDER BY transaction_date ASC, id ASC
SQL;

$rows = $pdo->query($selectSql)->fetchAll(PDO::FETCH_ASSOC);
$eligible = count($rows);

echo "Eligible rows: {$eligible}\n";

if ($eligible === 0) {
    $stillCombined = (int) $pdo->query(
        'SELECT COUNT(*) FROM prima_nota WHERE deleted = 0'
        . ' AND subject_name LIKE \'% - %\''
    )->fetchColumn();

    if ($stillCombined > 0 && !$force) {
        echo "Hint: {$stillCombined} row(s) still have \" - \" in subject_name but beneficiary_name is set.\n";
        echo "      Re-run with --force --dry-run if those beneficiary values look wrong.\n";
    }

    echo "Nothing to migrate.\n";
    echo "ALL PASS\n";
    exit(0);
}

$previewLimit = min(5, $eligible);
echo "\nPreview (first {$previewLimit}):\n";

$previewSplits = 0;

for ($i = 0; $i < $previewLimit; $i++) {
    $row = $rows[$i];
    $split = $splitCombinedSubject((string) $row['subject_name']);

    if ($split === null) {
        echo "  [SKIP] {$row['id']}: invalid split for \"{$row['subject_name']}\"\n";
        continue;
    }

    [$subject, $beneficiary] = $split;
    $previewSplits++;
    echo "  {$row['id']}: \"{$row['subject_name']}\"\n";
    echo "    → subject_name=\"{$subject}\"\n";
    echo "    → beneficiary_name=\"{$beneficiary}\"\n";
}

if ($previewSplits === 0) {
    fwrite(STDERR, "FAIL: eligible rows found but none split with separator \" - \".\n");
    fwrite(STDERR, "Check for non-standard dashes/spaces in subject_name.\n");
    exit(1);
}

if ($dryRun) {
    echo "\nDry run complete. Re-run without --dry-run to apply.\n";
    echo "ALL PASS\n";
    exit(0);
}

$updateStmt = $pdo->prepare(
    'UPDATE prima_nota SET subject_name = :subject_name, beneficiary_name = :beneficiary_name WHERE id = :id'
);

$verifyStmt = $pdo->prepare(
    'SELECT subject_name, beneficiary_name FROM prima_nota WHERE id = :id AND deleted = 0'
);

$updated = 0;
$skipped = 0;
$verifyFailed = 0;

$pdo->beginTransaction();

try {
    foreach ($rows as $row) {
        $split = $splitCombinedSubject((string) $row['subject_name']);

        if ($split === null) {
            $skipped++;
            continue;
        }

        [$subject, $beneficiary] = $split;

        $updateStmt->execute([
            ':subject_name' => $subject,
            ':beneficiary_name' => $beneficiary,
            ':id' => $row['id'],
        ]);

        $verifyStmt->execute([':id' => $row['id']]);
        $saved = $verifyStmt->fetch(PDO::FETCH_ASSOC);

        if (
            !is_array($saved)
            || trim((string) ($saved['subject_name'] ?? '')) !== $subject
            || trim((string) ($saved['beneficiary_name'] ?? '')) !== $beneficiary
        ) {
            $verifyFailed++;
            fwrite(STDERR, "VERIFY FAIL {$row['id']}: expected subject=\"{$subject}\", beneficiary=\"{$beneficiary}\"; "
                . 'got subject="' . ($saved['subject_name'] ?? '') . '", beneficiary="' . ($saved['beneficiary_name'] ?? '') . "\"\n");
            continue;
        }

        $updated++;
    }

    if ($verifyFailed > 0) {
        $pdo->rollBack();
        fwrite(STDERR, "FAIL: {$verifyFailed} row(s) failed post-update verification. Rolled back.\n");
        exit(1);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "\nRows updated: {$updated}\n";

if ($skipped > 0) {
    echo "Rows skipped (invalid split): {$skipped}\n";
}

$remaining = (int) $pdo->query(
    'SELECT COUNT(*) FROM prima_nota WHERE deleted = 0'
    . ' AND subject_name LIKE \'% - %\''
    . ' AND (beneficiary_name IS NULL OR TRIM(beneficiary_name) = \'\')'
)->fetchColumn();

if ($remaining > 0) {
    fwrite(STDERR, "FAIL: {$remaining} row(s) still have combined subject_name with empty beneficiary_name.\n");
    exit(1);
}

echo "ALL PASS\n";
