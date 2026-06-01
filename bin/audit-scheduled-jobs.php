<?php

declare(strict_types=1);

/**
 * Audit scheduled jobs: metadata registration vs DB rows.
 *
 * Usage: ddev exec php bin/audit-scheduled-jobs.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);

$registered = $metadata->get('app.scheduledJobs') ?? [];

$customPrefixes = [
    'Safehouse', 'Google', 'Volont', 'Associat', 'Deactivate', 'GCal',
];

echo "=== Custom-related metadata scheduledJobs ===\n\n";

foreach ($registered as $name => $def) {
    $match = false;

    foreach ($customPrefixes as $prefix) {
        if (str_contains($name, $prefix)) {
            $match = true;
            break;
        }
    }

    if (!$match) {
        continue;
    }

    printf(
        "%-45s | %-55s | %-12s | %s\n",
        $name,
        $def['jobClassName'] ?? '(preparator only)',
        $def['scheduling'] ?? '(on-demand)',
        ($def['isSystem'] ?? false) ? 'system' : 'user'
    );
}

echo "\n=== All DB scheduled_job rows ===\n\n";

$pdo = $em->getPDO();
$stmt = $pdo->query(
    'SELECT name, job, status, scheduling, last_run, is_internal
     FROM scheduled_job
     WHERE deleted = 0
     ORDER BY name'
);

printf("%-45s | %-12s | %-16s | %-19s | %s\n", 'name', 'status', 'scheduling', 'last_run', 'internal');
echo str_repeat('-', 120) . "\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf(
        "%-45s | %-12s | %-16s | %-19s | %s\n",
        (string) ($row['name'] ?? ''),
        (string) ($row['status'] ?? ''),
        (string) ($row['scheduling'] ?? ''),
        (string) ($row['last_run'] ?? 'never'),
        ($row['is_internal'] ?? '0') ? 'yes' : 'no'
    );
}

echo "\n=== Metadata keys WITHOUT active DB row ===\n\n";

$dbNames = [];
$stmt2 = $pdo->query('SELECT name FROM scheduled_job WHERE deleted = 0');

while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    $dbNames[(string) $row['name']] = true;
}

foreach (['GoogleIntegrationSyncCalendar', 'SafehouseCrmSyncVolunteerEmployeeStatus', 'SafehouseCrmSyncMemberStatus'] as $key) {
  if (!isset($registered[$key])) {
      echo "MISSING in metadata: $key\n";
  } elseif (!isset($dbNames[$key])) {
      echo "In metadata but NOT in DB: $key (run Rebuild to provision)\n";
  }
}

echo "\n=== DB rows matching legacy/stale patterns ===\n\n";

$staleStmt = $pdo->query(
    "SELECT name, status, scheduling, last_run
     FROM scheduled_job
     WHERE deleted = 0
       AND (
         name LIKE '%Volont%'
         OR name LIKE '%Associat%'
         OR name LIKE '%Volontario%'
         OR name LIKE '%Deactivate%'
         OR name LIKE '%Google%'
         OR name LIKE '%Safehouse%'
       )
     ORDER BY name"
);

while ($row = $staleStmt->fetch(PDO::FETCH_ASSOC)) {
    printf(
        "%-50s | %-10s | %s\n",
        (string) $row['name'],
        (string) $row['status'],
        (string) ($row['scheduling'] ?? '')
    );
}

echo "\n=== Pending job queue (custom class names, last 20) ===\n\n";

if ($pdo->query("SHOW TABLES LIKE 'job'")->fetch()) {
    $jobStmt = $pdo->query(
        "SELECT name, status, execute_time, queue, created_at
         FROM job
         WHERE deleted = 0
           AND (
             class_name LIKE '%Google%'
             OR class_name LIKE '%Safehouse%'
             OR class_name LIKE '%Volont%'
           )
         ORDER BY created_at DESC
         LIMIT 20"
    );

    while ($row = $jobStmt->fetch(PDO::FETCH_ASSOC)) {
        printf(
            "%-40s | %-10s | queue=%s | %s\n",
            (string) ($row['name'] ?? $row['class_name'] ?? ''),
            (string) ($row['status'] ?? ''),
            (string) ($row['queue'] ?? ''),
            (string) ($row['created_at'] ?? '')
        );
    }
}

echo "\n=== Inactive or soft-deleted scheduled_job rows ===\n\n";

$inactiveStmt = $pdo->query(
    "SELECT name, status, scheduling, deleted, last_run
     FROM scheduled_job
     WHERE deleted = 1 OR status != 'Active'
     ORDER BY deleted DESC, name"
);

$inactiveCount = 0;

while ($row = $inactiveStmt->fetch(PDO::FETCH_ASSOC)) {
    $inactiveCount++;
    printf(
        "%-45s | %-10s | deleted=%s | %s\n",
        (string) ($row['name'] ?? ''),
        (string) ($row['status'] ?? ''),
        (string) ($row['deleted'] ?? '0'),
        (string) ($row['last_run'] ?? '')
    );
}

if ($inactiveCount === 0) {
    echo "(none)\n";
}

echo "\nDone.\n";
