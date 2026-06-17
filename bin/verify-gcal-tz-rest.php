<?php

/**
 * REST + Google cross-check: CRM dateStart vs export payload vs Google API.
 *
 * Usage: ddev exec php bin/verify-gcal-tz-rest.php [EntityType] [id ...]
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarDateTimeResolver;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;

const API_USER = 'smoke_api_catalog';

$entityType = $argv[1] ?? 'Call';
$ids = array_slice($argv, 2);

$app = new Application();
$container = $app->getContainer();
$config = $container->getByClass(Config::class);
$em = $container->getByClass(EntityManager::class);
$dtUtil = $container->getByClass(DateTimeUtil::class);
$factory = $container->getByClass(InjectableFactory::class);

$admin = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'admin', 'deleted' => false])
    ->findOne();

if ($admin !== null) {
    $container->getByClass(ApplicationUser::class)->setUser($admin);
}

$siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');

if ($siteUrl === '') {
    fwrite(STDERR, "FAIL: siteUrl empty\n");
    exit(1);
}

$user = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => API_USER, 'deleted' => false])
    ->findOne();

if ($user === null) {
    $role = $em->getRDBRepository('Role')->where(['name' => 'Admin', 'deleted' => false])->findOne();

    if ($role === null) {
        fwrite(STDERR, "FAIL: Admin role missing\n");
        exit(1);
    }

    $user = $em->createEntity(User::ENTITY_TYPE, [
        'userName' => API_USER,
        'type' => User::TYPE_API,
        'authMethod' => ApiKeyLogin::NAME,
        'rolesIds' => [$role->getId()],
        'firstName' => 'Smoke',
        'lastName' => 'GCalTz',
    ]);
    $em->saveEntity($user);
    $user = $em->getEntityById(User::ENTITY_TYPE, $user->getId());
}

$apiKey = $user->get('apiKey');

if (!is_string($apiKey) || $apiKey === '') {
    $apiKey = Util::generateApiKey();
    $user->set('apiKey', $apiKey);
    $em->saveEntity($user);
}

if ($ids === []) {
    $client = new Client(['base_uri' => $siteUrl, 'verify' => false, 'http_errors' => false]);
    $r = $client->get('/api/v1/' . $entityType, [
        'headers' => ['X-Api-Key' => $apiKey, 'Accept' => 'application/json'],
        'query' => [
            'maxSize' => 20,
            'select' => 'id,name,dateStart,dateEnd',
            'where' => [
                ['type' => 'startsWith', 'attribute' => 'name', 'value' => 'T-'],
            ],
        ],
    ]);
    $body = json_decode((string) $r->getBody(), true);
    $ids = array_map(
        static fn (array $row): string => (string) ($row['id'] ?? ''),
        is_array($body['list'] ?? null) ? $body['list'] : []
    );
    $ids = array_values(array_filter($ids));
}

if ($ids === []) {
    fwrite(STDERR, "No records to verify for {$entityType}\n");
    exit(1);
}

$http = new Client(['base_uri' => $siteUrl, 'verify' => false, 'http_errors' => false]);
$pusher = $factory->create(EventPusher::class);
$resolver = $factory->create(CalendarDateTimeResolver::class);
$buildRange = new ReflectionMethod($pusher, 'buildDateTimeRange');
$buildRange->setAccessible(true);

echo "=== REST + TZ verify ({$entityType}) ===\n";
echo "siteUrl={$siteUrl} appTZ=" . ($config->get('timeZone') ?? 'null') . "\n\n";

$fail = 0;

foreach ($ids as $id) {
    $r = $http->get("/api/v1/{$entityType}/{$id}", [
        'headers' => ['X-Api-Key' => $apiKey, 'Accept' => 'application/json'],
        'query' => ['select' => 'id,name,dateStart,dateEnd'],
    ]);

    if ($r->getStatusCode() !== 200) {
        echo "[FAIL] GET {$entityType}/{$id} → {$r->getStatusCode()}\n";
        $fail++;
        continue;
    }

    $row = json_decode((string) $r->getBody(), true);
    $dbStart = (string) ($row['dateStart'] ?? '');
    $dbEnd = (string) ($row['dateEnd'] ?? '');
    $name = (string) ($row['name'] ?? '');
    $crmStart = $dbStart !== '' ? $dtUtil->convertSystemDateTime($dbStart) : '';
    $crmEnd = $dbEnd !== '' ? $dtUtil->convertSystemDateTime($dbEnd) : '';

    $range = ($dbStart !== '' && $dbEnd !== '')
        ? $buildRange->invoke($pusher, $dbStart, $dbEnd)
        : null;

    $exportStart = is_array($range) ? (string) ($range['start']['dateTime'] ?? '') : '';
    $exportTz = is_array($range) ? (string) ($range['start']['timeZone'] ?? '') : '';
    $expectedWall = $dbStart !== '' ? $resolver->utcStorageToWallClockDateTime($dbStart) : '';

    preg_match('/\d{2}:\d{2}/', $crmStart, $crmTimeM);
    $crmTime = $crmTimeM[0] ?? '';
    preg_match('/T(\d{2}:\d{2})/', $exportStart, $exportTimeM);
    $exportTime = $exportTimeM[1] ?? '';

    $restOk = $exportStart === $expectedWall && $exportTz === $resolver->getExportTimeZone();
    $displayOk = $crmTime !== '' && $exportTime === $crmTime;

    echo "{$entityType}/{$id}\n";
    echo "  name: {$name}\n";
    echo "  REST dateStart (UTC storage): {$dbStart}\n";
    echo "  CRM display: {$crmStart}" . ($crmEnd !== '' ? " – {$crmEnd}" : '') . "\n";
    echo "  export: " . json_encode($range['start'] ?? null, JSON_UNESCAPED_SLASHES) . "\n";

    $status = ($restOk && $displayOk) ? 'PASS' : 'FAIL';

    if ($status === 'FAIL') {
        $fail++;
    }

    echo "  [{$status}] REST storage → export wall-clock; CRM time = export time\n\n";
}

echo $fail === 0
    ? "=== ALL PASS ({$entityType}, " . count($ids) . " records) ===\n"
    : "=== {$fail} FAILURE(S) ===\n";

exit($fail === 0 ? 0 : 1);
