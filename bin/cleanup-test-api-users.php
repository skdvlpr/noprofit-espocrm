<?php
/**
 * Organic cleanup of leftover test API users.
 *
 * Keeps the canonical, script-backed API identities and removes stale/orphan
 * test users that clutter Administration → API Users.
 *
 *   KEEP (canonical / real):
 *     - api_user                          real integration user (not test infra)
 *     - smoke_api_catalog                 provisioned by bin/smoke-espo-rest-catalog.php
 *     - smoke_api_volunteer               provisioned by bin/smoke-espo-rest-catalog.php
 *
 *   REMOVE (orphans, recreated on demand by their smoke if ever needed):
 *     - smoke_block2_*                    no script creates these (pure leftovers)
 *     - any other `type=api` user whose name matches a removable pattern below
 *
 * Idempotent. Safe to re-run.
 *
 * Usage:
 *   ddev exec php bin/cleanup-test-api-users.php          # dry-run (report only)
 *   ddev exec php bin/cleanup-test-api-users.php --apply  # actually delete
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

/** Canonical API users to always keep. */
const KEEP = [
    'api_user',
    'smoke_api_catalog',
    'smoke_api_volunteer',
];

/** Removable orphan patterns (regex, case-insensitive). */
const REMOVE_PATTERNS = [
    '/^smoke_block2_/i',
];

$apply = in_array('--apply', $argv, true);

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->getByClass(EntityManager::class);

$users = $em->getRDBRepository('User')
    ->where(['type' => 'api', 'deleted' => false])
    ->order('userName')
    ->find();

$removed = [];
$kept = [];

foreach ($users as $u) {
    $name = (string) $u->get('userName');

    if (in_array($name, KEEP, true)) {
        $kept[] = $name . ' (canonical)';
        continue;
    }

    $isRemovable = false;
    foreach (REMOVE_PATTERNS as $pattern) {
        if (preg_match($pattern, $name)) {
            $isRemovable = true;
            break;
        }
    }

    if (!$isRemovable) {
        $kept[] = $name . ' (unknown — left untouched)';
        continue;
    }

    if ($apply) {
        $em->removeEntity($u);
    }

    $removed[] = $name;
}

echo "\n=== cleanup-test-api-users " . ($apply ? '(APPLY)' : '(dry-run)') . " ===\n";

echo "\nKEEP:\n";
foreach ($kept as $k) {
    echo "  - $k\n";
}

echo "\n" . ($apply ? 'REMOVED:' : 'WOULD REMOVE:') . "\n";
if ($removed === []) {
    echo "  (none)\n";
} else {
    foreach ($removed as $r) {
        echo "  - $r\n";
    }
}

if (!$apply && $removed !== []) {
    echo "\nRe-run with --apply to delete.\n";
}
