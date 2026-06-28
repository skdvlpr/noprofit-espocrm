<?php
/**
 * Smoke test for migrate-member-address-native.php
 *
 * Seeds legacy member columns, runs migration, asserts address_* populated.
 *
 * Usage: ddev exec php bin/smoke-migrate-member-address.php
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

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    echo '  [' . ($pass ? 'PASS' : 'FAIL') . '] ' . $name . ($detail !== '' ? " — $detail" : '') . "\n";
};

$columnExists = static function (PDO $pdo, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
    );
    $stmt->execute([':t' => 'member', ':c' => $column]);

    return (bool) $stmt->fetchColumn();
};

echo "Preconditions\n";
$ok('address_street column exists', $columnExists($pdo, 'address_street'));
$ok('legacy residence_address column exists', $columnExists($pdo, 'residence_address'));

if ($fail > 0) {
    echo "\nABORT: schema preconditions failed\n";
    exit(1);
}

$id = 'smoke' . bin2hex(random_bytes(6));
$pdo->exec(
    "INSERT INTO member (id, first_name, last_name, name, status, tax_code, "
    . "residence_address, city, province, "
    . "address_street, address_city, address_state, address_country, "
    . "deleted, created_at, modified_at) VALUES ("
    . "'$id', 'Migrate', 'SmokeTest', 'Migrate SmokeTest', 'Active', 'SMKMGR0000000000', "
    . "'Via Marco Mersiero 3', 'Palestrina', 'rm', "
    . "NULL, NULL, NULL, NULL, "
    . "0, NOW(), NOW())"
);

echo "\nBefore migration\n";
$row = $pdo->query("SELECT residence_address, city, province, address_street, address_city, address_state, address_country FROM member WHERE id = '$id'")->fetch(PDO::FETCH_ASSOC);
$ok('legacy street seeded', ($row['residence_address'] ?? '') === 'Via Marco Mersiero 3');
$ok('address_street empty before', ($row['address_street'] ?? '') === '');

passthru('php ' . escapeshellarg(__DIR__ . '/migrate-member-address-native.php'), $exitCode);
$ok('migration script exit 0', $exitCode === 0);

echo "\nAfter migration\n";
$row = $pdo->query("SELECT address_street, address_city, address_state, address_country FROM member WHERE id = '$id'")->fetch(PDO::FETCH_ASSOC);
$ok('address_street copied', ($row['address_street'] ?? '') === 'Via Marco Mersiero 3');
$ok('address_city copied', ($row['address_city'] ?? '') === 'Palestrina');
$ok('address_state uppercased', ($row['address_state'] ?? '') === 'RM');
$ok('address_country defaulted', ($row['address_country'] ?? '') === 'Italy');

echo "\nIdempotency (second run)\n";
passthru('php ' . escapeshellarg(__DIR__ . '/migrate-member-address-native.php'), $exitCode2);
$ok('second run exit 0', $exitCode2 === 0);
$row2 = $pdo->query("SELECT address_street, address_city, address_state, address_country FROM member WHERE id = '$id'")->fetch(PDO::FETCH_ASSOC);
$ok('data unchanged after second run', $row2 === $row);

$pdo->exec("DELETE FROM member WHERE id = '$id'");

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
