<?php

/**
 * Blocks 5–8 — per-date Google Calendar lifecycle (same scenarios as Block 4 where applicable).
 *
 * Each entity section:
 *   .1 create with one selected date → 1 CRM link + Google event
 *   .2 edit add second date (dual-date entities only) → 2 links, first gid unchanged
 *   .3 edit display name with & → Google summary plain &, not &amp;
 *   .4 CRM delete → 0 active links
 *
 * Single-date entities (Member, Account, Task, Campaign): .2 becomes "edit primary date → same gid".
 *
 * Usage:
 *   ddev exec php bin/test-block5-8-gcal-per-date.php
 *   ddev exec php bin/cleanup-gcal-e2e.php BLOCK58_
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google as GoogleClient;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);

$admin = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'admin', 'deleted' => false])
    ->findOne();

if ($admin === null) {
    fwrite(STDERR, "FAIL: admin user not found\n");
    exit(1);
}

$container->getByClass(ApplicationUser::class)->setUser($admin);
$adminId = $admin->getId();
$pdo = $em->getPDO();

$client = $container->getByClass(ClientManager::class)
    ->create(Installer::INTEGRATION_ID, $adminId);

if (!$client instanceof GoogleClient) {
    fwrite(STDERR, "FAIL: Google OAuth not connected for admin\n");
    exit(1);
}

$tag = 'BLOCK58_' . gmdate('Ymd_His');
$totalPass = 0;
$totalFail = 0;
$matrix = [];

$ok = function (string $section, string $caseId, string $label, bool $passed, string $detail = '') use (&$totalPass, &$totalFail, &$matrix): void {
    if ($passed) {
        $totalPass++;
    } else {
        $totalFail++;
    }

    $matrix[] = [
        'section' => $section,
        'case' => $caseId,
        'label' => $label,
        'passed' => $passed,
        'detail' => $detail,
    ];

    echo '  [' . ($passed ? 'PASS' : 'FAIL') . "] {$caseId} {$label}"
        . ($detail !== '' ? " — {$detail}" : '') . "\n";
};

$countLinks = function (string $entityType, string $entityId) use ($pdo, $adminId): int {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND user_id = ? AND deleted = 0'
    );
    $stmt->execute([$entityType, $entityId, $adminId]);

    return (int) $stmt->fetchColumn();
};

$linkForType = function (string $entityType, string $entityId, string $dateType) use ($pdo, $adminId): ?array {
    $stmt = $pdo->prepare(
        'SELECT id, google_event_id FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND source_date_type = ?
           AND user_id = ? AND deleted = 0 LIMIT 1'
    );
    $stmt->execute([$entityType, $entityId, $dateType, $adminId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
};

$fetchGoogleSummary = function (string $googleEventId) use ($client): ?string {
    try {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($googleEventId);
        $response = $client->request($url);

        return is_string($response['summary'] ?? null) ? $response['summary'] : null;
    } catch (Throwable) {
        return null;
    }
};

/**
 * @param list<string> $sourceDateTypes
 * @return list<array<string, mixed>>
 */
function settingsRows(array $sourceDateTypes): array
{
    $rows = [];

    foreach ($sourceDateTypes as $sourceDateType) {
        $rows[] = [
            'sourceDateType' => $sourceDateType,
            'reminderMode' => 'none',
            'reminders' => [],
            'location' => '',
            'visibility' => 'default',
            'transparency' => 'opaque',
            'colorId' => '',
            'calendarTemplateId' => '',
            'descriptionTemplateOverride' => '',
        ];
    }

    return $rows;
}

/**
 * @param array{
 *   section: string,
 *   entityType: string,
 *   dualDate: bool,
 *   primaryDateType: string,
 *   secondaryDateType?: string,
 *   ampersandNeedle: string,
 *   create: callable(): Entity,
 *   applySecondDate?: callable(Entity): void,
 *   applyPrimaryDateShift?: callable(Entity): void,
 *   renameWithAmpersand: callable(Entity): void,
 * } $spec
 */
function runBlock(array $spec, EntityManager $em, callable $ok, callable $countLinks, callable $linkForType, callable $fetchGoogleSummary): void
{
    $section = $spec['section'];
    $entityType = $spec['entityType'];
    $primary = $spec['primaryDateType'];

    echo "── {$section} ({$entityType}) ──\n";

    $entity = $spec['create']();

    try {
        $em->saveEntity($entity);
    } catch (Throwable $e) {
        $ok($section, "{$section}.0", 'create saveEntity', false, $e->getMessage());
        echo "\n";

        return;
    }

    $entityId = $entity->getId();

    $links1 = $countLinks($entityType, $entityId);
    $primaryLink = $linkForType($entityType, $entityId, $primary);
    $primaryGid = $primaryLink['google_event_id'] ?? '';

    $ok($section, "{$section}.1", 'create → 1 CRM link', $links1 === 1, 'links=' . $links1);
    $ok($section, "{$section}.1", 'primary Google link exists', $primaryLink !== null && $primaryGid !== '');

    $summary1 = $primaryGid !== '' ? $fetchGoogleSummary($primaryGid) : null;
    $ok(
        $section,
        "{$section}.1",
        'Google summary has & not &amp;',
        is_string($summary1)
            && str_contains($summary1, $spec['ampersandNeedle'])
            && !str_contains($summary1, '&amp;'),
        'summary=' . ($summary1 ?? 'null')
    );

    if ($spec['dualDate']) {
        echo "  (dual-date: add second source on edit)\n";
        $entity = $em->getEntityById($entityType, $entityId);

        if ($entity === null) {
            $ok($section, "{$section}.2", 'reload for edit', false, 'entity missing');
        } else {
            ($spec['applySecondDate'])($entity);
            $em->saveEntity($entity);

            $secondary = $spec['secondaryDateType'];
            $links2 = $countLinks($entityType, $entityId);
            $secondLink = $linkForType($entityType, $entityId, $secondary);
            $primaryLink2 = $linkForType($entityType, $entityId, $primary);

            $ok($section, "{$section}.2", 'two links after second date added', $links2 === 2, 'links=' . $links2);
            $ok($section, "{$section}.2", 'secondary link created', $secondLink !== null && ($secondLink['google_event_id'] ?? '') !== '');
            $ok(
                $section,
                "{$section}.2",
                'primary same google_event_id',
                $primaryLink2 !== null && ($primaryLink2['google_event_id'] ?? '') === $primaryGid,
                'gid=' . $primaryGid
            );
        }
    } else {
        echo "  (single-date: shift primary date on edit)\n";
        $entity = $em->getEntityById($entityType, $entityId);

        if ($entity === null) {
            $ok($section, "{$section}.2", 'reload for edit', false, 'entity missing');
        } else {
            ($spec['applyPrimaryDateShift'])($entity);
            $em->saveEntity($entity);

            $primaryLink2 = $linkForType($entityType, $entityId, $primary);
            $ok($section, "{$section}.2", 'still one link after date shift', $countLinks($entityType, $entityId) === 1);
            $ok(
                $section,
                "{$section}.2",
                'same google_event_id after date shift',
                $primaryLink2 !== null && ($primaryLink2['google_event_id'] ?? '') === $primaryGid
            );
        }
    }

    $entity = $em->getEntityById($entityType, $entityId);

    if ($entity !== null) {
        ($spec['renameWithAmpersand'])($entity);
        $em->saveEntity($entity);
    }

    $summary3 = $primaryGid !== '' ? $fetchGoogleSummary($primaryGid) : null;
    $ok(
        $section,
        "{$section}.3",
        'rename preserves & in Google',
        is_string($summary3)
            && str_contains($summary3, 'updated')
            && str_contains($summary3, '&')
            && !str_contains($summary3, '&amp;'),
        'summary=' . ($summary3 ?? 'null')
    );

    $entity = $em->getEntityById($entityType, $entityId);

    if ($entity !== null) {
        $em->removeEntity($entity);
    }

    $ok($section, "{$section}.4", 'CRM delete → 0 links', $countLinks($entityType, $entityId) === 0);

    echo "\n";
}

echo "=== Blocks 5–8 per-date Google Calendar E2E ===\n";
echo "Tag: {$tag}\n\n";

$sfx = substr(Util::generateId(), 0, 6);
$d1 = (new DateTimeImmutable('+5 days'))->format('Y-m-d');
$d2 = (new DateTimeImmutable('+35 days'))->format('Y-m-d');
$d1Shift = (new DateTimeImmutable('+6 days'))->format('Y-m-d');
$taskEnd = (new DateTimeImmutable('+5 days'))->format('Y-m-d') . ' 17:00:00';
$smokeUserId = '6a063550b505f82da';

runBlock([
    'section' => '5 Member',
    'entityType' => 'Member',
    'dualDate' => false,
    'primaryDateType' => 'main',
    'ampersandNeedle' => 'F&F',
    'create' => function () use ($em, $tag, $sfx, $d1, $adminId) {
        $e = $em->getNewEntity('Member');
        $e->set([
            'firstName' => 'Test',
            'lastName' => "{$tag} F&F {$sfx}",
            'status' => 'Active',
            'birthDate' => $d1,
            'emailAddress' => "block58-member-{$sfx}@example.test",
            'saveToGoogleCalendar' => true,
            'googleCalendarDateSourceList' => ['main'],
            'googleCalendarEventSettings' => settingsRows(['main']),
        ]);

        return $e;
    },
    'applyPrimaryDateShift' => function (Entity $e) use ($d1Shift) {
        $e->set('birthDate', $d1Shift);
    },
    'renameWithAmpersand' => function (Entity $e) use ($tag, $sfx) {
        $e->set('lastName', "{$tag} F&F updated {$sfx}");
    },
], $em, $ok, $countLinks, $linkForType, $fetchGoogleSummary);

runBlock([
    'section' => '6 VolunteerEmployee',
    'entityType' => 'VolunteerEmployee',
    'dualDate' => true,
    'primaryDateType' => 'main',
    'secondaryDateType' => 'endDate',
    'ampersandNeedle' => 'F&F',
    'create' => function () use ($em, $tag, $sfx, $d1, $d2, $smokeUserId) {
        $e = $em->getNewEntity('VolunteerEmployee');
        $e->set([
            'firstName' => 'Test',
            'lastName' => "{$tag} F&F {$sfx}",
            'type' => 'Volunteer',
            'status' => 'Active',
            'startDate' => $d1,
            'endDate' => $d2,
            'emailAddress' => "block58-vol-{$sfx}@example.test",
            'saveToGoogleCalendar' => true,
            'googleCalendarDateSourceList' => ['main'],
            'googleCalendarEventSettings' => settingsRows(['main']),
            'assignedUserId' => $smokeUserId,
        ]);

        return $e;
    },
    'applySecondDate' => function (Entity $e) use ($d1, $d2) {
        $e->set([
            'googleCalendarDateSourceList' => ['main', 'endDate'],
            'googleCalendarEventSettings' => settingsRows(['main', 'endDate']),
            'startDate' => $d1,
            'endDate' => $d2,
        ]);
    },
    'renameWithAmpersand' => function (Entity $e) use ($tag, $sfx) {
        $e->set('lastName', "{$tag} F&F updated {$sfx}");
    },
], $em, $ok, $countLinks, $linkForType, $fetchGoogleSummary);

runBlock([
    'section' => '7 Account',
    'entityType' => 'Account',
    'dualDate' => false,
    'primaryDateType' => 'main',
    'ampersandNeedle' => 'F&F',
    'create' => function () use ($em, $tag, $sfx, $d1) {
        $e = $em->getNewEntity('Account');
        $e->set([
            'name' => "{$tag} Acct F&F {$sfx}",
            'cDataFirmaContratto' => $d1,
            'saveToGoogleCalendar' => true,
            'googleCalendarDateSourceList' => ['main'],
            'googleCalendarEventSettings' => settingsRows(['main']),
        ]);

        return $e;
    },
    'applyPrimaryDateShift' => function (Entity $e) use ($d1Shift) {
        $e->set('cDataFirmaContratto', $d1Shift);
    },
    'renameWithAmpersand' => function (Entity $e) use ($tag, $sfx) {
        $e->set('name', "{$tag} Acct F&F updated {$sfx}");
    },
], $em, $ok, $countLinks, $linkForType, $fetchGoogleSummary);

runBlock([
    'section' => '8a Task',
    'entityType' => 'Task',
    'dualDate' => false,
    'primaryDateType' => 'main',
    'ampersandNeedle' => 'F&F',
    'create' => function () use ($em, $tag, $sfx, $taskEnd, $d1, $adminId) {
        $e = $em->getNewEntity('Task');
        $e->set([
            'name' => "{$tag} Task F&F {$sfx}",
            'status' => 'Not Started',
            'dateEnd' => $taskEnd,
            'dateEndDate' => $d1,
            'saveToGoogleCalendar' => true,
            'googleCalendarDateSourceList' => ['main'],
            'googleCalendarEventSettings' => settingsRows(['main']),
            'assignedUserId' => $adminId,
        ]);

        return $e;
    },
    'applyPrimaryDateShift' => function (Entity $e) use ($d1Shift) {
        $e->set([
            'dateEndDate' => $d1Shift,
            'dateEnd' => $d1Shift . ' 17:00:00',
        ]);
    },
    'renameWithAmpersand' => function (Entity $e) use ($tag, $sfx) {
        $e->set('name', "{$tag} Task F&F updated {$sfx}");
    },
], $em, $ok, $countLinks, $linkForType, $fetchGoogleSummary);

runBlock([
    'section' => '8b Campaign',
    'entityType' => 'Campaign',
    'dualDate' => false,
    'primaryDateType' => 'campaignFinDate',
    'ampersandNeedle' => 'F&F',
    'create' => function () use ($em, $tag, $sfx, $d1) {
        $e = $em->getNewEntity('Campaign');
        $e->set([
            'name' => "{$tag} Camp F&F {$sfx}",
            'status' => 'Active',
            'startDate' => $d1,
            'saveToGoogleCalendar' => true,
            'googleCalendarDateSourceList' => ['campaignFinDate'],
            'googleCalendarEventSettings' => settingsRows(['campaignFinDate']),
        ]);

        return $e;
    },
    'applyPrimaryDateShift' => function (Entity $e) use ($d1Shift) {
        $e->set('startDate', $d1Shift);
    },
    'renameWithAmpersand' => function (Entity $e) use ($tag, $sfx) {
        $e->set('name', "{$tag} Camp F&F updated {$sfx}");
    },
], $em, $ok, $countLinks, $linkForType, $fetchGoogleSummary);

echo "=== MATRIX (copy for report) ===\n";
echo str_pad('Block', 22) . str_pad('Case', 8) . str_pad('Result', 8) . "Check\n";
echo str_repeat('-', 72) . "\n";

foreach ($matrix as $row) {
    echo str_pad($row['section'], 22)
        . str_pad($row['case'], 8)
        . str_pad($row['passed'] ? 'PASS' : 'FAIL', 8)
        . $row['label']
        . ($row['detail'] !== '' ? " ({$row['detail']})" : '')
        . "\n";
}

echo "\n=== TOTAL: PASS {$totalPass}, FAIL {$totalFail} ===\n";
echo "Cleanup: ddev exec php bin/cleanup-gcal-e2e.php BLOCK58_\n\n";

exit($totalFail > 0 ? 1 : 0);
