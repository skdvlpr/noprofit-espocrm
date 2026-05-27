<?php
/**
 * REST smoke for the standalone **`GoogleCalendarDrive`** Espo extension (universal
 * Google OAuth2: Calendar + `drive.file` Drive scope via core ExternalAccount).
 *
 * 1) Runs {@see \Espo\Modules\GoogleIntegration\Tools\Installer} (idempotent: DB row,
 *    migrates/removes legacy `GoogleIntegration` / `GoogleSafehouse` integration ids, rebuild).
 * 2) Follows `explore-espo-endpoints` Workflow A (`App/user`) + Workflow C (Metadata slice).
 * 3) ORM + expected **403** on `GET Integration/GoogleCalendarDrive` for `type=api` users
 *    (human admin UI uses `type=admin`).
 *
 * Usage:
 *   ddev exec php bin/smoke-google-integration.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Installer as GoogleIntegrationInstaller;
use Espo\ORM\EntityManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

const SMOKE_USER_ADMIN = 'smoke_api_catalog';

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Config $config */
$config = $container->getByClass(Config::class);

$integrationsConfigBefore = $config->get('integrations') ?? (object) [];
if (is_array($integrationsConfigBefore)) {
    $integrationsConfigBefore = (object) $integrationsConfigBefore;
}
$canonicalConfigExisted = is_object($integrationsConfigBefore)
    && property_exists($integrationsConfigBefore, GoogleIntegrationInstaller::INTEGRATION_ID);
$legacyConfigSnapshot = is_object($integrationsConfigBefore)
    && property_exists($integrationsConfigBefore, 'GoogleIntegration')
    ? (bool) $integrationsConfigBefore->GoogleIntegration
    : null;

$canonicalIntegrationBefore = $em
    ->getRDBRepository('Integration')
    ->where(['id' => GoogleIntegrationInstaller::INTEGRATION_ID])
    ->findOne();
$legacyIntegrationBefore = $em->getRDBRepository('Integration')->where(['id' => 'GoogleIntegration'])->findOne();
$legacyIntegrationSnapshot = $legacyIntegrationBefore === null ? null : [
    'enabled' => (bool) $legacyIntegrationBefore->get('enabled'),
    'clientId' => $legacyIntegrationBefore->get('clientId'),
    'clientSecret' => $legacyIntegrationBefore->get('clientSecret'),
];
$legacyExternalAccountBefore = $em
    ->getRDBRepository('ExternalAccount')
    ->where(['id*' => 'GoogleIntegration__%'])
    ->findOne();
$legacyExternalAccountSnapshot = null;

if ($legacyExternalAccountBefore !== null) {
    $legacyExternalAccountId = $legacyExternalAccountBefore->getId();

    if (is_string($legacyExternalAccountId) && str_starts_with($legacyExternalAccountId, 'GoogleIntegration__')) {
        $legacyExternalAccountSnapshot = [
            'id' => $legacyExternalAccountId,
            'newId' => GoogleIntegrationInstaller::INTEGRATION_ID . '__'
                . substr($legacyExternalAccountId, strlen('GoogleIntegration__')),
            'canonicalExisted' => $em->getEntityById(
                'ExternalAccount',
                GoogleIntegrationInstaller::INTEGRATION_ID . '__'
                    . substr($legacyExternalAccountId, strlen('GoogleIntegration__'))
            ) !== null,
            'enabled' => (bool) $legacyExternalAccountBefore->get('enabled'),
            'accessToken' => $legacyExternalAccountBefore->get('accessToken'),
            'refreshToken' => $legacyExternalAccountBefore->get('refreshToken'),
            'calendarSyncMode' => $legacyExternalAccountBefore->get('calendarSyncMode'),
        ];
    }
}

echo "Provisioning: Espo\\Modules\\GoogleIntegration\\Tools\\Installer\n";
(new GoogleIntegrationInstaller())->runPostInstall($container);
$config->update();

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

$role = $em->getRDBRepository('Role')->where(['name' => 'Admin', 'deleted' => false])->findOne();
if ($role === null) {
    fwrite(STDERR, "FAIL: Admin role not found.\n");
    exit(1);
}
$roleId = $role->getId();

/** @var ?User $user */
$user = $em->getRDBRepository('User')
    ->where(['userName' => SMOKE_USER_ADMIN, 'deleted' => false])
    ->findOne();
if ($user === null) {
    $user = $em->createEntity(User::ENTITY_TYPE, [
        'userName'   => SMOKE_USER_ADMIN,
        'type'       => User::TYPE_API,
        'authMethod' => ApiKeyLogin::NAME,
        'rolesIds'   => [$roleId],
        'firstName'  => 'Smoke',
        'lastName'   => 'GoogleMeta',
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
    'base_uri'    => $siteUrl,
    'verify'      => false,
    'timeout'     => 30,
    'http_errors' => false,
    'headers'     => [
        'X-Api-Key' => $apiKey,
        'Accept'    => 'application/json',
    ],
]);

echo "Base URL: $siteUrl\n";
echo "Workflow A: App/user (X-Api-Key)\n";

try {
    $r = $client->get('/api/v1/App/user');
} catch (RequestException $e) {
    fwrite(STDERR, 'HTTP error: ' . $e->getMessage() . "\n");
    exit(1);
}
$ok('GET /api/v1/App/user → 200', $r->getStatusCode() === 200, 'code=' . $r->getStatusCode());

echo "\nWorkflow C: Metadata integrations.GoogleCalendarDrive\n";

$rMeta = $client->get('/api/v1/Metadata', ['query' => ['key' => 'integrations.GoogleCalendarDrive']]);
$ok('GET Metadata?key=integrations.GoogleCalendarDrive → 200', $rMeta->getStatusCode() === 200,
    'code=' . $rMeta->getStatusCode());
$meta = json_decode((string) $rMeta->getBody(), true);
$ok('authMethod OAuth2', ($meta['authMethod'] ?? '') === 'OAuth2');
$ok(
    'admin view uses module client path (google-integration:…)',
    ($meta['view'] ?? '') === 'google-integration:views/admin/integrations/edit'
);
$ok(
    'userView uses module client path (google-integration:…)',
    ($meta['userView'] ?? '') === 'google-integration:views/external-account/oauth2'
);
$ok(
    'clientClassName is module Google client',
    ($meta['clientClassName'] ?? '') === 'Espo\\Modules\\GoogleIntegration\\Core\\ExternalAccount\\Clients\\Google'
);
$ok('no custom redirectUriPath (canonical ?entryPoint=oauthCallback)', empty($meta['params']['redirectUriPath'] ?? null));
$expectedRedirect = (string) ($config->get('siteUrl') ?? '') . '?entryPoint=oauthCallback';
$ok('canonical redirect URI matches Espo core ClientManager', \Espo\Modules\GoogleIntegration\Tools\OAuth\RedirectUri::build($config) === $expectedRedirect, $expectedRedirect);
$scope = (string) (($meta['params'] ?? [])['scope'] ?? '');
$ok('scope lists calendar and drive.file', str_contains($scope, 'calendar') && str_contains($scope, 'drive.file'));
$fields = $meta['fields'] ?? null;
$ok('fields include clientId and clientSecret', is_array($fields) && isset($fields['clientId'], $fields['clientSecret']));

echo "\nORM + Integration REST (API user expected 403)\n";

$gh = $em->getRDBRepository('Integration')->where(['id' => GoogleIntegrationInstaller::INTEGRATION_ID])->findOne();
$ok('Integration ' . GoogleIntegrationInstaller::INTEGRATION_ID . ' exists in database', $gh !== null);

$legacy = $em->getRDBRepository('Integration')->where(['id' => 'GoogleSafehouse'])->findOne();
$ok('Legacy Integration GoogleSafehouse removed', $legacy === null);

$legacyGoogle = $em->getRDBRepository('Integration')->where(['id' => 'GoogleIntegration'])->findOne();
$ok('Legacy Integration GoogleIntegration removed after migration', $legacyGoogle === null);

if ($legacyIntegrationSnapshot !== null && $canonicalIntegrationBefore === null && $gh !== null) {
    $ok(
        'legacy GoogleIntegration enabled flag migrated',
        (bool) $gh->get('enabled') === $legacyIntegrationSnapshot['enabled']
    );
    $ok(
        'legacy GoogleIntegration clientId migrated',
        $gh->get('clientId') === $legacyIntegrationSnapshot['clientId']
    );
    $ok(
        'legacy GoogleIntegration clientSecret migrated',
        $gh->get('clientSecret') === $legacyIntegrationSnapshot['clientSecret']
    );
}

if ($legacyConfigSnapshot !== null) {
    $integrationsConfigAfter = $config->get('integrations') ?? (object) [];
    if (is_array($integrationsConfigAfter)) {
        $integrationsConfigAfter = (object) $integrationsConfigAfter;
    }

    $ok(
        'legacy config integrations.GoogleIntegration removed',
        is_object($integrationsConfigAfter) && !property_exists($integrationsConfigAfter, 'GoogleIntegration')
    );

    if (!$canonicalConfigExisted) {
        $ok(
            'legacy config flag migrated to GoogleCalendarDrive',
            is_object($integrationsConfigAfter)
                && property_exists($integrationsConfigAfter, GoogleIntegrationInstaller::INTEGRATION_ID)
                && (bool) $integrationsConfigAfter->{GoogleIntegrationInstaller::INTEGRATION_ID}
                    === $legacyConfigSnapshot
        );
    }
}

if ($legacyExternalAccountSnapshot !== null) {
    $migratedExternalAccount = $em->getEntityById('ExternalAccount', $legacyExternalAccountSnapshot['newId']);
    $legacyExternalAccount = $em->getEntityById('ExternalAccount', $legacyExternalAccountSnapshot['id']);

    $ok('legacy GoogleIntegration ExternalAccount removed', $legacyExternalAccount === null);
    $ok('legacy GoogleIntegration ExternalAccount migrated to GoogleCalendarDrive id', $migratedExternalAccount !== null);

    if ($migratedExternalAccount !== null && !$legacyExternalAccountSnapshot['canonicalExisted']) {
        $ok(
            'legacy ExternalAccount enabled flag migrated',
            (bool) $migratedExternalAccount->get('enabled') === $legacyExternalAccountSnapshot['enabled']
        );
        $ok(
            'legacy ExternalAccount accessToken migrated',
            $migratedExternalAccount->get('accessToken') === $legacyExternalAccountSnapshot['accessToken']
        );
        $ok(
            'legacy ExternalAccount refreshToken migrated',
            $migratedExternalAccount->get('refreshToken') === $legacyExternalAccountSnapshot['refreshToken']
        );
        $ok(
            'legacy ExternalAccount calendarSyncMode migrated',
            $migratedExternalAccount->get('calendarSyncMode') === $legacyExternalAccountSnapshot['calendarSyncMode']
        );
    }
}

$rInt403 = $client->get('/api/v1/Integration/' . GoogleIntegrationInstaller::INTEGRATION_ID);
$ok(
    'API user GET Integration/' . GoogleIntegrationInstaller::INTEGRATION_ID . ' → 403 (expected for type=api)',
    $rInt403->getStatusCode() === 403,
    'code=' . $rInt403->getStatusCode()
);

echo "\nExternalAccount calendarSyncMode hook\n";

$userId = $user->getId();
$externalAccountId = GoogleIntegrationInstaller::INTEGRATION_ID . '__' . $userId;
$externalAccount = $em->getEntityById('ExternalAccount', $externalAccountId);

if ($externalAccount === null) {
    $externalAccount = $em->createEntity('ExternalAccount', [
        'id' => $externalAccountId,
        'enabled' => true,
    ]);
}

$externalAccount->set('enabled', true);
$externalAccount->clear('calendarSyncMode');
$em->saveEntity($externalAccount);
$externalAccount = $em->getEntityById('ExternalAccount', $externalAccountId);
$ok(
    'beforeSave sets default calendarSyncMode when missing',
    $externalAccount !== null && $externalAccount->get('calendarSyncMode') === 'none',
    'mode=' . (string) ($externalAccount?->get('calendarSyncMode') ?? 'null')
);

$externalAccount->set('enabled', false);
$em->saveEntity($externalAccount);
$encoded = json_encode($externalAccount->getValueMap());
$ok('disconnect save (enabled=false) produces JSON value map', $encoded !== false && $encoded !== '');
$ok('disconnect clears enabled flag', $externalAccount->get('enabled') === false);

echo "\n=== " . ($fail === 0 ? 'ALL PASS' : "$fail FAILURE(S)") . " ===\n";
exit($fail === 0 ? 0 : 1);
