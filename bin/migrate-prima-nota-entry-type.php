<?php
/**
 * One-shot migration: populate PrimaNota entryType + amount from legacy amountIn/amountOut.
 *
 * Idempotent: only rows with empty entryType and non-zero legacy amounts.
 *
 * Usage (after rebuild):
 *   ddev exec php bin/migrate-prima-nota-entry-type.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);
$pdo = $em->getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Migration: PrimaNota entryType + amount ===\n\n";

$columnExists = static function (PDO $pdo, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
    );
    $stmt->execute([':t' => 'prima_nota', ':c' => $column]);

    return (bool) $stmt->fetchColumn();
};

foreach (['entry_type', 'amount', 'amount_in', 'amount_out'] as $col) {
    if (!$columnExists($pdo, $col)) {
        fwrite(STDERR, "FAIL: column `$col` missing on `prima_nota`. Run rebuild first.\n");
        exit(1);
    }
}

$sql = <<<'SQL'
UPDATE prima_nota
SET
    entry_type = CASE
        WHEN amount_in > 0 AND (amount_out IS NULL OR amount_out <= 0) THEN 'Income'
        WHEN amount_out > 0 AND (amount_in IS NULL OR amount_in <= 0) THEN 'Expense'
        ELSE entry_type
    END,
    amount = CASE
        WHEN amount_in > 0 AND (amount_out IS NULL OR amount_out <= 0) THEN amount_in
        WHEN amount_out > 0 AND (amount_in IS NULL OR amount_in <= 0) THEN amount_out
        ELSE amount
    END
WHERE deleted = 0
  AND (entry_type IS NULL OR TRIM(entry_type) = '')
  AND (
    (amount_in IS NOT NULL AND amount_in > 0)
    OR (amount_out IS NOT NULL AND amount_out > 0)
  )
SQL;

$updated = $pdo->exec($sql);

echo 'Rows updated: ' . (int) $updated . "\n";
echo "ALL PASS\n";
