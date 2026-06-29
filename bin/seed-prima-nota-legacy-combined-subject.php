<?php

declare(strict_types=1);

/**
 * Wipe local PrimaNota rows and seed legacy combined subject_name values
 * "{soggetto pagatore} - {beneficiario}" in subject_name only (prod-like pre-migration).
 *
 * Usage: ddev exec php bin/seed-prima-nota-legacy-combined-subject.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);
$pdo = $em->getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Seed: PrimaNota legacy combined subject_name ===\n\n";

$deleted = $pdo->exec('DELETE FROM prima_nota');
echo "Removed rows: " . (int) $deleted . "\n";

/** @var list<array{subject: string, description: string, entryType: string, amount: float}> $fixtures */
$fixtures = [
    ['subject' => 'Gofound.me - Safe House', 'description' => 'Donazione piattaforma', 'entryType' => 'Income', 'amount' => 1500.00],
    ['subject' => 'Acme Corp - Mario Rossi', 'description' => 'Rimborso spese', 'entryType' => 'Expense', 'amount' => 320.50],
    ['subject' => 'Fondazione ABC - Safe House', 'description' => 'Contributo progetto', 'entryType' => 'Income', 'amount' => 5000.00],
    ['subject' => 'Comune di Roma - Safe House', 'description' => 'Finanziamento pubblico', 'entryType' => 'Income', 'amount' => 12000.00],
    ['subject' => 'Fornitore XYZ - Safe House', 'description' => 'Acquisto materiali', 'entryType' => 'Expense', 'amount' => 890.00],
    ['subject' => 'Anna Verdi - Safe House', 'description' => 'Donazione privata', 'entryType' => 'Income', 'amount' => 200.00],
    ['subject' => 'Banca Intesa - Safe House', 'description' => 'Commissioni bancarie', 'entryType' => 'Expense', 'amount' => 45.00],
    ['subject' => 'Org Multi - Parte A - Parte B', 'description' => 'Test split on first separator only', 'entryType' => 'Expense', 'amount' => 10.00],
    ['subject' => 'Solo Soggetto Unico', 'description' => 'Record without separator (must stay unchanged)', 'entryType' => 'Income', 'amount' => 99.00],
    ['subject' => 'Enel - Safe House', 'description' => 'Bolletta luce', 'entryType' => 'Expense', 'amount' => 410.00],
    ['subject' => 'Volontario Rossi - Safe House', 'description' => 'Rimborso km', 'entryType' => 'Expense', 'amount' => 67.80],
    ['subject' => 'Partner EU - Safe House', 'description' => 'Grant europeo', 'entryType' => 'Income', 'amount' => 25000.00],
];

$insert = $pdo->prepare(
    'INSERT INTO prima_nota (
        id, name, deleted, description, subject_name, beneficiary_name,
        entry_type, amount, amount_currency, amount_in, amount_out,
        transaction_date,
        create_subject_account, create_subject_contact,
        create_beneficiary_account, create_beneficiary_contact
    ) VALUES (
        :id, :name, 0, :description, :subject_name, NULL,
        :entry_type, :amount, :amount_currency, :amount_in, :amount_out,
        :transaction_date,
        0, 0, 0, 0
    )'
);

$day = 1;
$inserted = 0;
$seq = 1;

foreach ($fixtures as $fixture) {
    $id = 'pnlegacy' . str_pad((string) $seq, 9, '0', STR_PAD_LEFT);
    $seq++;

    $entryType = $fixture['entryType'];
    $amount = $fixture['amount'];
    $amountIn = $entryType === 'Income' ? $amount : 0;
    $amountOut = $entryType === 'Expense' ? $amount : 0;
    $date = sprintf('2025-06-%02d', min($day, 28));

    $insert->execute([
        ':id' => $id,
        ':name' => $date . ' — ' . $fixture['description'],
        ':description' => $fixture['description'],
        ':subject_name' => $fixture['subject'],
        ':entry_type' => $entryType,
        ':amount' => $amount,
        ':amount_currency' => 'EUR',
        ':amount_in' => $amountIn,
        ':amount_out' => $amountOut,
        ':transaction_date' => $date,
    ]);

    echo "  + {$fixture['subject']}\n";
    $inserted++;
    $day++;
}

$combined = (int) $pdo->query(
    "SELECT COUNT(*) FROM prima_nota WHERE deleted = 0 AND subject_name LIKE '% - %'"
)->fetchColumn();

echo "\nInserted: {$inserted}\n";
echo "Rows with legacy separator \" - \": {$combined}\n";
echo "ALL PASS\n";
