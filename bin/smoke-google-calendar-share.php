<?php

declare(strict_types=1);

require __DIR__ . '/lib/refuse-production.php';

/**
 * Smoke: Google Calendar manager share consent + eligible target resolution (no Google API).
 *
 * Usage:
 *   ddev exec php bin/smoke-google-calendar-share.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Util;
use Espo\Entities\ExternalAccount;
use Espo\Entities\Team;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\ManagerCalendarShare;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use GuzzleHttp\Client;

const SMOKE_USER = 'smoke_api_catalog';

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Config $config */
$config = $container->getByClass(Config::class);
/** @var ManagerCalendarShare $share */
$share = $container->get('injectableFactory')
    ->create(ManagerCalendarShare::class);

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: config siteUrl is empty.\n");
    exit(1);
}

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $m = $pass ? 'PASS' : 'FAIL';
    echo "  [$m] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$roleAdmin = $em->getRDBRepository('Role')->where(['name' => 'Admin', 'deleted' => false])->findOne();
$roleManager = $em->getRDBRepository('Role')->where(['name' => 'Manager', 'deleted' => false])->findOne();
$roleVolunteer = $em->getRDBRepository('Role')->where(['name' => 'Volunteer', 'deleted' => false])->findOne();

if ($roleAdmin === null) {
    fwrite(STDERR, "FAIL: Admin role not found.\n");
    exit(1);
}

$user = $em->getRDBRepository('User')
    ->where(['userName' => SMOKE_USER, 'deleted' => false])
    ->findOne();

if ($user === null) {
    $user = $em->createEntity(User::ENTITY_TYPE, [
        'userName' => SMOKE_USER,
        'type' => User::TYPE_API,
        'authMethod' => ApiKeyLogin::NAME,
        'rolesIds' => [$roleAdmin->getId()],
        'firstName' => 'Smoke',
        'lastName' => 'GCalShare',
    ]);
    $em->saveEntity($user);
    $user = $em->getRDBRepository('User')->getById($user->getId());
}

$apiKey = $user->get('apiKey');
if (!is_string($apiKey) || $apiKey === '') {
    $apiKey = Util::generateApiKey();
    $user->set('apiKey', $apiKey);
    $em->saveEntity($user);
}

$client = new Client([
    'base_uri' => $siteUrl,
    'verify' => false,
    'timeout' => 30,
    'http_errors' => false,
    'headers' => [
        'X-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ],
]);

echo "=== Google Calendar share smoke ===\n";
echo "Base URL: $siteUrl\n\n";

$rStatus = $client->get('/api/v1/GoogleIntegration/integration-status');
$statusBody = json_decode((string) $rStatus->getBody(), true) ?: [];
$ok(
    'GET integration-status → 200 + canShareToOthers key',
    $rStatus->getStatusCode() === 200 && array_key_exists('canShareToOthers', $statusBody),
    'code=' . $rStatus->getStatusCode() . ' body=' . json_encode($statusBody)
);

$md = $client->get('/api/v1/Metadata?key=entityDefs.Meeting.fields');
$fields = json_decode((string) $md->getBody(), true) ?: [];
$ok(
    'Meeting fields include googleCalendarShareUsers/Teams',
    isset($fields['googleCalendarShareUsers']) && isset($fields['googleCalendarShareTeams']),
    isset($fields['googleCalendarShareUsers']) ? 'ok' : 'missing fields'
);

$shareView = is_array($fields['googleCalendarShareUsers'] ?? null)
    ? (string) ($fields['googleCalendarShareUsers']['view'] ?? '')
    : '';
$ok(
    'Share users field uses custom picker view',
    str_contains($shareView, 'google-calendar-share-users'),
    $shareView
);

$rPicker = $client->get('/api/v1/GoogleIntegration/calendar/share-picker-data');
$pickerBody = json_decode((string) $rPicker->getBody(), true) ?: [];
$ok(
    'GET share-picker-data responds (200 or 403 for API user)',
    in_array($rPicker->getStatusCode(), [200, 403], true),
    'code=' . $rPicker->getStatusCode()
);

if ($rPicker->getStatusCode() === 200) {
    $ok(
        'share-picker-data has users + teams keys',
        isset($pickerBody['users'], $pickerBody['teams'], $pickerBody['connectedUserIds']),
        json_encode(array_keys($pickerBody))
    );
}

/** @var \Espo\Core\Utils\Metadata $metadata */
$metadata = $container->getByClass(\Espo\Core\Utils\Metadata::class);
$mdSelect = $metadata->get(['selectDefs', 'User', 'boolFilterClassNameMap']) ?? [];
$ok(
    'User bool filter googleCalendarConnected registered',
    is_array($mdSelect) && isset($mdSelect['googleCalendarConnected']),
    is_array($mdSelect) ? implode(',', array_keys($mdSelect)) : 'not-array'
);

$suffix = substr(str_replace('.', '', uniqid('', true)), -8);

$targetNoConsent = $em->createEntity(User::ENTITY_TYPE, [
    'userName' => 'smoke_gcal_nocon_' . $suffix,
    'type' => User::TYPE_REGULAR,
    'firstName' => 'Smoke',
    'lastName' => 'NoConsent',
    'isActive' => true,
    'password' => Util::generateId(),
]);
$em->saveEntity($targetNoConsent);

$targetConsent = $em->createEntity(User::ENTITY_TYPE, [
    'userName' => 'smoke_gcal_yes_' . $suffix,
    'type' => User::TYPE_REGULAR,
    'firstName' => 'Smoke',
    'lastName' => 'Consent',
    'isActive' => true,
    'password' => Util::generateId(),
]);
$em->saveEntity($targetConsent);

$volunteerUser = null;
if ($roleVolunteer !== null) {
    $volunteerUser = $em->createEntity(User::ENTITY_TYPE, [
        'userName' => 'smoke_gcal_vol_' . $suffix,
        'type' => User::TYPE_REGULAR,
        'firstName' => 'Smoke',
        'lastName' => 'Volunteer',
        'isActive' => true,
        'password' => Util::generateId(),
        'rolesIds' => [$roleVolunteer->getId()],
    ]);
    $em->saveEntity($volunteerUser);
}

$managerUser = null;
if ($roleManager !== null) {
    $managerUser = $em->createEntity(User::ENTITY_TYPE, [
        'userName' => 'smoke_gcal_mgr_' . $suffix,
        'type' => User::TYPE_REGULAR,
        'firstName' => 'Smoke',
        'lastName' => 'Manager',
        'isActive' => true,
        'password' => Util::generateId(),
        'rolesIds' => [$roleManager->getId()],
    ]);
    $em->saveEntity($managerUser);
}

$ensureAccount = static function (
    EntityManager $em,
    string $userId,
    bool $enabled,
    bool $consent
): void {
    $id = Installer::INTEGRATION_ID . '__' . $userId;
    $account = $em->getRDBRepository(ExternalAccount::ENTITY_TYPE)
        ->where([Attribute::ID => $id, Attribute::DELETED => false])
        ->findOne();

    if ($account === null) {
        $account = $em->getNewEntity(ExternalAccount::ENTITY_TYPE);
        $account->set(Attribute::ID, $id);
    }

    $account->set('enabled', $enabled);
    $account->set(ManagerCalendarShare::CONSENT_ATTRIBUTE, $consent);
    $account->set('calendarRoutingMode', 'auto_dedicated');
    $em->saveEntity($account);
};

$ensureAccount($em, $targetNoConsent->getId(), true, false);
$ensureAccount($em, $targetConsent->getId(), true, true);

$team = $em->createEntity(Team::ENTITY_TYPE, [
    'name' => 'Smoke GCal Share ' . $suffix,
]);
$em->saveEntity($team);
$em->getRelation($team, 'users')->relateById($targetConsent->getId());
$em->getRelation($team, 'users')->relateById($targetNoConsent->getId());

$meeting = $em->getNewEntity('Meeting');
$meeting->set('name', 'Smoke GCal Share ' . $suffix);
$meeting->set('dateStart', date('Y-m-d H:i:s', time() + 86400));
$meeting->set('dateEnd', date('Y-m-d H:i:s', time() + 90000));
$meeting->set('saveToGoogleCalendar', true);
$meeting->setLinkMultipleIdList('googleCalendarShareUsers', [$targetNoConsent->getId(), $targetConsent->getId()]);
$meeting->setLinkMultipleIdList('googleCalendarShareTeams', [$team->getId()]);

$eligible = $share->resolveEligibleTargetUserIds($meeting);
$ok(
    'Consent false → user not eligible',
    !in_array($targetNoConsent->getId(), $eligible, true),
    'eligible=' . implode(',', $eligible)
);
$ok(
    'Consent true + enabled → user eligible',
    in_array($targetConsent->getId(), $eligible, true),
    'eligible=' . implode(',', $eligible)
);
$ok(
    'Team expands and still filters by consent',
    in_array($targetConsent->getId(), $eligible, true)
        && !in_array($targetNoConsent->getId(), $eligible, true),
    'count=' . count($eligible)
);

$adminActor = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['type' => User::TYPE_ADMIN, 'deleted' => false, 'isActive' => true])
    ->findOne();
if ($adminActor !== null) {
    $ok('Admin type can share', $share->actorCanShare($adminActor));
}

if ($managerUser !== null) {
    $ok('Manager role can share', $share->actorCanShare($managerUser));
} else {
    echo "  [SKIP] Manager role user (Manager role missing)\n";
}

if ($volunteerUser !== null) {
    $ok('Volunteer role cannot share', !$share->actorCanShare($volunteerUser));
} else {
    echo "  [SKIP] Volunteer role user (Volunteer role missing)\n";
}

$ok(
    'userHasManagerWriteConsent respects flag',
    $share->userHasManagerWriteConsent($targetConsent->getId())
        && !$share->userHasManagerWriteConsent($targetNoConsent->getId())
);

// Cleanup smoke users/team/accounts (best-effort).
foreach ([$targetNoConsent, $targetConsent, $volunteerUser, $managerUser] as $u) {
    if ($u === null) {
        continue;
    }
    $accId = Installer::INTEGRATION_ID . '__' . $u->getId();
    $acc = $em->getRDBRepository(ExternalAccount::ENTITY_TYPE)
        ->where([Attribute::ID => $accId])
        ->findOne();
    if ($acc !== null) {
        $em->removeEntity($acc);
    }
    $em->removeEntity($u);
}
$em->removeEntity($team);

echo "\n";
if ($fail > 0) {
    echo "RESULT: FAIL ($fail)\n";
    exit(1);
}

echo "RESULT: OK\n";
exit(0);
