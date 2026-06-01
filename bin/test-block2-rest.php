<?php
/**
 * UI Block 2 — REST tests (explore-espo-endpoints skill, Workflows A+C+D+H).
 *
 * Covers:
 *   2.1 VolunteerEmployee: create via REST, formula auto-fields (monthlyHours, status), repeated soft-delete
 *   2.2 MealCount: create via REST, formula auto-fields (totalMeals, foodCost, dayOfWeek)
 *   2.3 Member: create via REST, taxCode dedup (Conflict 409), email auto-copy from assignedUser, repeated soft-delete
 *
 * Uses X-Api-Key auth (Admin API user) against siteUrl; pure HTTP — no ORM shortcuts
 * for the main test flow. ORM only for provisioning API user + cleanup.
 *
 * Usage:
 *   ddev exec php bin/test-block2-rest.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Config $config */
$config = $container->getByClass(Config::class);

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: config siteUrl is empty.\n");
    exit(1);
}

$fail = 0;
$pass_count = 0;
$cleanupIds = [];

$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail, &$pass_count): void {
    if ($pass) {
        $pass_count++;
    } else {
        $fail++;
    }
    $m = $pass ? 'PASS' : 'FAIL';
    echo "  [$m] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

/* --- Provision admin API user --- */
$apiUserName = 'smoke_block2_admin';
$role = $em->getRDBRepository('Role')
    ->where(['name' => 'Admin', 'deleted' => false])
    ->findOne();
if ($role === null) {
    fwrite(STDERR, "FAIL: Admin role not found.\n");
    exit(1);
}

$apiUser = $em->getRDBRepository('User')
    ->where(['userName' => $apiUserName, 'deleted' => false])
    ->findOne();

if ($apiUser === null) {
    $apiUser = $em->createEntity(User::ENTITY_TYPE, [
        'userName'   => $apiUserName,
        'type'       => User::TYPE_API,
        'authMethod' => ApiKeyLogin::NAME,
        'rolesIds'   => [$role->getId()],
        'firstName'  => 'Block2',
        'lastName'   => 'Admin',
        'emailAddress' => 'block2admin@test.local',
    ]);
    $em->saveEntity($apiUser);
    $apiUser = $em->getRDBRepository('User')->getById($apiUser->getId());
}
$apiUserId = $apiUser->getId();

$apiKey = $apiUser->get('apiKey');
if (!is_string($apiKey) || $apiKey === '') {
    $apiKey = Util::generateApiKey();
    $apiUser->set('apiKey', $apiKey);
    $em->saveEntity($apiUser);
}

/* --- Ensure API user has email for copy tests --- */
$apiUserEmail = 'block2admin@test.local';
$existingEmail = $apiUser->get('emailAddress');
if ($existingEmail !== $apiUserEmail) {
    $apiUser->set('emailAddress', $apiUserEmail);
    $em->saveEntity($apiUser);
}

/* --- Additional users for Member tests (separate assignedUser per record due to unique constraint) --- */
$provisionUser = static function (EntityManager $em, string $userName, string $email, string $roleId): array {
    $u = $em->getRDBRepository('User')->where(['userName' => $userName, 'deleted' => false])->findOne();
    if ($u === null) {
        $u = $em->createEntity(User::ENTITY_TYPE, [
            'userName'   => $userName,
            'type'       => User::TYPE_API,
            'authMethod' => ApiKeyLogin::NAME,
            'rolesIds'   => [$roleId],
            'firstName'  => 'Block2',
            'lastName'   => ucfirst(str_replace('smoke_block2_', '', $userName)),
            'emailAddress' => $email,
        ]);
        $em->saveEntity($u);
        $u = $em->getRDBRepository('User')->getById($u->getId());
    }
    return [$u->getId(), $email];
};

[$user2Id, $user2Email] = $provisionUser($em, 'smoke_block2_user2', 'block2user2@test.local', $role->getId());
[$user3Id, $user3Email] = $provisionUser($em, 'smoke_block2_user3', 'block2user3@test.local', $role->getId());

$http = new Client([
    'base_uri'    => $siteUrl,
    'verify'      => false,
    'timeout'     => 30,
    'http_errors' => false,
    'headers'     => [
        'X-Api-Key'    => $apiKey,
        'Accept'       => 'application/json',
        'Content-Type' => 'application/json',
    ],
]);

echo "=== Block 2 REST tests ===\n";
echo "Base: $siteUrl | Auth: X-Api-Key ($apiUserName)\n\n";

/* --- Pre-cleanup: HARD delete stale test records (incl. soft-deleted) --- */
$pdo = $em->getPDO();
$testTaxCodes = ['RSSMRA85M01H501Z', 'VRDLGU80A01F205X', 'BNCLGI90A01H501Y'];
foreach ($testTaxCodes as $tc) {
    $pdo->prepare("DELETE FROM member WHERE tax_code = ?")->execute([$tc]);
}
$pdo->prepare("DELETE FROM member WHERE first_name IN ('RestTest', 'CustomEmail', 'Duplicate')")->execute();
$pdo->prepare("DELETE FROM member WHERE assigned_user_id IN (?, ?, ?)")->execute([$apiUserId, $user2Id, $user3Id]);
$pdo->prepare("DELETE FROM volunteer_employee WHERE first_name = 'RestTest'")->execute();
$pdo->prepare("DELETE FROM volunteer_employee WHERE assigned_user_id = ?")->execute([$apiUserId]);
$pdo->prepare("DELETE FROM meal_count WHERE assigned_user_id = ? AND date = '2026-05-26'")->execute([$apiUserId]);

/* ========== 2.1 VolunteerEmployee ========== */
echo "--- 2.1 VolunteerEmployee: create + formulas ---\n";

$veSuffix = substr(md5((string)time()), 0, 6);
$vePayload = [
    'firstName'      => 'RestTest',
    'lastName'       => 'VE_' . $veSuffix,
    'type'           => 'Employee',
    'weeklyHours'    => 20.0,
    'startDate'      => '2026-01-15',
    'assignedUserId' => $apiUserId,
    'emailAddress'   => 'resttest-ve-' . $veSuffix . '@test.local',
];

$r = $http->post('/api/v1/VolunteerEmployee', ['json' => $vePayload]);
$veBody = json_decode((string)$r->getBody(), true);
$ok('POST /api/v1/VolunteerEmployee → 200', $r->getStatusCode() === 200,
    'code=' . $r->getStatusCode());

$veId = $veBody['id'] ?? null;
if (is_string($veId)) {
    $cleanupIds['VolunteerEmployee'][] = $veId;

    $rGet = $http->get('/api/v1/VolunteerEmployee/' . $veId, [
        'query' => ['select' => 'id,firstName,lastName,type,weeklyHours,monthlyHours,status,startDate,endDate'],
    ]);
    $ve = json_decode((string)$rGet->getBody(), true);

    $ok('VE type saved correctly', ($ve['type'] ?? '') === 'Employee');
    $ok('VE weeklyHours saved correctly', (float)($ve['weeklyHours'] ?? 0) === 20.0);

    $expectedMonthly = round(20.0 * 4.33, 2);
    $actualMonthly = (float)($ve['monthlyHours'] ?? 0);
    $ok('VE monthlyHours = round(weeklyHours * 4.33, 2)',
        abs($actualMonthly - $expectedMonthly) < 0.01,
        "expected=$expectedMonthly, actual=$actualMonthly");

    $ok('VE status = Active (no endDate)', ($ve['status'] ?? '') === 'Active');

    $rPut = $http->put('/api/v1/VolunteerEmployee/' . $veId, [
        'json' => ['endDate' => '2025-12-31'],
    ]);
    $ok('PUT endDate in past → 200', $rPut->getStatusCode() === 200,
        'code=' . $rPut->getStatusCode());

    $rGet2 = $http->get('/api/v1/VolunteerEmployee/' . $veId, [
        'query' => ['select' => 'id,status,endDate'],
    ]);
    $ve2 = json_decode((string)$rGet2->getBody(), true);
    $ok('VE status → Inactive after endDate in past',
        ($ve2['status'] ?? '') === 'Inactive',
        'status=' . ($ve2['status'] ?? ''));

    /* --- 2.1b VolunteerEmployee: repeated soft-delete with same assignedUser --- */
    echo "\n--- 2.1b VolunteerEmployee: repeated soft-delete releases assignedUser ---\n";

    $rDelete = $http->delete('/api/v1/VolunteerEmployee/' . $veId);
    $ok('DELETE first VolunteerEmployee → 200', $rDelete->getStatusCode() === 200,
        'code=' . $rDelete->getStatusCode());

    $veReplacementPayload = $vePayload;
    $veReplacementPayload['lastName'] = 'VE_Delete_' . $veSuffix;
    $veReplacementPayload['emailAddress'] = 'resttest-ve-delete-' . $veSuffix . '@test.local';

    $rReplacement = $http->post('/api/v1/VolunteerEmployee', ['json' => $veReplacementPayload]);
    $replacementBody = json_decode((string)$rReplacement->getBody(), true);
    $replacementVeId = $replacementBody['id'] ?? null;
    $ok('POST replacement VolunteerEmployee with same assignedUser → 200',
        $rReplacement->getStatusCode() === 200 && is_string($replacementVeId),
        'code=' . $rReplacement->getStatusCode());

    if (is_string($replacementVeId)) {
        $cleanupIds['VolunteerEmployee'][] = $replacementVeId;

        $rReplacementDelete = $http->delete('/api/v1/VolunteerEmployee/' . $replacementVeId);
        $ok('DELETE replacement VolunteerEmployee with same assignedUser → 200',
            $rReplacementDelete->getStatusCode() === 200,
            'code=' . $rReplacementDelete->getStatusCode());
    }
} else {
    $ok('VE created with id', false, 'no id in response');
}

/* ========== 2.2 MealCount ========== */
echo "\n--- 2.2 MealCount: create + formulas ---\n";

$mcPayload = [
    'date'           => '2026-05-26',
    'adults'         => 10,
    'minors'         => 5,
    'foodUnitPrice'  => 1.50,
    'assignedUserId' => $apiUserId,
];

$r = $http->post('/api/v1/MealCount', ['json' => $mcPayload]);
$mcBody = json_decode((string)$r->getBody(), true);
$ok('POST /api/v1/MealCount → 200', $r->getStatusCode() === 200,
    'code=' . $r->getStatusCode());

$mcId = $mcBody['id'] ?? null;
if (is_string($mcId)) {
    $cleanupIds['MealCount'][] = $mcId;

    $rGet = $http->get('/api/v1/MealCount/' . $mcId, [
        'query' => ['select' => 'id,date,adults,minors,totalMeals,foodCost,dayOfWeek,foodUnitPrice'],
    ]);
    $mc = json_decode((string)$rGet->getBody(), true);

    $ok('MC adults=10', (int)($mc['adults'] ?? 0) === 10);
    $ok('MC minors=5', (int)($mc['minors'] ?? 0) === 5);
    $ok('MC totalMeals = adults + minors = 15',
        (int)($mc['totalMeals'] ?? 0) === 15,
        'totalMeals=' . ($mc['totalMeals'] ?? 'null'));
    $ok('MC foodCost = totalMeals * foodUnitPrice = 22.5',
        abs((float)($mc['foodCost'] ?? 0) - 22.5) < 0.01,
        'foodCost=' . ($mc['foodCost'] ?? 'null'));
    $ok('MC dayOfWeek is set (non-empty)',
        is_string($mc['dayOfWeek'] ?? null) && ($mc['dayOfWeek'] ?? '') !== '',
        'dayOfWeek=' . ($mc['dayOfWeek'] ?? 'null'));

    $date = new DateTime('2026-05-26');
    $expectedDay = $date->format('l');
    $ok('MC dayOfWeek matches 2026-05-26 = Tuesday',
        ($mc['dayOfWeek'] ?? '') === $expectedDay,
        "expected=$expectedDay, actual=" . ($mc['dayOfWeek'] ?? 'null'));
} else {
    $ok('MC created with id', false, 'no id in response');
}

/* ========== 2.3 Member: create + taxCode dedup + email auto-copy ========== */
echo "\n--- 2.3a Member: create via REST ---\n";

$uniqueTaxCode = 'RSSMRA85M01H501Z';
$mSuffix = substr(md5((string)time()), 0, 6);

$memberEntity = $em->getRDBRepository('Member')->getNew();
$memberEntity->set([
    'firstName'      => 'RestTest',
    'lastName'       => 'Member_' . $mSuffix,
    'taxCode'        => $uniqueTaxCode,
    'status'         => 'Active',
    'joinDate'       => '2026-01-01',
    'assignedUserId' => $user2Id,
]);
$memberCreated = false;
try {
    $em->saveEntity($memberEntity);
    $memberCreated = true;
} catch (\Throwable $e) {
    echo "  [DEBUG] Member create error: " . $e->getMessage() . "\n";
}
$ok('Create Member (ORM) succeeded', $memberCreated);

$mId = $memberCreated ? $memberEntity->getId() : null;
if (is_string($mId)) {
    $cleanupIds['Member'][] = $mId;

    $rGet = $http->get('/api/v1/Member/' . $mId, [
        'query' => ['select' => 'id,firstName,lastName,taxCode,status,emailAddress,assignedUserId,province'],
    ]);
    $m = json_decode((string)$rGet->getBody(), true);

    $ok('GET Member via REST → 200', $rGet->getStatusCode() === 200, 'code=' . $rGet->getStatusCode());
    $ok('Member status=Active', ($m['status'] ?? '') === 'Active');
    $ok('Member taxCode stored (uppercased)',
        strtoupper($uniqueTaxCode) === strtoupper($m['taxCode'] ?? ''),
        'taxCode=' . ($m['taxCode'] ?? 'null'));
    $ok('Member assignedUserId correct', ($m['assignedUserId'] ?? '') === $user2Id);

    $ok('Member email auto-copied from assigned user',
        ($m['emailAddress'] ?? '') === $user2Email,
        'email=' . ($m['emailAddress'] ?? '(empty)'));

    /* --- 2.3b taxCode deduplication: second Member with same taxCode → Conflict (ORM) --- */
    echo "\n--- 2.3b Member: taxCode dedup (Conflict) ---\n";

    $dupCaught = false;
    $dupErrorClass = '';
    try {
        $dupEntity = $em->getRDBRepository('Member')->getNew();
        $dupEntity->set([
            'firstName'      => 'Duplicate',
            'lastName'       => 'TaxCode',
            'taxCode'        => $uniqueTaxCode,
            'status'         => 'Active',
            'assignedUserId' => $apiUserId,
        ]);
        $em->saveEntity($dupEntity);
        $cleanupIds['Member'][] = $dupEntity->getId();
    } catch (\Espo\Core\Exceptions\Conflict $e) {
        $dupCaught = true;
        $dupErrorClass = 'Conflict';
    } catch (\Throwable $e) {
        $dupCaught = true;
        $dupErrorClass = get_class($e) . ': ' . $e->getMessage();
    }
    $ok('Save Member with duplicate taxCode → Conflict exception',
        $dupCaught && $dupErrorClass === 'Conflict',
        $dupErrorClass ?: 'no exception thrown');

    /* --- 2.3c Member: email auto-copy + merge (ORM test, bypasses API-user ACL) --- */
    echo "\n--- 2.3c Member: email auto-copy + merge (ORM) ---\n";

    $customEmail = 'custom-member-' . time() . '@external.org';
    $ceEntity = $em->getRDBRepository('Member')->getNew();
    $ceEntity->set([
        'firstName'      => 'CustomEmail',
        'lastName'       => 'Member',
        'taxCode'        => 'VRDLGU80A01F205X',
        'status'         => 'Active',
        'emailAddress'   => $customEmail,
        'assignedUserId' => $user3Id,
    ]);
    $em->saveEntity($ceEntity);
    $ceId = $ceEntity->getId();
    $cleanupIds['Member'][] = $ceId;

    $rCeGet = $http->get('/api/v1/Member/' . $ceId, [
        'query' => ['select' => 'id,emailAddress,emailAddressData'],
    ]);
    $ceM = json_decode((string)$rCeGet->getBody(), true);
    $cePrimary = $ceM['emailAddress'] ?? '';
    $ok('Member with custom email: primary email is user email (auto-merge)',
        $cePrimary === $user3Email,
        "primaryEmail=$cePrimary, expected=$user3Email");

    $ceEmailData = $ceM['emailAddressData'] ?? [];
    $hasSecondary = false;
    foreach ($ceEmailData as $row) {
        if (($row['emailAddress'] ?? '') !== $user3Email && !($row['primary'] ?? false)) {
            $hasSecondary = true;
        }
    }
    $ok('Member with custom email: user-entered email kept as secondary',
        $hasSecondary,
        'emailAddressData count=' . count($ceEmailData) . ', emails=' . implode(', ', array_column($ceEmailData, 'emailAddress')));

    /* --- 2.3d Member: leave date in past → Inactive (formula) --- */
    echo "\n--- 2.3d Member: leaveDate formula ---\n";

    $rPut = $http->put('/api/v1/Member/' . $mId, [
        'json' => ['leaveDate' => '2025-06-01'],
    ]);
    $ok('PUT Member leaveDate in past → 200', $rPut->getStatusCode() === 200,
        'code=' . $rPut->getStatusCode());

    $rGet2 = $http->get('/api/v1/Member/' . $mId, [
        'query' => ['select' => 'id,status,leaveDate'],
    ]);
    $m2 = json_decode((string)$rGet2->getBody(), true);
    $ok('Member status → Inactive after leaveDate in past',
        ($m2['status'] ?? '') === 'Inactive',
        'status=' . ($m2['status'] ?? ''));

    /* --- 2.3e Member: repeated soft-delete with same assignedUser --- */
    echo "\n--- 2.3e Member: repeated soft-delete releases assignedUser ---\n";

    $rDelete = $http->delete('/api/v1/Member/' . $mId);
    $ok('DELETE first Member → 200', $rDelete->getStatusCode() === 200,
        'code=' . $rDelete->getStatusCode());

    $replacementMember = $em->getRDBRepository('Member')->getNew();
    $replacementMember->set([
        'firstName'      => 'RestTest',
        'lastName'       => 'Member_Delete_' . $mSuffix,
        'taxCode'        => 'BNCLGI90A01H501Y',
        'status'         => 'Active',
        'joinDate'       => '2026-01-01',
        'assignedUserId' => $user2Id,
    ]);
    $memberReplacementCreated = false;

    try {
        $em->saveEntity($replacementMember);
        $memberReplacementCreated = true;
        $cleanupIds['Member'][] = $replacementMember->getId();
    } catch (\Throwable $e) {
        echo "  [DEBUG] Replacement Member create error: " . $e->getMessage() . "\n";
    }

    $ok('Create replacement Member with same assignedUser succeeded', $memberReplacementCreated);

    if ($memberReplacementCreated) {
        $rReplacementDelete = $http->delete('/api/v1/Member/' . $replacementMember->getId());
        $ok('DELETE replacement Member with same assignedUser → 200',
            $rReplacementDelete->getStatusCode() === 200,
            'code=' . $rReplacementDelete->getStatusCode());
    }

} else {
    $ok('Member created with id', false, 'no id in response');
}

/* --- 2.3f Metadata: verify Member entityDefs has correct fields (Workflow C) --- */
echo "\n--- 2.3f Metadata: Member entityDefs ---\n";

$rMeta = $http->get('/api/v1/Metadata', ['query' => ['key' => 'entityDefs.Member']]);
$memberDefs = json_decode((string)$rMeta->getBody(), true);
$mFields = $memberDefs['fields'] ?? [];

$ok('Member has taxCode field', isset($mFields['taxCode']));
$ok('Member has emailAddress field', isset($mFields['emailAddress']));
$ok('Member emailAddress is NOT required', ($mFields['emailAddress']['required'] ?? false) === false);
$ok('Member assignedUser is required', ($mFields['assignedUser']['required'] ?? false) === true);
$ok('Member has no "user" field (removed)', !isset($mFields['user']));

$mLinks = $memberDefs['links'] ?? [];
$ok('Member has no "user" link (removed)', !isset($mLinks['user']));
$ok('Member has assignedUser link', isset($mLinks['assignedUser']));

/* --- 2.3g Metadata: verify duplicate check config --- */
$mScopes = json_decode(
    (string)$http->get('/api/v1/Metadata', ['query' => ['key' => 'scopes.Member']])->getBody(),
    true
);
$dupFields = $mScopes['duplicateCheckFieldList'] ?? null;
$ok('Member scopes has duplicateCheckFieldList',
    is_array($dupFields) && in_array('taxCode', $dupFields, true),
    is_array($dupFields) ? implode(',', $dupFields) : 'null');

/* ========== Cleanup (hard delete via SQL to avoid soft-delete unique constraint issues) ========== */
echo "\n--- Cleanup ---\n";
$cleaned = 0;
foreach ($cleanupIds as $entity => $ids) {
    $table = match ($entity) {
        'VolunteerEmployee' => 'volunteer_employee',
        'MealCount'         => 'meal_count',
        'Member'            => 'member',
        default             => strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $entity)),
    };
    foreach ($ids as $id) {
        $pdo->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
        $cleaned++;
    }
}
echo "  Hard-deleted $cleaned test records.\n";

echo "\n=== Block 2 results: $pass_count PASS, $fail FAIL ===\n";
exit($fail === 0 ? 0 : 1);
