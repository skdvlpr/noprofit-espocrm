<?php

/**
 * UI Block 3.1–3.3 — automated Google Calendar push/update/delete for Meeting + Call.
 *
 * Maps to manual checklist:
 *   3.1 — create + first push (GoogleCalendarEventLink + google_event_id)
 *   3.2 — update title, re-save, same google_event_id (no duplicate link)
 *   3.3 — CRM soft-delete → active links = 0
 *
 * Requires admin user with Google External Account connected (OAuth).
 *
 * Usage:
 *   ddev exec php bin/test-block3-gcal-meeting-call.php
 *   ddev exec php bin/cleanup-gcal-e2e.php BLOCK3_
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarDateTimeResolver;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\EntityManager;

$entityTypes = ['Meeting', 'Call'];

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$injectableFactory = $container->getByClass(InjectableFactory::class);

$admin = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'admin', 'deleted' => false])
    ->findOne();

if ($admin === null) {
    fwrite(STDERR, "FAIL: admin user not found\n");
    exit(1);
}

$container->getByClass(ApplicationUser::class)->setUser($admin);
$adminId = $admin->getId();
$eventPusher = $injectableFactory->create(EventPusher::class);
$dateTimeResolver = $injectableFactory->create(CalendarDateTimeResolver::class);
$dtUtil = $container->getByClass(DateTimeUtil::class);
$clientManager = $container->getByClass(ClientManager::class);
$pdo = $em->getPDO();

$tag = 'BLOCK3_' . gmdate('Ymd_His');
$fail = 0;
$pass = 0;

$ok = function (string $label, bool $passed, string $detail = '') use (&$fail, &$pass): void {
    if ($passed) {
        $pass++;
    } else {
        $fail++;
    }

    echo '  [' . ($passed ? 'PASS' : 'FAIL') . "] {$label}"
        . ($detail !== '' ? " — {$detail}" : '') . "\n";
};

$fetchGoogleEventId = function (string $entityType, string $entityId) use ($pdo, $adminId): ?string {
    $stmt = $pdo->prepare(
        'SELECT google_event_id FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND source_date_type = ?
           AND user_id = ? AND deleted = 0 LIMIT 1'
    );
    $stmt->execute([$entityType, $entityId, 'main', $adminId]);
    $gid = $stmt->fetchColumn();

    return is_string($gid) && $gid !== '' ? $gid : null;
};

$countActiveLinks = function (string $entityType, string $entityId) use ($pdo, $adminId): int {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND user_id = ? AND deleted = 0'
    );
    $stmt->execute([$entityType, $entityId, $adminId]);

    return (int) $stmt->fetchColumn();
};

$buildEntity = function (string $entityType, string $name) use ($em, $adminId) {
    $appTz = 'Europe/Rome';
    $base = new DateTimeImmutable('next monday 10:00:00', new DateTimeZone($appTz));
    $start = $base->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $end = $base->modify('+1 hour')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

    $entity = $em->getNewEntity($entityType);

    if ($entityType === 'Meeting') {
        $entity->set([
            'name' => $name,
            'dateStart' => $start,
            'dateEnd' => $end,
            'status' => 'Planned',
            'assignedUserId' => $adminId,
        ]);
    } else {
        $entity->set([
            'name' => $name,
            'dateStart' => $start,
            'dateEnd' => $end,
            'direction' => 'Outbound',
            'status' => 'Planned',
            'assignedUserId' => $adminId,
        ]);
    }

    return $entity;
};

$assertExportWallClockMatchesCrm = function (string $entityType, string $entityId) use (
    $em,
    $eventPusher,
    $dateTimeResolver,
    $dtUtil,
    $clientManager,
    $adminId,
    $ok
): void {
    $entity = $em->getEntityById($entityType, $entityId);

    if ($entity === null) {
        $ok("{$entityType} export wall-clock vs CRM", false, 'entity missing');

        return;
    }

    $dbStart = (string) $entity->get('dateStart');
    $buildRange = new ReflectionMethod($eventPusher, 'buildDateTimeRange');
    $buildRange->setAccessible(true);
    $range = $buildRange->invoke($eventPusher, $dbStart, (string) $entity->get('dateEnd'));
    $expectedWall = $dateTimeResolver->utcStorageToWallClockDateTime($dbStart);
    $crmDisplay = $dtUtil->convertSystemDateTime($dbStart);
    $crmTime = preg_match('/\d{2}:\d{2}/', $crmDisplay, $m) ? $m[0] : '';
    $exportTime = preg_match('/T(\d{2}:\d{2})/', (string) ($range['start']['dateTime'] ?? ''), $m2) ? $m2[1] : '';

    $ok(
        "{$entityType} export wall-clock matches resolver",
        ($range['start']['dateTime'] ?? '') === $expectedWall
            && ($range['start']['timeZone'] ?? '') === $dateTimeResolver->getExportTimeZone(),
        'payload=' . json_encode($range['start'] ?? null)
    );
    $ok(
        "{$entityType} export wall-clock time matches CRM display",
        $crmTime !== '' && $exportTime === $crmTime,
        "crm={$crmDisplay} exportTime={$exportTime}"
    );

    $client = $clientManager->create(Installer::INTEGRATION_ID, $adminId);

    if (!$client instanceof \Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google) {
        $ok("{$entityType} Google API wall-clock matches CRM", true, 'skipped (no Google client)');

        return;
    }

    $stmt = $em->getPDO()->prepare(
        'SELECT google_event_id FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND source_date_type = ?
           AND user_id = ? AND deleted = 0 LIMIT 1'
    );
    $stmt->execute([$entityType, $entityId, 'main', $adminId]);
    $gid = $stmt->fetchColumn();

    if (!is_string($gid) || $gid === '') {
        $ok("{$entityType} Google API wall-clock matches CRM", true, 'skipped (no link)');

        return;
    }

    try {
        $ev = $client->request(
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($gid)
        );
        $googleDateTime = (string) ($ev['start']['dateTime'] ?? '');
        $googleTime = preg_match('/T(\d{2}:\d{2})/', $googleDateTime, $m3) ? $m3[1] : '';
        $ok(
            "{$entityType} Google API wall-clock matches CRM display",
            $crmTime !== '' && $googleTime === $crmTime,
            "google={$googleDateTime} crm={$crmDisplay}"
        );
    } catch (Throwable $e) {
        $ok("{$entityType} Google API wall-clock matches CRM", false, $e->getMessage());
    }
};

$enableGoogleExport = function (string $entityType, string $entityId) use ($em): void {
    $entity = $em->getEntityById($entityType, $entityId);

    if ($entity === null) {
        throw new RuntimeException("Entity {$entityType}/{$entityId} not found");
    }

    $entity->set([
        'saveToGoogleCalendar' => true,
        'googleCalendarDateSourceList' => ['main'],
        'googleCalendarEventSettings' => [[
            'sourceDateType' => 'main',
            'reminderMode' => 'none',
            'reminders' => [],
            'location' => '',
            'visibility' => 'default',
            'transparency' => 'opaque',
            'colorId' => '',
            'calendarTemplateId' => '',
            'descriptionTemplateOverride' => '',
        ]],
    ]);

    $em->saveEntity($entity);
};

echo "=== Block 3 automated — Meeting + Call (Google Calendar) ===\n";
echo "Tag: {$tag}\n";
echo "Admin: {$admin->get('userName')} (Google OAuth must be connected)\n\n";

$records = [];

foreach ($entityTypes as $entityType) {
    echo "── {$entityType} ──\n\n";

    $sfx = substr(Util::generateId(), 0, 6);
    $nameV1 = "{$tag} {$entityType} v1 {$sfx}";
    $nameV2 = "{$tag} {$entityType} v2 UPDATED {$sfx}";

    // 3.1 — create + first export
    echo "Block 3.1 (create + push)\n";

    try {
        $entity = $buildEntity($entityType, $nameV1);
        $em->saveEntity($entity);
        $id = (string) $entity->getId();
        $records[$entityType] = $id;
        $ok("{$entityType} 3.1 CRM create", $id !== '');
    } catch (Throwable $e) {
        $ok("{$entityType} 3.1 CRM create", false, $e->getMessage());
        echo "\n";
        continue;
    }

    try {
        $enableGoogleExport($entityType, $id);
        $links = $countActiveLinks($entityType, $id);
        $gid = $fetchGoogleEventId($entityType, $id);
        $ok("{$entityType} 3.1 active link count = 1", $links === 1, "links={$links}");
        $ok("{$entityType} 3.1 google_event_id set", $gid !== null, $gid ? 'gid=' . substr($gid, 0, 20) . '…' : 'none');
        $assertExportWallClockMatchesCrm($entityType, $id);
    } catch (Throwable $e) {
        $ok("{$entityType} 3.1 Google push via save", false, $e->getMessage());
        echo "\n";
        continue;
    }

    $gidAfterFirst = $fetchGoogleEventId($entityType, $id);

    // 3.2 — update title, re-save, same Google event
    echo "Block 3.2 (update, no duplicate)\n";

    try {
        $entity = $em->getEntityById($entityType, $id);

        if ($entity === null) {
            throw new RuntimeException('entity missing');
        }

        $entity->set('name', $nameV2);
        $em->saveEntity($entity);

        $links = $countActiveLinks($entityType, $id);
        $gidAfterUpdate = $fetchGoogleEventId($entityType, $id);

        $ok("{$entityType} 3.2 name updated in CRM", $entity->get('name') === $nameV2);
        $ok("{$entityType} 3.2 link count still 1", $links === 1, "links={$links}");
        $ok(
            "{$entityType} 3.2 same google_event_id",
            $gidAfterFirst !== null && $gidAfterUpdate === $gidAfterFirst,
            'before=' . substr((string) $gidAfterFirst, 0, 16) . ' after=' . substr((string) $gidAfterUpdate, 0, 16)
        );

        // Explicit second push (idempotency safety net — mirrors hook re-save)
        $entity = $em->getEntityById($entityType, $id);

        if ($entity !== null) {
            $eventPusher->pushIfRequested($entity, $admin);
            $linksAfterExplicit = $countActiveLinks($entityType, $id);
            $gidAfterExplicit = $fetchGoogleEventId($entityType, $id);
            $ok("{$entityType} 3.2 explicit re-push link count = 1", $linksAfterExplicit === 1);
            $ok(
                "{$entityType} 3.2 explicit re-push same gid",
                $gidAfterExplicit === $gidAfterFirst
            );
        }
    } catch (Throwable $e) {
        $ok("{$entityType} 3.2 update", false, $e->getMessage());
    }

    // 3.3 — delete from CRM
    echo "Block 3.3 (CRM delete)\n";

    try {
        $entity = $em->getEntityById($entityType, $id);

        if ($entity === null) {
            throw new RuntimeException('entity missing before delete');
        }

        $em->removeEntity($entity);
        $linksAfterDelete = $countActiveLinks($entityType, $id);
        $ok("{$entityType} 3.3 active links = 0", $linksAfterDelete === 0, "links={$linksAfterDelete}");
    } catch (Throwable $e) {
        $ok("{$entityType} 3.3 delete", false, $e->getMessage());
    }

    echo "\n";
}

echo "── Summary ──\n";
echo "  PASS: {$pass}\n";
echo "  FAIL: {$fail}\n";
echo "  Cleanup: ddev exec php bin/cleanup-gcal-e2e.php BLOCK3_\n\n";

if ($fail > 0) {
    echo "=== {$fail} FAILURE(S) ===\n";
    exit(1);
}

echo "=== ALL PASS (Block 3.1–3.3 Meeting + Call) ===\n";
exit(0);
