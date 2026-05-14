<?php
/**
 * REST smoke aligned with skill `explore-espo-endpoints` (Workflow A + D).
 *
 * Provisions an idempotent API user (`smoke_api_catalog`) with Admin role,
 * then calls the live Espo REST API with `X-Api-Key` (same as skill Workflow E).
 *
 * Checks relevant to completed Safehouse work:
 *   - GET /api/v1/App/user → 200, `acl.table` present
 *   - GET /api/v1/Metadata?key=scopes → Safehouse entities have entity:true
 *   - GET /api/v1/{Entity} with select + maxSize for VolunteerEmployee, Member,
 *     MealCount, Account, Opportunity → 200 + JSON list
 *   - GET /api/v1/Metadata?key=entityDefs.Account → field `sector` exists
 *   - GET /api/v1/Metadata?key=entityDefs.Opportunity → `presentationDate`, English stage options
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
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

const SMOKE_USER = 'smoke_api_catalog';

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Config $config */
$config = $container->getByClass(Config::class);

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: config siteUrl is empty — set Site URL in Admin → Settings.\n");
    exit(1);
}

$adminRole = $em->getRDBRepository('Role')
    ->where(['name' => 'Admin', 'deleted' => false])
    ->findOne();
if ($adminRole === null) {
    fwrite(STDERR, "FAIL: Role Admin not found.\n");
    exit(1);
}
$adminRoleId = $adminRole->getId();

$user = $em->getRDBRepository('User')
    ->where(['userName' => SMOKE_USER, 'deleted' => false])
    ->findOne();

if ($user === null) {
    $user = $em->createEntity(User::ENTITY_TYPE, [
        'userName'   => SMOKE_USER,
        'type'       => User::TYPE_API,
        'authMethod' => ApiKeyLogin::NAME,
        'rolesIds'   => [$adminRoleId],
        'firstName'  => 'Smoke',
        'lastName'   => 'ApiCatalog',
    ]);
    $em->saveEntity($user);
    // apiKey is assigned in BeforeCreate hook; reload for plaintext key
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
    'verify'   => false,
    'timeout'  => 30,
    'headers'  => [
        'X-Api-Key' => $apiKey,
        'Accept'    => 'application/json',
    ],
]);

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $m = $pass ? 'PASS' : 'FAIL';
    echo "  [$m] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

echo "Base URL: $siteUrl\n";
echo "Auth: X-Api-Key (user " . SMOKE_USER . ")\n\n";

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

/* --- 401 without key (skill: unauthenticated) --- */
try {
    $bare = new Client([
        'base_uri' => $siteUrl,
        'verify'   => false,
        'timeout'  => 15,
        'http_errors' => false,
    ]);
    $r401 = $bare->get('/api/v1/App/user');
    $ok('GET App/user without X-Api-Key is not 200', $r401->getStatusCode() !== 200,
        'code=' . $r401->getStatusCode());
} catch (RequestException) {
    $ok('GET App/user without key (error path)', true);
}

echo "\n=== " . ($fail === 0 ? 'ALL PASS' : "$fail FAILURE(S)") . " ===\n";
exit($fail === 0 ? 0 : 1);
