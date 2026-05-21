<?php
/**
 * REST smoke for the standalone **`GoogleCalendarDrive`** Espo extension (universal
 * Google OAuth2: Calendar + `drive.file` Drive scope via core ExternalAccount).
 *
 * 1) Runs {@see \Espo\Modules\GoogleIntegration\Tools\Installer} (idempotent:
 *    DB row, legacy id migration, rebuild).
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
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
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

echo "\nLegacy id migration regression\n";
$legacyIntegrationIds = ['GoogleIntegration', 'GoogleSafehouse'];
$externalAccountId = GoogleIntegrationInstaller::INTEGRATION_ID . '__' . $user->getId();
$legacyExternalAccountId = 'GoogleIntegration__' . $user->getId();
$existingTargetExternalAccount = $em->getEntityById('ExternalAccount', $externalAccountId);
if ($existingTargetExternalAccount !== null) {
    $em->removeEntity($existingTargetExternalAccount);
}
$existingLegacyExternalAccount = $em->getEntityById('ExternalAccount', $legacyExternalAccountId);
if ($existingLegacyExternalAccount !== null) {
    $em->removeEntity($existingLegacyExternalAccount);
}
$legacyExternalAccount = $em->getNewEntity('ExternalAccount');
$legacyExternalAccount->set('id', $legacyExternalAccountId);
$legacyExternalAccount->set('enabled', true);
$legacyExternalAccount->set('accessToken', 'smoke-legacy-access-token');
$legacyExternalAccount->set('refreshToken', 'smoke-legacy-refresh-token');
$legacyExternalAccount->set('tokenType', 'Bearer');
$legacyExternalAccount->set('calendarSyncMode', 'crmToGoogle');
$em->saveEntity($legacyExternalAccount);

$normalizeIntegrations = static function (mixed $integrations): stdClass {
    if ($integrations instanceof stdClass) {
        return (object) get_object_vars($integrations);
    }
    if (is_array($integrations)) {
        return (object) $integrations;
    }

    return (object) [];
};
$originalIntegrations = $normalizeIntegrations($config->get('integrations'));
$testIntegrations = (object) get_object_vars($originalIntegrations);
unset($testIntegrations->{GoogleIntegrationInstaller::INTEGRATION_ID});
$testIntegrations->GoogleIntegration = true;
/** @var ConfigWriter $configWriter */
$configWriter = $container->getByClass(InjectableFactory::class)->create(ConfigWriter::class);
$configWriter->set('integrations', $testIntegrations);
$configWriter->save();
$config->update();

(new GoogleIntegrationInstaller())->runPostInstall($container);
$config->update();

$migratedExternalAccount = $em->getEntityById('ExternalAccount', $externalAccountId);
$ok('legacy ExternalAccount id removed', $em->getEntityById('ExternalAccount', $legacyExternalAccountId) === null);
$ok('ExternalAccount migrated to GoogleCalendarDrive id', $migratedExternalAccount !== null);
$ok(
    'ExternalAccount token data preserved',
    $migratedExternalAccount !== null
    && $migratedExternalAccount->get('accessToken') === 'smoke-legacy-access-token'
    && $migratedExternalAccount->get('refreshToken') === 'smoke-legacy-refresh-token'
);
$ok(
    'ExternalAccount calendarSyncMode preserved',
    $migratedExternalAccount !== null && $migratedExternalAccount->get('calendarSyncMode') === 'crmToGoogle',
    'mode=' . (string) ($migratedExternalAccount?->get('calendarSyncMode') ?? 'null')
);
$integrationsAfter = $normalizeIntegrations($config->get('integrations'));
$ok('legacy integration config key removed', !property_exists($integrationsAfter, 'GoogleIntegration'));
$ok(
    'legacy integration config migrated to GoogleCalendarDrive',
    ($integrationsAfter->{GoogleIntegrationInstaller::INTEGRATION_ID} ?? null) === true
);

$restoredIntegrations = (object) get_object_vars($originalIntegrations);
$originalLegacyEnabled = null;
foreach ($legacyIntegrationIds as $legacyIntegrationId) {
    if ($originalLegacyEnabled === null && property_exists($originalIntegrations, $legacyIntegrationId)) {
        $originalLegacyEnabled = (bool) $originalIntegrations->$legacyIntegrationId;
    }

    unset($restoredIntegrations->$legacyIntegrationId);
}
if (!property_exists($originalIntegrations, GoogleIntegrationInstaller::INTEGRATION_ID) && $originalLegacyEnabled !== null) {
    $restoredIntegrations->{GoogleIntegrationInstaller::INTEGRATION_ID} = $originalLegacyEnabled;
} elseif (!property_exists($originalIntegrations, GoogleIntegrationInstaller::INTEGRATION_ID)) {
    unset($restoredIntegrations->{GoogleIntegrationInstaller::INTEGRATION_ID});
}
$configWriter->set('integrations', $restoredIntegrations);
$configWriter->save();
$config->update();

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
