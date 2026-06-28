<?php
/**
 * Migration: PrimaNota classification field split.
 *
 * Renames legacy column model_d_classification → internal_classification
 * (old Donation/Grant/… values). Rebuild adds fresh model_d_classification for A–E.
 *
 * Usage: ddev exec php bin/migrate-prima-nota-classification-fields.php
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

$columnExists = static function (PDO $pdo, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
    );
    $stmt->execute([':t' => 'prima_nota', ':c' => $column]);

    return (bool) $stmt->fetchColumn();
};

echo "=== Migration: PrimaNota classification fields ===\n\n";

if (!$columnExists($pdo, 'internal_classification') && $columnExists($pdo, 'model_d_classification')) {
    $pdo->exec('ALTER TABLE prima_nota CHANGE model_d_classification internal_classification VARCHAR(255) DEFAULT NULL');
    echo "Renamed model_d_classification → internal_classification\n";
} elseif ($columnExists($pdo, 'internal_classification')) {
    echo "internal_classification already present (OK)\n";
} else {
    echo "No legacy model_d_classification column — skip rename (OK)\n";
}

echo "ALL PASS\n";
