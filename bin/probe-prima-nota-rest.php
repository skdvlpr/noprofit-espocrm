<?php

declare(strict_types=1);

/**
 * One-off REST probe for PrimaNota (explore-espo-endpoints Workflow A + C + D).
 * Usage: ddev exec php bin/probe-prima-nota-rest.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
/** @var Config $config */
$config = $container->getByClass(Config::class);
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: siteUrl empty\n");
    exit(1);
}

$user = $em->getRDBRepository('User')
    ->where(['userName' => 'smoke_api_catalog', 'deleted' => false])
    ->findOne();

if ($user === null) {
    fwrite(STDERR, "FAIL: smoke_api_catalog missing — run bin/setup-roles.php\n");
    exit(1);
}

$apiKey = $user->get('apiKey');
if (!is_string($apiKey) || $apiKey === '') {
    fwrite(STDERR, "FAIL: smoke_api_catalog has no apiKey\n");
    exit(1);
}

$client = new Client([
    'base_uri' => $siteUrl,
    'verify' => false,
    'timeout' => 30,
    'headers' => [
        'X-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ],
]);

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    echo '  [' . ($pass ? 'PASS' : 'FAIL') . '] ' . $name . ($detail !== '' ? " — $detail" : '') . "\n";
};

echo "Base URL: $siteUrl\n";
echo "Auth: X-Api-Key (smoke_api_catalog)\n\n";

$r = $client->get('/api/v1/App/user');
$body = json_decode((string) $r->getBody(), true);
$ok('GET /api/v1/App/user → 200', $r->getStatusCode() === 200, 'code=' . $r->getStatusCode());

$acl = is_array($body) ? ($body['acl']['table']['PrimaNota'] ?? null) : null;
$ok('acl.table.PrimaNota present', is_array($acl), json_encode($acl));

$scope = json_decode(
    (string) $client->get('/api/v1/Metadata', ['query' => ['key' => 'scopes.PrimaNota']])->getBody(),
    true
);
$ok('scopes.PrimaNota.entity === true', ($scope['entity'] ?? false) === true);

$fields = json_decode(
    (string) $client->get('/api/v1/Metadata', ['query' => ['key' => 'entityDefs.PrimaNota.fields']])->getBody(),
    true
);
$ok('entityDefs has subjectName', isset($fields['subjectName']));
$ok('entityDefs has beneficiaryName', isset($fields['beneficiaryName']));
$ok('entityDefs has beneficiaryParty (linkParent)', ($fields['beneficiaryParty']['type'] ?? '') === 'linkParent');

$filtersLayout = json_decode(
    (string) $client->get('/api/v1/PrimaNota/layout/filters')->getBody(),
    true
);
$firstFilter = is_array($filtersLayout) && $filtersLayout !== [] ? $filtersLayout[0] : null;
$ok(
    'layout/filters uses string field names (not [object Object])',
    is_string($firstFilter),
    'first=' . json_encode($firstFilter)
);

$list = json_decode(
    (string) $client->get('/api/v1/PrimaNota', [
        'query' => [
            'select' => 'id,subjectName,beneficiaryName,transactionDate',
            'maxSize' => 5,
            'orderBy' => 'transactionDate',
            'order' => 'desc',
        ],
    ])->getBody(),
    true
);
$ok('GET /api/v1/PrimaNota list → 200', is_array($list) && array_key_exists('list', $list));

$migratedResponse = $client->get('/api/v1/PrimaNota/migrate0000000001', [
    'query' => ['select' => 'id,subjectName,beneficiaryName,description'],
    'http_errors' => false,
]);
$migrated = json_decode((string) $migratedResponse->getBody(), true);
$ok(
    'GET migrated row → 200',
    $migratedResponse->getStatusCode() === 200,
    'code=' . $migratedResponse->getStatusCode()
);
$ok(
    'migrated row subjectName split',
    ($migrated['subjectName'] ?? '') === 'Gofound.me',
    (string) ($migrated['subjectName'] ?? '')
);
$ok(
    'migrated row beneficiaryName split',
    ($migrated['beneficiaryName'] ?? '') === 'Safe House',
    (string) ($migrated['beneficiaryName'] ?? '')
);

$filtered = json_decode(
    (string) $client->get('/api/v1/PrimaNota', [
        'query' => [
            'select' => 'id,subjectName,beneficiaryName',
            'maxSize' => 5,
            'where' => [
                [
                    'type' => 'contains',
                    'attribute' => 'beneficiaryName',
                    'value' => 'Safe House',
                ],
            ],
        ],
    ])->getBody(),
    true
);
$ids = array_column($filtered['list'] ?? [], 'id');
$ok(
    'where beneficiaryName contains Safe House',
    in_array('migrate0000000001', $ids, true),
    'total=' . ($filtered['total'] ?? 0) . ' ids=' . implode(',', $ids)
);

$bare = new Client(['base_uri' => $siteUrl, 'verify' => false, 'http_errors' => false]);
$unauth = $bare->get('/api/v1/App/user');
$ok('GET App/user without key is not 200', $unauth->getStatusCode() !== 200, 'code=' . $unauth->getStatusCode());

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
