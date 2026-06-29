<?php

declare(strict_types=1);

/**
 * Smoke: legacy "soggetto pagatore - beneficiario" → split columns via migration script.
 *
 * Usage: ddev exec php bin/smoke-prima-nota-subject-beneficiary-migrate.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ORM\EntityManager;

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

$testIds = ['migrate0000000001', 'migrate0000000002', 'migrate0000000003'];

foreach ($testIds as $id) {
    $pdo->exec("DELETE FROM prima_nota WHERE id = " . $pdo->quote($id));
}

$insert = $pdo->prepare(
    'INSERT INTO prima_nota (id, name, deleted, description, subject_name, entry_type, amount, amount_currency,'
    . ' transaction_date, create_subject_account, create_subject_contact, create_beneficiary_account, create_beneficiary_contact)'
    . ' VALUES (:id, :name, 0, :description, :subject_name, :entry_type, :amount, :amount_currency, :transaction_date, 0, 0, 0, 0)'
);

$fixtures = [
    [
        'id' => 'migrate0000000001',
        'name' => '2026-01-01 — test',
        'description' => 'Exact prod example',
        'subject_name' => 'Gofound.me - Safe House',
        'entry_type' => 'Income',
        'amount' => 100,
        'amount_currency' => 'EUR',
        'transaction_date' => '2026-01-01',
    ],
    [
        'id' => 'migrate0000000002',
        'name' => '2026-01-02 — test',
        'description' => 'Second split',
        'subject_name' => 'Acme Corp - Mario Rossi',
        'entry_type' => 'Expense',
        'amount' => 50,
        'amount_currency' => 'EUR',
        'transaction_date' => '2026-01-02',
    ],
    [
        'id' => 'migrate0000000003',
        'name' => '2026-01-03 — test',
        'description' => 'No separator',
        'subject_name' => 'Single Name Only',
        'entry_type' => 'Income',
        'amount' => 25,
        'amount_currency' => 'EUR',
        'transaction_date' => '2026-01-03',
    ],
];

foreach ($fixtures as $row) {
    $insert->execute($row);
}

$ok('fixtures inserted', count($fixtures) === 3);

ob_start();
passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/migrate-prima-nota-subject-beneficiary.php'), $exitCode);
$output = ob_get_clean() ?: '';
echo $output;

$ok('migration script exit 0', $exitCode === 0, 'exit=' . $exitCode);

$fetch = $pdo->prepare('SELECT subject_name, beneficiary_name FROM prima_nota WHERE id = :id');

$fetch->execute([':id' => 'migrate0000000001']);
$row1 = $fetch->fetch(PDO::FETCH_ASSOC);
$ok(
    'Gofound.me - Safe House → split',
    ($row1['subject_name'] ?? '') === 'Gofound.me' && ($row1['beneficiary_name'] ?? '') === 'Safe House',
    'subject=' . ($row1['subject_name'] ?? '') . ' beneficiary=' . ($row1['beneficiary_name'] ?? '')
);

$fetch->execute([':id' => 'migrate0000000002']);
$row2 = $fetch->fetch(PDO::FETCH_ASSOC);
$ok(
    'Acme Corp - Mario Rossi → split',
    ($row2['subject_name'] ?? '') === 'Acme Corp' && ($row2['beneficiary_name'] ?? '') === 'Mario Rossi',
    'subject=' . ($row2['subject_name'] ?? '') . ' beneficiary=' . ($row2['beneficiary_name'] ?? '')
);

$fetch->execute([':id' => 'migrate0000000003']);
$row3 = $fetch->fetch(PDO::FETCH_ASSOC);
$ok(
    'single name unchanged',
    ($row3['subject_name'] ?? '') === 'Single Name Only' && ($row3['beneficiary_name'] ?? null) === null,
    'subject=' . ($row3['subject_name'] ?? '') . ' beneficiary=' . var_export($row3['beneficiary_name'] ?? null, true)
);

foreach ($testIds as $id) {
    $pdo->exec("DELETE FROM prima_nota WHERE id = " . $pdo->quote($id));
}

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
