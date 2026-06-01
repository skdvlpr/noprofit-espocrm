<?php

/**
 * Block 4 — Opportunity (F&F) Google Calendar per-date lifecycle.
 *
 * 4.1 — create with one date (presentationDate) → 1 link + Google event
 * 4.2 — edit: add closeDate to googleCalendarDateSourceList → 2 links (idempotent)
 * 4.3 — edit: change name with & → same google_event_ids, summary decoded in Google
 * 4.4 — CRM delete → 0 active links
 *
 * Usage:
 *   ddev exec php bin/test-block4-gcal-opportunity.php
 *   ddev exec php bin/cleanup-gcal-e2e.php BLOCK4_
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google as GoogleClient;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarPlainText;
use Espo\Modules\GoogleIntegration\Tools\Installer;
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

$tag = 'BLOCK4_' . gmdate('Ymd_His');
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

$countLinks = function (string $oppId) use ($pdo, $adminId): int {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND user_id = ? AND deleted = 0'
    );
    $stmt->execute(['Opportunity', $oppId, $adminId]);

    return (int) $stmt->fetchColumn();
};

$linkForType = function (string $oppId, string $dateType) use ($pdo, $adminId): ?array {
    $stmt = $pdo->prepare(
        'SELECT id, google_event_id FROM google_calendar_event_link
         WHERE source_entity_type = ? AND source_entity_id = ? AND source_date_type = ?
           AND user_id = ? AND deleted = 0 LIMIT 1'
    );
    $stmt->execute(['Opportunity', $oppId, $dateType, $adminId]);
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

echo "=== Block 4 Opportunity Google Calendar E2E ===\n";
echo "Tag: {$tag}\n\n";

// Plain-text unit check
$ok(
    'GoogleCalendarPlainText decodes &amp;',
    GoogleCalendarPlainText::normalize('Test F&amp;F') === 'Test F&F'
);

$account = $em->getRDBRepository('Account')
    ->where(['name' => 'Test SRL', 'deleted' => false])
    ->findOne();

if ($account === null) {
    $account = $em->getNewEntity('Account');
    $account->set('name', 'Test SRL');
    $em->saveEntity($account);
}

$presDate = (new DateTimeImmutable('+3 days'))->format('Y-m-d');
$closeDate = (new DateTimeImmutable('+30 days'))->format('Y-m-d');

echo "4.1 Create with presentationDate only\n";

$opp = $em->getNewEntity('Opportunity');
$opp->set([
    'name' => $tag . ' Test F&F',
    'accountId' => $account->getId(),
    'stage' => 'Preparation',
    'amount' => 1488,
    'amountCurrency' => 'EUR',
    'presentationDate' => $presDate,
    'closeDate' => $closeDate,
    'saveToGoogleCalendar' => true,
    'googleCalendarDateSourceList' => ['presentationDate'],
    'googleCalendarEventSettings' => [[
        'sourceDateType' => 'presentationDate',
        'reminderMode' => 'none',
        'reminders' => [],
        'location' => 'Torino, Italy',
        'visibility' => 'default',
        'transparency' => 'opaque',
        'colorId' => '',
        'calendarTemplateId' => '',
        'descriptionTemplateOverride' => '',
    ]],
    'assignedUserId' => $adminId,
]);
$em->saveEntity($opp);
$oppId = $opp->getId();

$links1 = $countLinks($oppId);
$presLink = $linkForType($oppId, 'presentationDate');

$ok('4.1 one active link', $links1 === 1, 'links=' . $links1);
$ok('4.1 presentationDate link exists', $presLink !== null && $presLink['google_event_id'] !== '');

$presGid = $presLink['google_event_id'] ?? '';
$summary1 = $presGid !== '' ? $fetchGoogleSummary($presGid) : null;

$ok(
    '4.1 Google summary contains & not &amp;',
    is_string($summary1)
        && str_contains($summary1, 'F&F')
        && !str_contains($summary1, '&amp;'),
    'summary=' . ($summary1 ?? 'null')
);

echo "\n4.2 Edit — add closeDate to Google date list\n";

$opp = $em->getEntityById('Opportunity', $oppId);

if ($opp === null) {
    fwrite(STDERR, "FAIL: opportunity lost\n");
    exit(1);
}

$opp->set([
    'googleCalendarDateSourceList' => ['presentationDate', 'closeDate'],
    'googleCalendarEventSettings' => [
        [
            'sourceDateType' => 'presentationDate',
            'reminderMode' => 'none',
            'reminders' => [],
            'location' => 'Torino, Italy',
            'visibility' => 'default',
            'transparency' => 'opaque',
            'colorId' => '',
            'calendarTemplateId' => '',
            'descriptionTemplateOverride' => '',
        ],
        [
            'sourceDateType' => 'closeDate',
            'reminderMode' => 'none',
            'reminders' => [],
            'location' => 'Torino, Italy',
            'visibility' => 'default',
            'transparency' => 'opaque',
            'colorId' => '',
            'calendarTemplateId' => '',
            'descriptionTemplateOverride' => '',
        ],
    ],
]);
$em->saveEntity($opp);

$links2 = $countLinks($oppId);
$closeLink = $linkForType($oppId, 'closeDate');
$presLink2 = $linkForType($oppId, 'presentationDate');

$ok('4.2 two active links after adding closeDate', $links2 === 2, 'links=' . $links2);
$ok('4.2 closeDate link created', $closeLink !== null && $closeLink['google_event_id'] !== '');
$ok(
    '4.2 presentationDate same google_event_id',
    $presLink2 !== null && ($presLink2['google_event_id'] ?? '') === $presGid,
    'before=' . $presGid . ' after=' . ($presLink2['google_event_id'] ?? '')
);

echo "\n4.3 Update name (ampersand preserved)\n";

$oppFresh = $em->getEntityById('Opportunity', $oppId);
$oppFresh->set('name', $tag . ' Test F&F updated');
$em->saveEntity($oppFresh);

$summary3 = $fetchGoogleSummary($presGid);
$ok(
    '4.3 Google summary updated without &amp;',
    is_string($summary3)
        && str_contains($summary3, 'F&F updated')
        && !str_contains($summary3, '&amp;'),
    'summary=' . ($summary3 ?? 'null')
);

echo "\n4.4 CRM delete\n";

$oppDel = $em->getEntityById('Opportunity', $oppId);

if ($oppDel !== null) {
    $em->removeEntity($oppDel);
}

$ok('4.4 zero active links', $countLinks($oppId) === 0);

echo "\n=== Summary: PASS {$pass}, FAIL {$fail} ===\n";
echo "Cleanup: ddev exec php bin/cleanup-gcal-e2e.php BLOCK4_\n\n";

exit($fail > 0 ? 1 : 0);
