#!/usr/bin/env php
<?php
/**
 * Migrates the `userId` column to `assignedUserId` for VolunteerEmployee and Member.
 *
 * Idempotent: skips rows where assignedUserId is already set; logs conflicts.
 * Run: ddev exec php bin/migrate-user-to-assigned-user.php
 */

require_once __DIR__ . '/../bootstrap.php';

$app = new \Espo\Core\Application();
$em  = $app->getContainer()->getByClass(\Espo\ORM\EntityManager::class);
$pdo = $em->getPDO();

$tables = [
    'volunteer_employee' => 'VolunteerEmployee',
    'member'             => 'Member',
];

$migrated = 0;
$skipped  = 0;
$conflicts = 0;

foreach ($tables as $table => $entityType) {
    echo "\n=== $entityType ($table) ===\n";

    $rows = $pdo->query(
        "SELECT id, user_id, assigned_user_id FROM `$table` WHERE deleted = 0"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $id = $row['id'];
        $userId = $row['user_id'];
        $assignedUserId = $row['assigned_user_id'];

        if (empty($userId)) {
            echo "  [$id] no userId — skip\n";
            $skipped++;
            continue;
        }

        if (!empty($assignedUserId) && $assignedUserId === $userId) {
            echo "  [$id] already migrated (match) — skip\n";
            $skipped++;
            continue;
        }

        if (!empty($assignedUserId) && $assignedUserId !== $userId) {
            echo "  [$id] CONFLICT: userId=$userId, assignedUserId=$assignedUserId — keeping both, NOT overwriting\n";
            $conflicts++;
            continue;
        }

        $stmt = $pdo->prepare("UPDATE `$table` SET assigned_user_id = ? WHERE id = ?");
        $stmt->execute([$userId, $id]);
        echo "  [$id] copied userId=$userId -> assignedUserId\n";
        $migrated++;
    }
}

echo "\n--- Summary ---\n";
echo "Migrated: $migrated\n";
echo "Skipped:  $skipped\n";
echo "Conflicts: $conflicts\n";

if ($conflicts > 0) {
    echo "\nWARNING: $conflicts rows had conflicting assignedUserId values.\n";
    echo "Review these manually before proceeding.\n";
}

echo "\nDone. Run: ddev exec php clear_cache.php && ddev exec php rebuild.php\n";
