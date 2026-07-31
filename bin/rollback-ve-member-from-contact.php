<?php
/**
 * Rollback Contact STI migration: restore soft-deleted VE/Member, soft-delete migrated Contacts.
 *
 * DDEV only.
 *
 *   ddev exec php bin/rollback-ve-member-from-contact.php --dry-run
 *   ddev exec php bin/rollback-ve-member-from-contact.php
 *   # then restore tabs: temporarily clear ENTITIES_TO_HIDE or set SAFEHOUSE_RESTORE_LEGACY_PARTY_TABS=1
 *   ddev exec php bin/reorder-safehouse-tabs.php
 */

declare(strict_types=1);

require __DIR__ . '/lib/refuse-production.php';

include dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv, true);

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

$pdo = $em->getPDO();

$contacts = $em->getRDBRepository('Contact')
    ->where([
        'migratedFromEntityType!=' => null,
        'migratedFromEntityId!=' => null,
        'deleted' => false,
    ])
    ->find();

$restored = ['VolunteerEmployee' => 0, 'Member' => 0];
$contactsRemoved = 0;

foreach ($contacts as $contact) {
    $fromType = (string) $contact->get('migratedFromEntityType');
    $fromId = (string) $contact->get('migratedFromEntityId');

    if (!in_array($fromType, ['VolunteerEmployee', 'Member'], true) || $fromId === '') {
        continue;
    }

    if (!$dryRun) {
        // Undelete source row via PDO (ORM soft-delete find won't see deleted=1 easily with withDeleted).
        $table = $fromType === 'Member' ? 'member' : 'volunteer_employee';
        $stmt = $pdo->prepare("UPDATE `{$table}` SET deleted = 0 WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $fromId]);

        $em->removeEntity($contact);
    }

    $restored[$fromType] = ($restored[$fromType] ?? 0) + 1;
    $contactsRemoved++;
}

echo json_encode([
    'dryRun' => $dryRun,
    'contactsSoftDeleted' => $contactsRemoved,
    'sourcesRestored' => $restored,
    'note' => 'To show VE/Member tabs again, clear Installer ENTITIES_TO_HIDE and run reorder-safehouse-tabs.php (or set env SAFEHOUSE_RESTORE_LEGACY_PARTY_TABS=1 if wired).',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
