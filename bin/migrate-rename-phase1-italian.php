<?php
/**
 * Phase 1 follow-up migration: rename Italian-named fields and enum values that
 * survived the main `bin/migrate-rename-italian.php` pass.
 *
 * What it does:
 *   1) `account.settore`              -> `account.sector`            (column rename)
 *      enum text on `account.sector`:
 *        'Terzo Settore'              -> 'ThirdSector'
 *        'Assistenti Sociali'         -> 'SocialWorkers'
 *        'Pubblico'                   -> 'Public'
 *   2) `opportunity.data_presentazione` -> `opportunity.presentation_date`
 *      enum text on `opportunity.stage` / `opportunity.last_stage`:
 *        'Prospecting'               -> 'Preparation'
 *        'Qualification'             -> 'Preparation'
 *        'Preparazione'               -> 'Preparation'
 *        'Proposta'                   -> 'Proposal'
 *        'Negoziazione'               -> 'Negotiation'
 *        'Chiuso Positivamente'       -> 'Closed Won'
 *        'Chiuso Negativamente'       -> 'Closed Lost'
 *
 * Idempotent: each step checks DB state before mutating; re-running after a
 * successful first pass is a no-op.
 *
 * Usage:
 *   ddev exec php bin/migrate-rename-phase1-italian.php
 *
 * Post-run:
 *   ddev exec php clear_cache.php
 *   ddev exec php rebuild.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);

/** @var PDO $pdo */
$pdo = $em->getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Phase 1 migration: Account.settore / Opportunity.stage / Opportunity.dataPresentazione ===\n\n";

$tableExists = static function (PDO $pdo, string $name): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :n LIMIT 1'
    );
    $stmt->execute([':n' => $name]);

    return (bool) $stmt->fetchColumn();
};

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
    );
    $stmt->execute([':t' => $table, ':c' => $column]);

    return (bool) $stmt->fetchColumn();
};

$renameColumn = static function (PDO $pdo, string $table, string $oldCol, string $newCol) use ($tableExists, $columnExists): void {
    if (!$tableExists($pdo, $table)) {
        echo "  skip rename $table.$oldCol -> $newCol (table missing)\n";
        return;
    }
    if ($columnExists($pdo, $table, $oldCol) && !$columnExists($pdo, $table, $newCol)) {
        $pdo->exec(sprintf('ALTER TABLE `%s` RENAME COLUMN `%s` TO `%s`', $table, $oldCol, $newCol));
        echo "  column renamed: $table.$oldCol -> $newCol\n";
    } else {
        echo "  skip rename $table.$oldCol -> $newCol (state already migrated)\n";
    }
};

$updateColumnText = static function (PDO $pdo, string $table, string $column, array $valueMap) use ($tableExists, $columnExists): void {
    if (!$tableExists($pdo, $table) || !$columnExists($pdo, $table, $column)) {
        echo "  skip enum update $table.$column (missing)\n";
        return;
    }
    foreach ($valueMap as $oldValue => $newValue) {
        $stmt = $pdo->prepare(sprintf('UPDATE `%s` SET `%s` = :new WHERE `%s` = :old', $table, $column, $column));
        $stmt->execute([':new' => $newValue, ':old' => $oldValue]);
        $n = $stmt->rowCount();
        if ($n > 0) {
            echo "    $table.$column: '$oldValue' -> '$newValue' ($n rows)\n";
        }
    }
};

echo "[1/5] Renaming account.settore -> account.sector...\n";
$renameColumn($pdo, 'account', 'settore', 'sector');

echo "\n[2/5] Translating account.sector enum values...\n";
$updateColumnText($pdo, 'account', 'sector', [
    'Terzo Settore'      => 'ThirdSector',
    'Assistenti Sociali' => 'SocialWorkers',
    'Pubblico'           => 'Public',
]);

echo "\n[3/5] Renaming opportunity.data_presentazione -> opportunity.presentation_date...\n";
$renameColumn($pdo, 'opportunity', 'data_presentazione', 'presentation_date');

echo "\n[4/5] Translating opportunity stage enum values...\n";
$opportunityStageMap = [
    // Default EspoCRM stages are no longer valid once the Safehouse grant
    // pipeline metadata is installed; collapse both opening states safely.
    'Prospecting'           => 'Preparation',
    'Qualification'         => 'Preparation',
    'Preparazione'         => 'Preparation',
    'Proposta'             => 'Proposal',
    'Negoziazione'         => 'Negotiation',
    'Chiuso Positivamente' => 'Closed Won',
    'Chiuso Negativamente' => 'Closed Lost',
];
$updateColumnText($pdo, 'opportunity', 'stage', $opportunityStageMap);
$updateColumnText($pdo, 'opportunity', 'last_stage', $opportunityStageMap);

echo "\n[5/5] Cleaning up legacy `SafehouseCrmDeactivateExpiredVolunteerEmployee` job rows...\n";

// The job was a class-level alias of `SyncVolunteerEmployeeStatus` kept for
// backwards compatibility with the original `DeactivateExpiredVolontarioDipendente`
// scheduled job name during the Italian->English refactor. Now that the canonical
// `SyncVolunteerEmployeeStatus` exists, the alias just doubles the cron workload.
$legacyJobName = 'SafehouseCrmDeactivateExpiredVolunteerEmployee';
foreach (['scheduled_job', 'job'] as $jobTable) {
    if (!$tableExists($pdo, $jobTable)) {
        continue;
    }
    foreach (['name', 'job', 'class_name'] as $col) {
        if (!$columnExists($pdo, $jobTable, $col)) {
            continue;
        }
        $stmt = $pdo->prepare(sprintf('DELETE FROM `%s` WHERE `%s` = :n', $jobTable, $col));
        $stmt->execute([':n' => $legacyJobName]);
        if ($stmt->rowCount() > 0) {
            echo "  $jobTable.$col deleted '$legacyJobName' ({$stmt->rowCount()} rows)\n";
        }
    }
}

echo "\nDone.\n";
echo "Next steps:\n";
echo "  ddev exec php clear_cache.php\n";
echo "  ddev exec php rebuild.php\n";
