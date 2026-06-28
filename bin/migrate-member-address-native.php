<?php
/**
 * One-shot migration: Member split address columns → native Espo address field.
 *
 * Copies legacy columns on `member`:
 *   residence_address → address_street
 *   city              → address_city
 *   province          → address_state (uppercased)
 *   (default)         → address_country = Italy when empty
 *
 * Idempotent: only rows with empty address_street and non-empty legacy data.
 *
 * Usage (after deploy + rebuild so address_* columns exist):
 *   ddev exec php bin/migrate-member-address-native.php
 *
 * Production:
 *   php bin/migrate-member-address-native.php
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

$table = 'member';

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
    );
    $stmt->execute([':t' => $table, ':c' => $column]);

    return (bool) $stmt->fetchColumn();
};

echo "=== Migration: Member → native address field ===\n\n";

$required = ['address_street', 'address_city', 'address_state', 'address_postal_code', 'address_country'];
foreach ($required as $col) {
    if (!$columnExists($pdo, $table, $col)) {
        fwrite(STDERR, "FAIL: column `$col` missing on `$table`. Run Admin → Repair → Rebuild first.\n");
        exit(1);
    }
}

$hasLegacy = $columnExists($pdo, $table, 'residence_address')
    || $columnExists($pdo, $table, 'city')
    || $columnExists($pdo, $table, 'province');

if (!$hasLegacy) {
    echo "Legacy columns already absent — nothing to migrate (OK).\n";
    exit(0);
}

$legacyStreet = $columnExists($pdo, $table, 'residence_address') ? 'residence_address' : null;
$legacyCity = $columnExists($pdo, $table, 'city') ? 'city' : null;
$legacyProvince = $columnExists($pdo, $table, 'province') ? 'province' : null;

$setParts = [];
if ($legacyStreet) {
    $setParts[] = 'address_street = TRIM(`' . $legacyStreet . '`)';
}
if ($legacyCity) {
    $setParts[] = 'address_city = TRIM(`' . $legacyCity . '`)';
}
if ($legacyProvince) {
    $setParts[] = 'address_state = UPPER(TRIM(`' . $legacyProvince . '`))';
}
$setParts[] = "address_country = CASE WHEN address_country IS NULL OR TRIM(address_country) = '' THEN 'Italy' ELSE address_country END";

$whereParts = ['deleted = 0', "(address_street IS NULL OR TRIM(address_street) = '')"];
$legacyOr = [];
if ($legacyStreet) {
    $legacyOr[] = "(`$legacyStreet` IS NOT NULL AND TRIM(`$legacyStreet`) <> '')";
}
if ($legacyCity) {
    $legacyOr[] = "(`$legacyCity` IS NOT NULL AND TRIM(`$legacyCity`) <> '')";
}
if ($legacyProvince) {
    $legacyOr[] = "(`$legacyProvince` IS NOT NULL AND TRIM(`$legacyProvince`) <> '')";
}
if ($legacyOr) {
    $whereParts[] = '(' . implode(' OR ', $legacyOr) . ')';
}

$sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $setParts) . ' WHERE ' . implode(' AND ', $whereParts);
$updated = $pdo->exec($sql);

echo "Rows updated: " . (int) $updated . "\n";

$remaining = (int) $pdo->query(
    "SELECT COUNT(*) FROM `$table` WHERE deleted = 0 "
    . "AND (address_street IS NULL OR TRIM(address_street) = '') "
    . ($legacyStreet ? "AND (`$legacyStreet` IS NOT NULL AND TRIM(`$legacyStreet`) <> '')" : '')
)->fetchColumn();

if ($remaining > 0) {
    echo "WARN: $remaining rows still have legacy street but empty address_street.\n";
    exit(1);
}

echo "ALL PASS\n";
