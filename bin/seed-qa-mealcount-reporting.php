<?php
/**
 * Seed QA MealCount rows for Epic 7.3 reporting UI verification.
 *
 * Creates via REST POST /api/v1/MealCount (skill: explore-espo-endpoints).
 * Prints expected banner totals from MealCountStatsProvider (same as summary API).
 *
 * Prefix in description: QA-MealCount-Reporting-* (name is formula-generated).
 * Rows are left for manual UI check.
 *
 * Usage:
 *   ddev exec php bin/seed-qa-mealcount-reporting.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\MealCountStatsProvider;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

const SEED_TAG = 'QA-MealCount-Reporting';
const SMOKE_USER_ADMIN = 'smoke_api_catalog';

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Config $config */
$config = $container->getByClass(Config::class);
/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: siteUrl empty.\n");
    exit(1);
}

$role = $em->getRDBRepository('Role')->where(['name' => 'Admin', 'deleted' => false])->findOne();
if ($role === null) {
    fwrite(STDERR, "FAIL: Admin role missing. Run: ddev exec php bin/setup-roles.php\n");
    exit(1);
}

/** @var ?User $user */
$user = $em->getRDBRepository('User')
    ->where(['userName' => SMOKE_USER_ADMIN, 'deleted' => false])
    ->findOne();

if ($user === null) {
    $user = $em->createEntity(User::ENTITY_TYPE, [
        'userName' => SMOKE_USER_ADMIN,
        'type' => User::TYPE_API,
        'authMethod' => ApiKeyLogin::NAME,
        'rolesIds' => [$role->getId()],
        'firstName' => 'Smoke',
        'lastName' => 'ApiCatalog',
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

$client = new Client([
    'base_uri' => $siteUrl,
    'verify' => false,
    'timeout' => 30,
    'http_errors' => false,
    'headers' => [
        'X-Api-Key' => $apiKey,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ],
]);

$request = static function (Client $client, string $method, string $path, ?array $json = null, ?array $query = null): array {
    $options = [];
    if ($json !== null) {
        $options['json'] = $json;
    }
    if ($query !== null) {
        $options['query'] = $query;
    }
    $response = $client->request($method, $path, $options);
    $body = json_decode((string) $response->getBody(), true);

    return [
        'code' => $response->getStatusCode(),
        'body' => is_array($body) ? $body : [],
        'reason' => $response->getHeaderLine('X-Status-Reason'),
    ];
};

echo "=== QA MealCount reporting seed ===\n";
echo "Base: $siteUrl\n";
echo "REST user: " . SMOKE_USER_ADMIN . " (Admin role)\n";
echo "Tag: " . SEED_TAG . "\n\n";

$auth = $request($client, 'GET', '/api/v1/App/user');
if ($auth['code'] !== 200) {
    fwrite(STDERR, "FAIL: App/user {$auth['code']} {$auth['reason']}\n");
    exit(1);
}

$statsProvider = $injectableFactory->create(MealCountStatsProvider::class);
$summaryBefore = $statsProvider->getSummary();

/** @var array<int, array{date: string, adults: int, minors: int, suffix: string}> $planned */
$planned = [
    ['date' => '2026-06-05', 'adults' => 20, 'minors' => 10, 'suffix' => 'Jun-A'],
    ['date' => '2026-06-12', 'adults' => 15, 'minors' => 8, 'suffix' => 'Jun-B'],
    ['date' => '2026-06-18', 'adults' => 12, 'minors' => 6, 'suffix' => 'Jun-C'],
    ['date' => '2026-03-10', 'adults' => 50, 'minors' => 25, 'suffix' => 'Mar-Y'],
    ['date' => '2026-01-15', 'adults' => 30, 'minors' => 15, 'suffix' => 'Jan-Y'],
];

$seedMonth = ['adults' => 0, 'minors' => 0, 'totalMeals' => 0, 'foodCost' => 0.0];
$seedYear = ['adults' => 0, 'minors' => 0, 'totalMeals' => 0, 'foodCost' => 0.0];
$created = 0;
$skipped = 0;

foreach ($planned as $row) {
    $desc = SEED_TAG . ' ' . $row['suffix'];

    $existing = $request($client, 'GET', '/api/v1/MealCount', null, [
        'select' => 'id,description,adults,minors,totalMeals,foodCost,date',
        'maxSize' => 5,
        'where' => [
            ['type' => 'equals', 'attribute' => 'description', 'value' => $desc],
        ],
    ]);

    if (($existing['body']['total'] ?? 0) > 0) {
        $e = $existing['body']['list'][0];
        echo "SKIP exists: $desc id={$e['id']}\n";
        $skipped++;
        $adults = (int) ($e['adults'] ?? 0);
        $minors = (int) ($e['minors'] ?? 0);
        $total = (int) ($e['totalMeals'] ?? 0);
        $cost = (float) ($e['foodCost'] ?? 0);
        $date = (string) ($e['date'] ?? '');
    } else {
        $post = $request($client, 'POST', '/api/v1/MealCount', [
            'date' => $row['date'],
            'adults' => $row['adults'],
            'minors' => $row['minors'],
            'description' => $desc,
        ]);

        if ($post['code'] !== 200) {
            echo "FAIL POST $desc: {$post['code']} {$post['reason']}\n";
            if (!empty($post['body'])) {
                echo json_encode($post['body'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
            }
            continue;
        }

        $id = (string) ($post['body']['id'] ?? '');
        $adults = (int) ($post['body']['adults'] ?? $row['adults']);
        $minors = (int) ($post['body']['minors'] ?? $row['minors']);
        $total = (int) ($post['body']['totalMeals'] ?? ($adults + $minors));
        $cost = (float) ($post['body']['foodCost'] ?? ($total * 1.5));
        $date = (string) ($post['body']['date'] ?? $row['date']);
        echo "CREATE $desc id=$id date=$date adults=$adults minors=$minors totalMeals=$total foodCost=$cost\n";
        $created++;
    }

    if ($date >= '2026-06-01' && $date <= '2026-06-30') {
        $seedMonth['adults'] += $adults;
        $seedMonth['minors'] += $minors;
        $seedMonth['totalMeals'] += $total;
        $seedMonth['foodCost'] += $cost;
    }

    if ($date >= '2026-01-01' && $date <= '2026-12-31') {
        $seedYear['adults'] += $adults;
        $seedYear['minors'] += $minors;
        $seedYear['totalMeals'] += $total;
        $seedYear['foodCost'] += $cost;
    }
}

$summaryAfter = $statsProvider->getSummary();

echo "\n========== QA BATCH ONLY (5 seed rows) ==========\n";
echo "June 2026 portion: adults={$seedMonth['adults']} minors={$seedMonth['minors']} "
    . "totalMeals={$seedMonth['totalMeals']} foodCost={$seedMonth['foodCost']}\n";
echo "Year 2026 portion: adults={$seedYear['adults']} minors={$seedYear['minors']} "
    . "totalMeals={$seedYear['totalMeals']} foodCost={$seedYear['foodCost']}\n";
echo "REST: created=$created skipped=$skipped\n";

$m = $summaryAfter->month;
$y = $summaryAfter->year;
$mb = $summaryBefore->month;
$yb = $summaryBefore->year;

echo "\n========== BANNER / API (ALL MealCount in DB) ==========\n";
echo "Use these numbers on #MealCount list (Ctrl+Shift+R first).\n\n";
echo "MONTH {$m->from} – {$m->to}:\n";
echo "  adults      = {$m->adults}\n";
echo "  minors      = {$m->minors}\n";
echo "  totalMeals  = {$m->totalMeals}\n";
echo "  foodCost    = {$m->foodCost} EUR\n\n";
echo "YEAR {$y->from} – {$y->to}:\n";
echo "  adults      = {$y->adults}\n";
echo "  minors      = {$y->minors}\n";
echo "  totalMeals  = {$y->totalMeals}\n";
echo "  foodCost    = {$y->foodCost} EUR\n";

if ($created > 0) {
    echo "\nDelta from this run (month adults): " . ((int) $m->adults - (int) $mb->adults) . "\n";
    echo "Delta from this run (year adults): " . ((int) $y->adults - (int) $yb->adults) . "\n";
}

echo "\n--- Per-row reference (foodUnitPrice=1.5 EUR) ---\n";
foreach ($planned as $row) {
    $t = $row['adults'] + $row['minors'];
  echo "{$row['suffix']}: date={$row['date']} adults={$row['adults']} minors={$row['minors']} "
        . "totalMeals=$t foodCost=" . ($t * 1.5) . "\n";
}

echo "\nUI filter: MealCount list → search description \"" . SEED_TAG . "\" → 5 rows\n";
echo "URL: {$siteUrl}/#MealCount\n";

// REST summary probe (UI uses same endpoint as logged-in Admin)
$restSummary = $request($client, 'GET', '/api/v1/NonprofitEspocrm/reporting/meal-count/summary');
if ($restSummary['code'] === 200) {
    echo "\nREST summary OK (smoke_api_catalog user).\n";
} else {
    echo "\nNOTE: REST summary returned {$restSummary['code']} for API user — "
        . "UI Admin session should still show banner if logged in as Admin.\n";
    echo "Reason: {$restSummary['reason']}\n";
}
