<?php
/**
 * One-shot migrate VolunteerEmployee + Member → Contact (STI contactType).
 *
 * Entities VolunteerEmployee and Member are RETIRED (2026-08-11). Keep this
 * script only for historical / leftover-table cleanup on old DBs. Do not recreate
 * those entity scopes. Rollback helper was deleted — no restore path.
 *
 * DDEV only (refuse-production).
 *
 *   ddev exec php bin/migrate-ve-member-to-contact.php --dry-run
 *   ddev exec php bin/migrate-ve-member-to-contact.php
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

$stats = [
    'VolunteerEmployee' => ['created' => 0, 'skipped' => 0],
    'Member' => ['created' => 0, 'skipped' => 0],
];

$migratePerson = static function (
    string $entityType,
    callable $mapContactType,
    callable $copyFields
) use ($em, $dryRun, &$stats): void {
    $rows = $em->getRDBRepository($entityType)
        ->where(['deleted' => false])
        ->find();

    foreach ($rows as $src) {
        $srcId = (string) $src->getId();
        $existing = $em->getRDBRepository('Contact')
            ->where([
                'migratedFromEntityType' => $entityType,
                'migratedFromEntityId' => $srcId,
                'deleted' => false,
            ])
            ->findOne();

        if ($existing !== null) {
            if (!$dryRun) {
                $table = $entityType === 'Member' ? 'member' : 'volunteer_employee';
                $stmt = $em->getPDO()->prepare(
                    "UPDATE `{$table}` SET deleted = 1, assigned_user_id = NULL WHERE id = :id AND deleted = 0 LIMIT 1"
                );
                $stmt->execute(['id' => $srcId]);
            }
            $stats[$entityType]['skipped']++;
            continue;
        }

        $contact = $em->getNewEntity('Contact');
        $contact->set('firstName', $src->get('firstName'));
        $contact->set('lastName', $src->get('lastName'));
        $contact->set('contactType', $mapContactType($src));
        $contact->set('migratedFromEntityType', $entityType);
        $contact->set('migratedFromEntityId', $srcId);
        $contact->set('personnelStatus', $src->get('status') ?: 'Active');

        $assigned = trim((string) ($src->get('assignedUserId') ?? ''));
        if ($assigned !== '') {
            $contact->set('linkedUserId', $assigned);
            $contact->set('isUser', true);
        }

        $copyFields($contact, $src);

        // Email/phone: copy string attributes if present (Espo email/phone fields).
        if ($src->get('emailAddress')) {
            $contact->set('emailAddress', $src->get('emailAddress'));
        }
        if ($src->get('phoneNumber')) {
            $contact->set('phoneNumber', $src->get('phoneNumber'));
        }

        if ($src->get('teamsIds')) {
            $contact->set('teamsIds', $src->get('teamsIds'));
        }

        if (!$dryRun) {
            $em->saveEntity($contact, [SaveOption::SKIP_ALL => true]);

            // Soft-delete source without hooks (GCal / unique assignedUser): clear link then mark deleted.
            $table = $entityType === 'Member' ? 'member' : 'volunteer_employee';
            $stmt = $em->getPDO()->prepare(
                "UPDATE `{$table}` SET deleted = 1, assigned_user_id = NULL WHERE id = :id AND deleted = 0 LIMIT 1"
            );
            $stmt->execute(['id' => $srcId]);
        }

        $stats[$entityType]['created']++;
    }
};

$migratePerson(
    'VolunteerEmployee',
    static fn ($src): string => ((string) $src->get('type') === 'Employee') ? 'Employee' : 'Volunteer',
    static function ($contact, $src): void {
        $contact->set('startDate', $src->get('startDate'));
        $contact->set('endDate', $src->get('endDate'));
        $contact->set('contractType', $src->get('contractType'));
        $contact->set('weeklyHours', $src->get('weeklyHours'));
        $contact->set('monthlyHours', $src->get('monthlyHours'));
        $contact->set('extra', $src->get('extra'));
        $contact->set('birthDate', $src->get('birthDate'));
    }
);

$migratePerson(
    'Member',
    static fn ($src): string => 'MemberContact',
    static function ($contact, $src): void {
        $contact->set('taxCode', $src->get('taxCode'));
        $contact->set('birthPlace', $src->get('birthPlace'));
        $contact->set('birthDate', $src->get('birthDate'));
        $contact->set('joinDate', $src->get('joinDate'));
        $contact->set('leaveDate', $src->get('leaveDate'));
        $contact->set('positionsHeld', $src->get('positionsHeld'));
        $contact->set('notes', $src->get('notes'));
        foreach (['addressStreet', 'addressCity', 'addressState', 'addressCountry', 'addressPostalCode'] as $addr) {
            if ($src->has($addr)) {
                $contact->set($addr, $src->get($addr));
            }
        }
    }
);

echo json_encode([
    'dryRun' => $dryRun,
    'stats' => $stats,
    'next' => $dryRun
        ? 'Re-run without --dry-run, then: ddev exec php bin/reorder-safehouse-tabs.php'
        : 'Run: ddev exec php bin/reorder-safehouse-tabs.php && hard-refresh UI',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
