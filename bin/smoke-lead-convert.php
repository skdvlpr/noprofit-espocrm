<?php
/**
 * Smoke: Lead convert flows + Volunteer ACL (Task 7.1.3).
 *
 * Hygiene: deletes prior QA-Lead-Convert-* seeds and linked convert artifacts
 * before creating fresh rows. Leaves new rows for manual user review.
 *
 * Usage:
 *   ddev exec php bin/smoke-lead-convert.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Tools\RoleSetup;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;

const SMOKE_USER_ADMIN = 'smoke_api_catalog';
const SMOKE_USER_VOLUNTEER = 'smoke_api_volunteer';
const LEAD_PREFIX = 'QA-Lead-Convert-';
const CONTACT_PREFIX = 'QA-Contact-Convert-';
const ACCOUNT_PREFIX = 'QA-Account-Convert-';
const OPPORTUNITY_PREFIX = 'QA-Opportunity-Convert-';

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Config $config */
$config = $container->getByClass(Config::class);
$convertCurrency = (string) ($config->get('defaultCurrency') ?? 'USD');

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $m = $pass ? 'PASS' : 'FAIL';
    echo "  [$m] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$roleSetup = $container->getByClass(\Espo\Core\InjectableFactory::class)
    ->create(RoleSetup::class);
$roleSetup->provisionRoles();
$config->update();

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: siteUrl empty\n");
    exit(1);
}

/**
 * @return array{0: User, 1: string}
 */
$provisionApiUser = static function (
    EntityManager $em,
    string $userName,
    string $roleName,
    string $first,
    string $last
): array {
    $role = $em->getRDBRepository('Role')
        ->where(['name' => $roleName, 'deleted' => false])
        ->findOne();
    if ($role === null) {
        throw new RuntimeException("Role not found: $roleName");
    }

    $user = $em->getRDBRepository('User')
        ->where(['userName' => $userName, 'deleted' => false])
        ->findOne();

    if ($user === null) {
        $user = $em->createEntity(User::ENTITY_TYPE, [
            'userName' => $userName,
            'type' => User::TYPE_API,
            'authMethod' => ApiKeyLogin::NAME,
            'rolesIds' => [$role->getId()],
            'firstName' => $first,
            'lastName' => $last,
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

    return [$user, $apiKey];
};

[$adminUser, $adminKey] = $provisionApiUser(
    $em,
    SMOKE_USER_ADMIN,
    'Admin',
    'Smoke',
    'LeadConvert'
);
[$volunteerUser, $volunteerKey] = $provisionApiUser(
    $em,
    SMOKE_USER_VOLUNTEER,
    'Volunteer',
    'Smoke',
    'VolunteerLead'
);

$adminClient = new Client([
    'base_uri' => $siteUrl . '/api/v1/',
    'headers' => ['X-Api-Key' => $adminKey, 'Content-Type' => 'application/json'],
    'http_errors' => false,
    'timeout' => 60,
]);
$volunteerClient = new Client([
    'base_uri' => $siteUrl . '/api/v1/',
    'headers' => ['X-Api-Key' => $volunteerKey, 'Content-Type' => 'application/json'],
    'http_errors' => false,
    'timeout' => 60,
]);

/**
 * @return list<array<string, mixed>>
 */
$fetchList = static function (Client $client, string $entity, string $prefix): array {
    $r = $client->get($entity, [
        'query' => ['select' => 'id,name,firstName,lastName', 'maxSize' => 200],
    ]);
    $body = json_decode((string) $r->getBody(), true) ?: [];
    $rows = is_array($body['list'] ?? null) ? $body['list'] : [];
    $out = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = (string) ($row['name'] ?? $row['firstName'] ?? '');
        if (str_starts_with($label, $prefix)) {
            $out[] = $row;
        }
    }

    return $out;
};

echo "Cleanup prior convert QA rows\n";
$removed = 0;
foreach (
    [
        ['Opportunity', OPPORTUNITY_PREFIX],
        ['Contact', CONTACT_PREFIX],
        ['Account', ACCOUNT_PREFIX],
        ['Lead', LEAD_PREFIX],
    ] as [$entity, $prefix]
) {
    foreach ($fetchList($adminClient, $entity, $prefix) as $row) {
        $id = $row['id'] ?? null;
        if (!is_string($id) || $id === '') {
            continue;
        }
        $adminClient->delete($entity . '/' . $id);
        $removed++;
    }
}
$ok('Cleanup convert QA artifacts', true, "removed=$removed");

/**
 * @return ?string
 */
$createLead = static function (Client $client, string $suffix) use ($ok): ?string {
    $first = LEAD_PREFIX . $suffix;
    $r = $client->post('Lead', [
        'json' => [
            'firstName' => $first,
            'lastName' => 'Convert',
            'status' => 'New',
            'source' => 'Web Site',
        ],
    ]);
    $body = json_decode((string) $r->getBody(), true) ?: [];
    $id = is_string($body['id'] ?? null) ? $body['id'] : null;

    return $r->getStatusCode() === 200 ? $id : null;
};

/**
 * @return array{code: int, body: array<string, mixed>}
 */
$convertLead = static function (
    Client $client,
    string $leadId,
    array $records,
    bool $skipDuplicateCheck = true
): array {
    $r = $client->post('Lead/action/convert', [
        'json' => [
            'id' => $leadId,
            'records' => (object) $records,
            'skipDuplicateCheck' => $skipDuplicateCheck,
        ],
    ]);

    return [
        'code' => $r->getStatusCode(),
        'body' => json_decode((string) $r->getBody(), true) ?: [],
        'reason' => $r->getHeaderLine('X-Status-Reason'),
    ];
};

echo "\nConvert: Contact only\n";
$leadContactId = $createLead($adminClient, 'contact');
$contactResult = $leadContactId
    ? $convertLead($adminClient, $leadContactId, [
        'Contact' => [
            'firstName' => CONTACT_PREFIX . 'contact',
            'lastName' => 'Only',
        ],
    ])
    : ['code' => 0, 'body' => [], 'reason' => 'no-lead'];
$ok(
    'Convert Lead → Contact only',
    $contactResult['code'] === 200
        && ($contactResult['body']['status'] ?? '') === 'Converted'
        && is_string($contactResult['body']['createdContactId'] ?? null),
    'code=' . $contactResult['code'] . ' reason=' . ($contactResult['reason'] ?? '')
);

echo "\nConvert: Account only\n";
$leadAccountId = $createLead($adminClient, 'account');
$accountResult = $leadAccountId
    ? $convertLead($adminClient, $leadAccountId, [
        'Account' => [
            'name' => ACCOUNT_PREFIX . 'account',
        ],
    ])
    : ['code' => 0, 'body' => [], 'reason' => 'no-lead'];
$ok(
    'Convert Lead → Account only',
    $accountResult['code'] === 200
        && ($accountResult['body']['status'] ?? '') === 'Converted'
        && is_string($accountResult['body']['createdAccountId'] ?? null),
    'code=' . $accountResult['code'] . ' reason=' . ($accountResult['reason'] ?? '')
);

echo "\nConvert: Opportunity only (Fondi e Finanziamenti)\n";
$leadOppId = $createLead($adminClient, 'opportunity');
$oppResult = $leadOppId
    ? $convertLead($adminClient, $leadOppId, [
        'Opportunity' => [
            'name' => OPPORTUNITY_PREFIX . 'opportunity',
            'stage' => 'Preparation',
            'amount' => 1000,
            'amountCurrency' => $convertCurrency,
            'closeDate' => gmdate('Y-m-d', strtotime('+30 days')),
        ],
    ])
    : ['code' => 0, 'body' => [], 'reason' => 'no-lead'];
$ok(
    'Convert Lead → Opportunity only',
    $oppResult['code'] === 200
        && ($oppResult['body']['status'] ?? '') === 'Converted'
        && is_string($oppResult['body']['createdOpportunityId'] ?? null),
    'code=' . $oppResult['code'] . ' reason=' . ($oppResult['reason'] ?? '')
);

echo "\nConvert: Contact + Account + Opportunity\n";
$leadAllId = $createLead($adminClient, 'all');
$allResult = $leadAllId
    ? $convertLead($adminClient, $leadAllId, [
        'Contact' => [
            'firstName' => CONTACT_PREFIX . 'all',
            'lastName' => 'Triple',
        ],
        'Account' => [
            'name' => ACCOUNT_PREFIX . 'all',
        ],
        'Opportunity' => [
            'name' => OPPORTUNITY_PREFIX . 'all',
            'stage' => 'Preparation',
            'amount' => 2500,
            'amountCurrency' => $convertCurrency,
            'closeDate' => gmdate('Y-m-d', strtotime('+45 days')),
        ],
    ])
    : ['code' => 0, 'body' => [], 'reason' => 'no-lead'];
$ok(
    'Convert Lead → Contact + Account + Opportunity',
    $allResult['code'] === 200
        && ($allResult['body']['status'] ?? '') === 'Converted'
        && is_string($allResult['body']['createdContactId'] ?? null)
        && is_string($allResult['body']['createdAccountId'] ?? null)
        && is_string($allResult['body']['createdOpportunityId'] ?? null),
    'code=' . $allResult['code'] . ' reason=' . ($allResult['reason'] ?? '')
);

echo "\nACL: Volunteer cannot read Lead\n";
$volLeadList = $volunteerClient->get('Lead', [
    'query' => ['select' => 'id', 'maxSize' => 5],
]);
$volBody = json_decode((string) $volLeadList->getBody(), true) ?: [];
$volList = is_array($volBody['list'] ?? null) ? $volBody['list'] : [];
$ok(
    'Volunteer GET Lead list blocked or empty',
    $volLeadList->getStatusCode() === 403 || $volList === [],
    'code=' . $volLeadList->getStatusCode()
);

if ($leadAllId !== null) {
    $volGet = $volunteerClient->get('Lead/' . $leadAllId, [
        'query' => ['select' => 'id,status'],
    ]);
    $ok(
        'Volunteer GET converted Lead by id → 403',
        $volGet->getStatusCode() === 403,
        'code=' . $volGet->getStatusCode()
    );
}

echo "\n=== ";
echo $fail === 0 ? 'ALL PASS' : ($fail . ' FAILURE(S)');
echo " ===\n";
echo "Manual QA: open converted Lead(s), verify «Convertito in» links to Contact/Account/Opportunity.\n";
echo "QA prefixes kept: " . LEAD_PREFIX . "*, " . CONTACT_PREFIX . "*, " . ACCOUNT_PREFIX . "*, " . OPPORTUNITY_PREFIX . "*\n";
exit($fail === 0 ? 0 : 1);
