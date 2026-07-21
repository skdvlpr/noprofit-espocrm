<?php
/**
 * REST smoke aligned with skill `explore-espo-endpoints` (Workflow A + D + ACL probes).
 *
 * 1) Admin API user (`smoke_api_catalog`): catalog, metadata, list routes, schema checks,
 *    `GoogleCalendarDrive` extension metadata (universal Google OAuth2) + ORM DB row check;
 *    `GET Integration/...` is not asserted for API users (Espo: admin UI only, type=admin).
 * 2) Volunteer API user (`smoke_api_volunteer`): `read=own` IDOR (VolunteerEmployee),
 *    `Member` blocked (`read=no`), `MealCount` foreign assignee → 403.
 *
 * Provisions idempotent API users with `X-Api-Key` auth (Workflow E). Ensures the
 * Volunteer user has a linked `VolunteerEmployee` profile (same pattern as
 * `RoleSetup::provisionTestProfiles()`).
 *
 * Usage:
 *   ddev exec php bin/smoke-espo-rest-catalog.php
 *
 * Requires `siteUrl` in Espo config to be reachable from the web container
 * (DDEV: https://<project>.ddev.site).
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\GoogleIntegration\Tools\Installer as GoogleIntegrationInstaller;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

const SMOKE_USER_ADMIN      = 'smoke_api_catalog';
const SMOKE_USER_VOLUNTEER = 'smoke_api_volunteer';

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Config $config */
$config = $container->getByClass(Config::class);

(new GoogleIntegrationInstaller())->runPostInstall($container);
$config->update();

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: config siteUrl is empty — set Site URL in Admin → Settings.\n");
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
    $roleId = $role->getId();

    /** @var ?User $user */
    $user = $em->getRDBRepository('User')
        ->where(['userName' => $userName, 'deleted' => false])
        ->findOne();

    if ($user === null) {
        $user = $em->createEntity(User::ENTITY_TYPE, [
            'userName'   => $userName,
            'type'       => User::TYPE_API,
            'authMethod' => ApiKeyLogin::NAME,
            'rolesIds'   => [$roleId],
            'firstName'  => $first,
            'lastName'   => $last,
        ]);
        $em->saveEntity($user);
        $user = $em->getRDBRepository('User')->getById($user->getId());
    } else {
        $relation = $em->getRDBRepository('User')->getRelation($user, 'roles');
        if (!$relation->isRelated($role)) {
            $relation->relate($role);
        }
    }

    $apiKey = $user->get('apiKey');
    if (!is_string($apiKey) || $apiKey === '') {
        $apiKey = Util::generateApiKey();
        $user->set('apiKey', $apiKey);
        $em->saveEntity($user);
    }

    return [$user, $apiKey];
};

[$adminApiUser, $adminApiKey] = $provisionApiUser(
    $em,
    SMOKE_USER_ADMIN,
    'Admin',
    'Smoke',
    'ApiCatalog'
);

$client = new Client([
    'base_uri' => $siteUrl,
    'verify'   => false,
    'timeout'  => 30,
    'headers'  => [
        'X-Api-Key' => $adminApiKey,
        'Accept'    => 'application/json',
    ],
]);

echo "Base URL: $siteUrl\n";
echo "Auth (admin catalog): X-Api-Key (" . SMOKE_USER_ADMIN . ")\n\n";

/* --- Workflow A: App/user --- */
try {
    $r = $client->get('/api/v1/App/user');
    $code = $r->getStatusCode();
    $body = json_decode((string) $r->getBody(), true);
} catch (RequestException $e) {
    fwrite(STDERR, 'HTTP error: ' . $e->getMessage() . "\n");
    exit(1);
}

$ok('GET /api/v1/App/user → 200', $code === 200, "code=$code");
$acl = is_array($body) ? ($body['acl']['table'] ?? null) : null;
$ok('App/user has acl.table', is_array($acl) && $acl !== []);

foreach (['VolunteerEmployee', 'Member', 'MealCount', 'Account', 'Opportunity'] as $entity) {
    $row = is_array($acl) ? ($acl[$entity] ?? null) : null;
    $ok("acl.table[$entity] present", is_array($row), $row === null ? 'missing' : 'ok');
    if (is_array($row) && ($row['read'] ?? '') === 'no') {
        $ok("acl.table[$entity].read !== no", false, 'read=no');
    }
}

/* --- Metadata scopes slice --- */
$r2 = $client->get('/api/v1/Metadata', ['query' => ['key' => 'scopes']]);
$scopes = json_decode((string) $r2->getBody(), true);
$ok('GET Metadata?key=scopes → 200', $r2->getStatusCode() === 200);

foreach (['VolunteerEmployee', 'Member', 'MealCount'] as $entity) {
    $ent = is_array($scopes) ? ($scopes[$entity] ?? null) : null;
    $flag = is_array($ent) && (($ent['entity'] ?? false) === true);
    $ok("scopes[$entity].entity === true", $flag);
}

/* --- List entities (select + maxSize, skill rule) --- */
foreach (['VolunteerEmployee', 'Member', 'MealCount', 'Account', 'Opportunity'] as $entity) {
    $path = '/api/v1/' . $entity;
    $rq = $client->get($path, [
        'query' => [
            'select'  => 'id,name',
            'maxSize' => 5,
        ],
    ]);
    $listBody = json_decode((string) $rq->getBody(), true);
    $hasList = is_array($listBody) && array_key_exists('list', $listBody);
    $ok("GET $path?select=&maxSize=5 → 200 + list", $rq->getStatusCode() === 200 && $hasList,
        'code=' . $rq->getStatusCode());
}

/* --- entityDefs slices (post English refactor) --- */
$accDefs = json_decode(
    (string) $client->get('/api/v1/Metadata', ['query' => ['key' => 'entityDefs.Account']])->getBody(),
    true
);
$fields = is_array($accDefs) && isset($accDefs['fields']) && is_array($accDefs['fields'])
    ? $accDefs['fields']
    : [];
$ok('Metadata entityDefs.Account has field sector', isset($fields['sector']));
$ok('Metadata entityDefs.Account has no settore', !isset($fields['settore']));

$oppDefs = json_decode(
    (string) $client->get('/api/v1/Metadata', ['query' => ['key' => 'entityDefs.Opportunity']])->getBody(),
    true
);
$oppFields = is_array($oppDefs) && isset($oppDefs['fields']) && is_array($oppDefs['fields'])
    ? $oppDefs['fields']
    : [];
$ok('Metadata entityDefs.Opportunity has presentationDate', isset($oppFields['presentationDate']));
$ok('Metadata entityDefs.Opportunity has no dataPresentazione', !isset($oppFields['dataPresentazione']));
$stageOpts = $oppFields['stage']['options'] ?? null;
$ok(
    'Opportunity.stage options include Preparation',
    is_array($stageOpts) && in_array('Preparation', $stageOpts, true),
    is_array($stageOpts) ? implode(',', $stageOpts) : 'n/a'
);

echo "\n--- Contact enhancements ---\n";

$contactDefs = json_decode(
    (string) $client->get('/api/v1/Metadata', ['query' => ['key' => 'entityDefs.Contact']])->getBody(),
    true
);
$cFields = is_array($contactDefs) && isset($contactDefs['fields']) && is_array($contactDefs['fields'])
    ? $contactDefs['fields']
    : [];
$ok('Metadata entityDefs.Contact has contactType', isset($cFields['contactType']));
$ok('Metadata entityDefs.Contact has relatedRecord Account link', ($cFields['relatedRecord']['type'] ?? '') === 'link'
    && ($cFields['relatedRecord']['entity'] ?? '') === 'Account');
$cOpts = $cFields['contactType']['options'] ?? [];
$ok('contactType options include HelpSeeker', is_array($cOpts) && in_array('HelpSeeker', $cOpts, true));
$ok('contactType options include Other', is_array($cOpts) && in_array('Other', $cOpts, true));

$rContactList = $client->get('/api/v1/Contact', [
    'query' => ['select' => 'id,firstName,lastName,contactType', 'maxSize' => 5],
]);
$ok(
    'GET Contact?select=contactType&maxSize=5 → 200',
    $rContactList->getStatusCode() === 200,
    'code=' . $rContactList->getStatusCode()
);

$extractLayoutFieldNames = static function (mixed $layout): array {
    if (!is_array($layout)) {
        return [];
    }

    $names = [];

    foreach ($layout as $item) {
        if (is_string($item)) {
            $names[] = $item;
            continue;
        }

        if (!is_array($item)) {
            continue;
        }

        if (isset($item['name']) && is_string($item['name'])) {
            $names[] = $item['name'];
            continue;
        }

        if (isset($item['rows']) && is_array($item['rows'])) {
            foreach ($item['rows'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach ($row as $cell) {
                    if (is_array($cell) && isset($cell['name']) && is_string($cell['name'])) {
                        $names[] = $cell['name'];
                    }
                }
            }
        }
    }

    return $names;
};

foreach (['detail', 'list', 'filters'] as $layoutName) {
    $rLayout = $client->get("/api/v1/Contact/layout/$layoutName");
    $layoutBody = json_decode((string) $rLayout->getBody(), true);
    $fieldNames = $extractLayoutFieldNames($layoutBody);

    $ok(
        "GET Contact/layout/$layoutName → 200",
        $rLayout->getStatusCode() === 200,
        'code=' . $rLayout->getStatusCode()
    );
    $ok(
        "Contact layout/$layoutName includes contactType",
        in_array('contactType', $fieldNames, true),
        implode(',', $fieldNames)
    );
    $ok(
        "Contact layout/$layoutName includes relatedRecord",
        in_array('relatedRecord', $fieldNames, true),
        implode(',', $fieldNames)
    );
    $ok(
        "Contact layout/$layoutName excludes accounts",
        !in_array('accounts', $fieldNames, true),
        implode(',', $fieldNames)
    );
}

$filtersLayout = json_decode(
    (string) $client->get('/api/v1/Contact/layout/filters')->getBody(),
    true
);
$filtersAreStrings = is_array($filtersLayout)
    && count($filtersLayout) > 0
    && array_reduce(
        $filtersLayout,
        static fn (bool $carry, mixed $item): bool => $carry && is_string($item),
        true
    );
$ok(
    'Contact filters layout uses string field names (Espo search view requirement)',
    $filtersAreStrings,
    is_array($filtersLayout) ? json_encode(array_slice($filtersLayout, 0, 3)) : 'invalid'
);

$styleMap = json_decode(
    (string) $client->get('/api/v1/Metadata', ['query' => ['key' => 'clientDefs.Contact.styleMap']])->getBody(),
    true
);
$ok(
    'Metadata clientDefs.Contact.styleMap.contactType.HelpSeeker === primary',
    ($styleMap['contactType']['HelpSeeker'] ?? null) === 'primary'
);

$oppLinks = json_decode(
    (string) $client->get('/api/v1/Metadata', ['query' => ['key' => 'entityDefs.Opportunity.links']])->getBody(),
    true
);
$ok(
    'Opportunity.links.contacts is native hasMany (not relatedRecord hasChildren)',
    ($oppLinks['contacts']['type'] ?? '') === 'hasMany'
        && ($oppLinks['contacts']['foreign'] ?? '') === 'opportunities'
);

echo "\n--- Entity stream (Flusso attività) ---\n";
foreach ([
    'Intervention',
    'PrimaNota',
    'FoodParcelRegistration',
    'MealCount',
    'AssociationMealCount',
    'Member',
    'VolunteerEmployee',
    'Case',
] as $streamEntity) {
    $scopeRow = is_array($scopes) ? ($scopes[$streamEntity] ?? null) : null;
    $streamEnabled = is_array($scopeRow) && (($scopeRow['stream'] ?? false) === true);
    $aclStream = is_array($acl) ? ($acl[$streamEntity]['stream'] ?? null) : null;
    $ok("scopes[$streamEntity].stream === true", $streamEnabled);
    $ok("acl.table[$streamEntity].stream present", $aclStream !== null, $aclStream === null ? 'missing' : (string) $aclStream);
}

foreach (['Intervention', 'PrimaNota', 'FoodParcelRegistration'] as $entity) {
    $row = is_array($acl) ? ($acl[$entity] ?? null) : null;
    $ok("acl.table[$entity] present", is_array($row), $row === null ? 'missing' : 'ok');
    $ent = is_array($scopes) ? ($scopes[$entity] ?? null) : null;
    $ok("scopes[$entity].entity === true", is_array($ent) && (($ent['entity'] ?? false) === true));
}

/* --- 401 without key (skill: unauthenticated) --- */
$bare = new Client([
    'base_uri' => $siteUrl,
    'verify'   => false,
    'timeout'  => 15,
    'http_errors' => false,
]);
$r401 = $bare->get('/api/v1/App/user');
$ok('GET App/user without X-Api-Key is not 200', $r401->getStatusCode() !== 200,
    'code=' . $r401->getStatusCode());

/* --- Phase 3: GoogleCalendarDrive OAuth2 (Workflow C + Integration read; explore-espo-endpoints) --- */
echo "\n--- GoogleCalendarDrive (universal Google OAuth2) ---\n";

$rMetaGh = $client->get('/api/v1/Metadata', ['query' => ['key' => 'integrations.GoogleCalendarDrive']]);
$ok(
    'GET Metadata?key=integrations.GoogleCalendarDrive → 200',
    $rMetaGh->getStatusCode() === 200,
    'code=' . $rMetaGh->getStatusCode()
);
$metaInt = json_decode((string) $rMetaGh->getBody(), true);
$ok('integrations.GoogleCalendarDrive.authMethod === OAuth2', ($metaInt['authMethod'] ?? '') === 'OAuth2');
$ok(
    'integrations.GoogleCalendarDrive.clientClassName is Google client',
    str_contains((string) ($metaInt['clientClassName'] ?? ''), 'Google'),
    (string) ($metaInt['clientClassName'] ?? '')
);
$ok(
    'integrations.GoogleCalendarDrive.allowUserAccounts === true',
    ($metaInt['allowUserAccounts'] ?? false) === true
);
$scope = (string) (($metaInt['params'] ?? [])['scope'] ?? '');
$ok(
    'integrations.GoogleCalendarDrive scope has identity + calendar + drive.file',
    str_contains($scope, 'openid')
    && str_contains($scope, 'email')
    && str_contains($scope, 'profile')
    && str_contains($scope, 'calendar')
    && str_contains($scope, 'drive.file'),
    $scope === '' ? '(empty)' : $scope
);

$ghRow = $em->getRDBRepository('Integration')
    ->where(['id' => GoogleIntegrationInstaller::INTEGRATION_ID])
    ->findOne();
$ok('DB row Integration `' . GoogleIntegrationInstaller::INTEGRATION_ID . '` exists (ORM)', $ghRow !== null);

/* ========== Volunteer-role API (IDOR + blocked entity) ========== */

echo "\n--- Volunteer API user (" . SMOKE_USER_VOLUNTEER . ", role Volunteer) ---\n";

[$volApiUser, $volApiKey] = $provisionApiUser(
    $em,
    SMOKE_USER_VOLUNTEER,
    'Volunteer',
    'Smoke',
    'ApiVolunteer'
);

$volId = $volApiUser->getId();
if (!is_string($volId) || $volId === '') {
    fwrite(STDERR, "FAIL: volunteer API user has no id.\n");
    exit(1);
}

$ownVe = $em->getRDBRepository('VolunteerEmployee')
    ->where(['assignedUserId' => $volId])
    ->findOne();

if ($ownVe === null) {
    $ownVe = $em->getRDBRepository('VolunteerEmployee')->getNew();
    $ownVe->set([
        'type'            => 'Volunteer',
        'firstName'       => 'Smoke',
        'lastName'        => 'ApiVolunteer',
        'weeklyHours'     => 4,
        'assignedUserId'  => $volId,
        'emailAddress'    => SMOKE_USER_VOLUNTEER . '@example.com',
    ]);
    $em->saveEntity($ownVe, [SaveOption::SKIP_ALL => true]);
}

$ownVeId = $ownVe->getId();
if (!is_string($ownVeId) || $ownVeId === '') {
    fwrite(STDERR, "FAIL: could not resolve own VolunteerEmployee id.\n");
    exit(1);
}

$foreignVe = null;
foreach ($em->getRDBRepository('VolunteerEmployee')->limit(0, 80)->find() as $cand) {
    $aid = $cand->get('assignedUserId');
    $cid = $cand->getId();
    if (!is_string($cid) || $cid === '' || $cid === $ownVeId) {
        continue;
    }
    if (is_string($aid) && $aid !== '' && $aid !== $volId) {
        $foreignVe = $cand;
        break;
    }
}

$volClient = new Client([
    'base_uri' => $siteUrl,
    'verify'   => false,
    'timeout'  => 30,
    'http_errors' => false,
    'headers'  => [
        'X-Api-Key' => $volApiKey,
        'Accept'    => 'application/json',
    ],
]);

$rOwnVe = $volClient->get('/api/v1/VolunteerEmployee/' . $ownVeId, [
    'query' => ['select' => 'id,firstName,lastName,assignedUserId'],
]);
$ok(
    'Volunteer GET own VolunteerEmployee → 200',
    $rOwnVe->getStatusCode() === 200,
    'code=' . $rOwnVe->getStatusCode()
);

if ($foreignVe !== null) {
    $foreignVeId = $foreignVe->getId();
    if (is_string($foreignVeId) && $foreignVeId !== '') {
        $rFor = $volClient->get('/api/v1/VolunteerEmployee/' . $foreignVeId, [
            'query' => ['select' => 'id,firstName'],
        ]);
        $ok(
            'Volunteer GET foreign VolunteerEmployee → 403 (read=own IDOR)',
            $rFor->getStatusCode() === 403,
            'code=' . $rFor->getStatusCode()
        );
    }
} else {
    $ok('Volunteer GET foreign VolunteerEmployee → 403 (read=own IDOR)', false, 'no foreign VE row in DB');
}

$anyMember = $em->getRDBRepository('Member')->limit(0, 1)->findOne();
if ($anyMember !== null) {
    $mid = $anyMember->getId();
    if (is_string($mid) && $mid !== '') {
        $rMem = $volClient->get('/api/v1/Member/' . $mid, ['query' => ['select' => 'id,firstName']]);
        $ok(
            'Volunteer GET Member (blocked scope) → 403',
            $rMem->getStatusCode() === 403,
            'code=' . $rMem->getStatusCode()
        );
    }
} else {
    $ok('Volunteer GET Member (blocked scope) → 403', true, 'skipped (no Member rows)');
}

$foreignMc = null;
foreach ($em->getRDBRepository('MealCount')->limit(0, 80)->find() as $cand) {
    $aid = $cand->get('assignedUserId');
    if (is_string($aid) && $aid !== '' && $aid !== $volId) {
        $foreignMc = $cand;
        break;
    }
}

if ($foreignMc !== null) {
    $mcid = $foreignMc->getId();
    if (is_string($mcid) && $mcid !== '') {
        $rMc = $volClient->get('/api/v1/MealCount/' . $mcid, [
            'query' => ['select' => 'id,date,adults'],
        ]);
        $ok(
            'Volunteer GET foreign MealCount → 403 (read=own)',
            $rMc->getStatusCode() === 403,
            'code=' . $rMc->getStatusCode()
        );
    }
} else {
    $ok('Volunteer GET foreign MealCount → 403 (read=own)', true, 'skipped (no assigned foreign MealCount)');
}

$volBody = json_decode((string) $volClient->get('/api/v1/App/user')->getBody(), true);
$volAcl = is_array($volBody) ? ($volBody['acl']['table']['VolunteerEmployee'] ?? null) : null;
$ok(
    'Volunteer App/user shows VolunteerEmployee.read=own',
    is_array($volAcl) && ($volAcl['read'] ?? '') === 'own',
    is_array($volAcl) ? 'read=' . ($volAcl['read'] ?? '') : 'missing acl row'
);

$volAccountAcl = is_array($volBody) ? ($volBody['acl']['table']['Account'] ?? null) : null;
$ok(
    'Volunteer App/user shows Account.create=no',
    is_array($volAccountAcl) && ($volAccountAcl['create'] ?? '') === 'no',
    is_array($volAccountAcl) ? 'create=' . ($volAccountAcl['create'] ?? '') : 'missing acl row'
);

$forbiddenContactEmail = 'smoke-prima-nota-acl-' . bin2hex(random_bytes(8)) . '@example.com';
$rForbiddenPartyCreate = $volClient->post('/api/v1/PrimaNota', [
    'json' => [
        'entryType' => 'Income',
        'amount' => 10,
        'transactionDate' => date('Y-m-d'),
        'subjectName' => 'Forbidden Party Create',
        'subjectEmailAddress' => $forbiddenContactEmail,
        'createSubjectContact' => true,
    ],
]);
$ok(
    'Volunteer PrimaNota cannot create Contact through SubjectParty hook → 403',
    $rForbiddenPartyCreate->getStatusCode() === 403,
    'code=' . $rForbiddenPartyCreate->getStatusCode()
);
$forbiddenContact = $em->getRDBRepository(Contact::ENTITY_TYPE)
    ->where(['emailAddress' => $forbiddenContactEmail])
    ->findOne();
$ok(
    'Forbidden PrimaNota party creation leaves no Contact',
    $forbiddenContact === null,
    $forbiddenContact === null ? 'not created' : 'created id=' . $forbiddenContact->getId()
);

$rIntVol = $volClient->get('/api/v1/Integration/' . GoogleIntegrationInstaller::INTEGRATION_ID);
$ok(
    'Volunteer GET Integration/' . GoogleIntegrationInstaller::INTEGRATION_ID . ' → 403 (admin user-type only)',
    $rIntVol->getStatusCode() === 403,
    'code=' . $rIntVol->getStatusCode()
);

echo "\n=== " . ($fail === 0 ? 'ALL PASS' : "$fail FAILURE(S)") . " ===\n";
exit($fail === 0 ? 0 : 1);
