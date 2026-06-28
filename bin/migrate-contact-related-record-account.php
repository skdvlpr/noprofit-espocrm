<?php
/**
 * One-shot migration: align Contact relatedRecord (Account link) with native accountId.
 *
 * - Backfill relatedRecordId from accountId when missing.
 * - Sync accountId from relatedRecord when type was Account (legacy linkParent).
 * - Clear relatedRecord when it pointed at a non-Account entity (field is Account-only now).
 *
 * Idempotent. Safe to re-run.
 *
 * Usage (after rebuild so contact columns exist):
 *   php bin/migrate-contact-related-record-account.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);
$pdo = $em->getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Migration: Contact relatedRecord → Account ===\n\n";

$columnExists = static function (PDO $pdo, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
    );
    $stmt->execute([':t' => 'contact', ':c' => $column]);

    return (bool) $stmt->fetchColumn();
};

foreach (['related_record_id', 'account_id'] as $col) {
    if (!$columnExists($pdo, $col)) {
        echo "Column $col missing — run rebuild first.\n";
        exit(1);
    }
}

$hasType = $columnExists($pdo, 'related_record_type');

$select = 'id, account_id, related_record_id'
    . ($hasType ? ', related_record_type' : '')
    . ', account_name, related_record_name';

$rows = $pdo->query("SELECT $select FROM contact WHERE deleted = 0")->fetchAll(PDO::FETCH_ASSOC);

$backfilledRelated = 0;
$syncedAccount = 0;
$clearedNonAccount = 0;

foreach ($rows as $row) {
    $id = $row['id'];
    $accountId = $row['account_id'] ?: null;
    $relatedId = $row['related_record_id'] ?: null;
    $relatedType = $hasType ? ($row['related_record_type'] ?: null) : null;

    if ($relatedType !== null && $relatedType !== '' && $relatedType !== 'Account') {
        $contact = $em->getEntityById('Contact', $id);

        if ($contact) {
            $contact->set('relatedRecordId', null);
            $contact->set('relatedRecordName', null);

            if ($hasType) {
                $contact->set('relatedRecordType', null);
            }

            $em->saveEntity($contact, [SaveOption::SKIP_ALL => true]);
            $clearedNonAccount++;
        }

        continue;
    }

    if (!$relatedId && $accountId) {
        $contact = $em->getEntityById('Contact', $id);

        if ($contact) {
            $contact->set('relatedRecordId', $accountId);
            $contact->set('relatedRecordName', $row['account_name'] ?: null);

            if ($hasType) {
                $contact->set('relatedRecordType', 'Account');
            }

            $em->saveEntity($contact, [SaveOption::SKIP_ALL => true]);
            $backfilledRelated++;
        }

        continue;
    }

    if ($relatedId && !$accountId && ($relatedType === 'Account' || $relatedType === null || $relatedType === '')) {
        $contact = $em->getEntityById('Contact', $id);

        if ($contact) {
            $contact->set('accountId', $relatedId);
            $contact->set('accountName', $row['related_record_name'] ?: $row['account_name'] ?: null);
            $em->saveEntity($contact, [SaveOption::SKIP_ALL => true]);
            $syncedAccount++;
        }
    }
}

echo "Backfilled relatedRecord from accountId: $backfilledRelated\n";
echo "Synced accountId from relatedRecord (Account): $syncedAccount\n";
echo "Cleared non-Account relatedRecord: $clearedNonAccount\n";
echo "\nDone.\n";
