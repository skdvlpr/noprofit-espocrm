<?php
/**
 * One-shot data migration: rename Italian-named schema objects to their English
 * equivalents while preserving all existing rows (Mario Rossi & friends).
 *
 * What it does:
 *   1) Renames tables and columns:
 *        volontario_dipendente   -> volunteer_employee
 *        associati               -> member
 *        conteggio_pasti         -> meal_count
 *        + per-table column renames (nome/cognome/dataInizio/...)
 *   2) Renames the foreign-key column inside many-to-many join tables and
 *      renames the join tables themselves (document_volontario_dipendente ->
 *      document_volunteer_employee, etc.).
 *   3) Translates enum text values (Volontario/Dipendente/Attivo/Inattivo/...).
 *   4) Updates polymorphic `*_type` columns across Espo system tables
 *      (note, attachment, notification, entity_team, entity_email_address,
 *      entity_phone_number, action_history_record).
 *   5) Rewrites Role.data and Role.field_data JSON to use the new entity-type
 *      keys; renames Role rows (Dipendente -> Employee, Volontario -> Volunteer,
 *      Associato -> Member) and the Team row (Amministrazione -> Administration).
 *   6) Removes stale `scheduled_job` and `job` rows that pointed at the old
 *      Italian-named job classes; the next Admin -> Repair -> Rebuild creates
 *      English-named replacements from metadata.
 *
 * Idempotent: every step checks whether the source object exists and the
 * destination does not; re-running the script after a successful first pass is
 * a no-op.
 *
 * Usage:
 *   ddev exec php bin/migrate-rename-italian.php
 *
 * After running:
 *   ddev exec php clear_cache.php
 *   ddev exec php rebuild.php
 *   ddev exec php bin/setup-roles.php
 *   ddev exec php bin/reorder-safehouse-tabs.php   # optional
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);

/** @var PDO $pdo */
$pdo = $em->getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Migration: rename Italian schema objects to English ===\n\n";

/* ---------- helpers --------------------------------------------------- */

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

$renameTable = static function (PDO $pdo, string $old, string $new) use ($tableExists): void {
    if ($tableExists($pdo, $old) && !$tableExists($pdo, $new)) {
        $pdo->exec(sprintf('RENAME TABLE `%s` TO `%s`', $old, $new));
        echo "  table renamed: $old -> $new\n";
    } else {
        echo "  skip table rename $old -> $new (state already migrated or source missing)\n";
    }
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

/* ---------- 1. Rename main tables ------------------------------------- */

echo "[1/8] Renaming main tables...\n";

$mainTables = [
    'volontario_dipendente' => 'volunteer_employee',
    'associati'             => 'member',
    'conteggio_pasti'       => 'meal_count',
];
foreach ($mainTables as $old => $new) {
    $renameTable($pdo, $old, $new);
}

/* ---------- 2. Rename columns on main tables -------------------------- */

echo "\n[2/8] Renaming columns on main tables...\n";

$columnRenames = [
    'volunteer_employee' => [
        'tipo'             => 'type',
        'nome'             => 'first_name',
        'cognome'          => 'last_name',
        'data_nascita'     => 'birth_date',
        'data_inizio'      => 'start_date',
        'data_fine'        => 'end_date',
        'tipo_contratto'   => 'contract_type',
        'ore_settimanali'  => 'weekly_hours',
        'ore_mensili'      => 'monthly_hours',
    ],
    'member' => [
        'stato'                  => 'status',
        'nome'                   => 'first_name',
        'cognome'                => 'last_name',
        'luogo_nascita'          => 'birth_place',
        'data_nascita'           => 'birth_date',
        'cf'                     => 'tax_code',
        'indirizzo_residenza'    => 'residence_address',
        'citta'                  => 'city',
        'provincia'              => 'province',
        'data_ingresso'          => 'join_date',
        'data_dimissione'        => 'leave_date',
        'incarichi_ricoperti'    => 'positions_held',
        'annotazioni'            => 'notes',
    ],
    'meal_count' => [
        'data'              => 'date',
        'giorno_settimana'  => 'day_of_week',
        'adulti'            => 'adults',
        'minori'            => 'minors',
        'totale_pasti'      => 'total_meals',
    ],
];
foreach ($columnRenames as $table => $cols) {
    foreach ($cols as $old => $new) {
        $renameColumn($pdo, $table, $old, $new);
    }
}

/* ---------- 3. Rename M2M join tables and their FK columns ------------ */

echo "\n[3/8] Renaming many-to-many join tables...\n";

$joinTables = [
    // [old_table, new_table, old_fk_col, new_fk_col]
    ['document_volontario_dipendente', 'document_volunteer_employee', 'volontario_dipendente_id', 'volunteer_employee_id'],
    ['meeting_volontario_dipendente',  'meeting_volunteer_employee',  'volontario_dipendente_id', 'volunteer_employee_id'],
];
foreach ($joinTables as [$oldT, $newT, $oldCol, $newCol]) {
    $renameTable($pdo, $oldT, $newT);
    $renameColumn($pdo, $newT, $oldCol, $newCol);
}

/* ---------- 4. Translate enum text values ----------------------------- */

echo "\n[4/8] Translating enum text values...\n";

$updateColumnText($pdo, 'volunteer_employee', 'type', [
    'Volontario' => 'Volunteer',
    'Dipendente' => 'Employee',
]);
$updateColumnText($pdo, 'volunteer_employee', 'contract_type', [
    'Tempo Indeterminato' => 'Permanent',
    'Tempo Determinato'   => 'FixedTerm',
    'Partita IVA'         => 'Freelance',
    'Altro'               => 'Other',
]);
$updateColumnText($pdo, 'volunteer_employee', 'status', [
    'Attivo'   => 'Active',
    'Inattivo' => 'Inactive',
]);
$updateColumnText($pdo, 'member', 'status', [
    'Attivo'   => 'Active',
    'Inattivo' => 'Inactive',
]);
$updateColumnText($pdo, 'meal_count', 'day_of_week', [
    'Lunedì'    => 'Monday',
    'Martedì'   => 'Tuesday',
    'Mercoledì' => 'Wednesday',
    'Giovedì'   => 'Thursday',
    'Venerdì'   => 'Friday',
    'Sabato'    => 'Saturday',
    'Domenica'  => 'Sunday',
]);
$updateColumnText($pdo, 'document', 'status', [
    'Bozza'      => 'Draft',
    'Attivo'     => 'Active',
    'Inattivo'   => 'Inactive',
    'Scaduto'    => 'Expired',
    'Archiviato' => 'Archived',
]);

/* ---------- 5. Polymorphic *_type columns ----------------------------- */

echo "\n[5/8] Updating polymorphic entity-type columns...\n";

$entityTypeMap = [
    'VolontarioDipendente' => 'VolunteerEmployee',
    'Associati'            => 'Member',
    'ConteggioPasti'       => 'MealCount',
];

$polyColumns = [
    'note'                  => ['parent_type', 'related_type', 'super_parent_type', 'target_type'],
    'attachment'            => ['parent_type', 'related_type'],
    'notification'          => ['related_type', 'related_parent_type'],
    'entity_team'           => ['entity_type'],
    'entity_email_address'  => ['entity_type'],
    'entity_phone_number'   => ['entity_type'],
    'action_history_record' => ['target_type'],
    'audit_log'             => ['entity_type'],
    'stream_subscription'   => ['entity_type'],
    'user_reaction'         => ['parent_type'],
    'email_address_entity'  => ['entity_type'],
    'phone_number_entity'   => ['entity_type'],
];

foreach ($polyColumns as $table => $cols) {
    if (!$tableExists($pdo, $table)) {
        continue;
    }
    foreach ($cols as $col) {
        if (!$columnExists($pdo, $table, $col)) {
            continue;
        }
        foreach ($entityTypeMap as $old => $new) {
            $stmt = $pdo->prepare(sprintf('UPDATE `%s` SET `%s` = :new WHERE `%s` = :old', $table, $col, $col));
            $stmt->execute([':new' => $new, ':old' => $old]);
            $n = $stmt->rowCount();
            if ($n > 0) {
                echo "    $table.$col: '$old' -> '$new' ($n rows)\n";
            }
        }
    }
}

/* ---------- 6. Roles, Teams, role.data / role.field_data -------------- */

echo "\n[6/8] Rewriting Role / Team rows...\n";

$roleNameMap = [
    'Dipendente' => 'Employee',
    'Volontario' => 'Volunteer',
    'Associato'  => 'Member',
];
foreach ($roleNameMap as $old => $new) {
    $stmt = $pdo->prepare('UPDATE role SET name = :new WHERE name = :old');
    $stmt->execute([':new' => $new, ':old' => $old]);
    if ($stmt->rowCount() > 0) {
        echo "  role renamed: '$old' -> '$new'\n";
    }
}

$stmt = $pdo->prepare("UPDATE team SET name = 'Administration' WHERE name = 'Amministrazione'");
$stmt->execute();
if ($stmt->rowCount() > 0) {
    echo "  team renamed: 'Amministrazione' -> 'Administration'\n";
}

// role.data + role.field_data are JSON-encoded objects keyed by entity type.
$rolesStmt = $pdo->query('SELECT id, `data`, `field_data` FROM role');
$entityKeyMap = $entityTypeMap;
$rolesUpdated = 0;
foreach ($rolesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $data = $row['data'] !== null ? json_decode((string) $row['data'], true) : null;
    $fieldData = $row['field_data'] !== null ? json_decode((string) $row['field_data'], true) : null;
    $changed = false;

    $remap = static function (array $assoc, array $keyMap, bool &$changed): array {
        $out = [];
        foreach ($assoc as $k => $v) {
            $nk = $keyMap[$k] ?? $k;
            if ($nk !== $k) {
                $changed = true;
            }
            $out[$nk] = $v;
        }
        return $out;
    };

    if (is_array($data)) {
        $data = $remap($data, $entityKeyMap, $changed);
    }
    if (is_array($fieldData)) {
        $fieldData = $remap($fieldData, $entityKeyMap, $changed);
    }

    if ($changed) {
        $upd = $pdo->prepare('UPDATE role SET `data` = :d, `field_data` = :f WHERE id = :id');
        $upd->execute([
            ':d'  => is_array($data) ? json_encode($data) : null,
            ':f'  => is_array($fieldData) ? json_encode($fieldData) : null,
            ':id' => $row['id'],
        ]);
        $rolesUpdated++;
    }
}
echo "  role rows updated (data/field_data keys remapped): $rolesUpdated\n";

/* ---------- 7. Cleanup stale scheduled_job / job rows ----------------- */

echo "\n[7/8] Cleaning up stale scheduled-job / job-queue rows...\n";

$staleJobKeys = [
    'SafehouseCrmSyncVolontarioDipendenteStatus',
    'SafehouseCrmSyncAssociatiStatus',
    'DeactivateExpiredVolontarioDipendente',
];

foreach (['scheduled_job', 'job'] as $jobTable) {
    if (!$tableExists($pdo, $jobTable)) {
        continue;
    }
    foreach ($staleJobKeys as $key) {
        // 'name' on scheduled_job, 'name' or 'class_name' on job; try both common columns.
        foreach (['name', 'job', 'class_name'] as $col) {
            if (!$columnExists($pdo, $jobTable, $col)) {
                continue;
            }
            $stmt = $pdo->prepare(sprintf('DELETE FROM `%s` WHERE `%s` = :n', $jobTable, $col));
            $stmt->execute([':n' => $key]);
            if ($stmt->rowCount() > 0) {
                echo "  $jobTable.$col deleted '$key' ({$stmt->rowCount()} rows)\n";
            }
        }
    }
}

echo "\n[8/8] Updating config tabList / quickCreateList (Italian entity keys)...\n";

$injectableFactory = $container->getByClass(InjectableFactory::class);
/** @var Config $appConfig */
$appConfig = $container->getByClass(Config::class);
$configWriter = $injectableFactory->create(ConfigWriter::class);

$tabMap = [
    'VolontarioDipendente' => 'VolunteerEmployee',
    'Associati'            => 'Member',
    'ConteggioPasti'       => 'MealCount',
];

$configDirty = false;
foreach (['tabList', 'quickCreateList'] as $configKey) {
    $list = $appConfig->get($configKey);
    if (!is_array($list)) {
        echo "  skip $configKey (not an array)\n";
        continue;
    }
    $changed = false;
    foreach ($list as $i => $item) {
        if (!is_string($item)) {
            continue;
        }
        if (isset($tabMap[$item])) {
            $list[$i] = $tabMap[$item];
            $changed = true;
        }
    }
    if ($changed) {
        $configWriter->set($configKey, array_values($list));
        $configDirty = true;
        echo "  $configKey: Italian entity keys replaced with English equivalents\n";
    } else {
        echo "  $configKey: no Italian entity keys found\n";
    }
}
if ($configDirty) {
    $configWriter->save();
}

echo "\nDone.\n";
echo "Next steps:\n";
echo "  ddev exec php clear_cache.php\n";
echo "  ddev exec php rebuild.php\n";
echo "  ddev exec php bin/setup-roles.php\n";
