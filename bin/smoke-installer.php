<?php
/**
 * Smoke test that exercises the unified post-install path used by both the
 * ZIP-install script (`scripts/AfterInstall.php`) and the module class
 * (`Espo\Modules\SafehouseCrm\AfterInstall`).
 *
 * Asserts after the run:
 *   - `Lead` and `Case` are absent from `tabList` and `quickCreateList`;
 *   - all Safehouse domain entities (`VolunteerEmployee`, `Member`,
 *     `MealCount`) are present in `tabList`;
 *   - canonical roles + Administration team exist;
 *   - universal `GoogleCalendarDrive` extension post-install (Integration row, legacy migration);
 *   - rerunning is idempotent (counts stable).
 *
 * Usage:
 *   ddev exec php bin/smoke-installer.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\GoogleIntegration\Tools\Installer as GoogleIntegrationInstaller;
use Espo\Modules\SafehouseCrm\Tools\Installer;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
$em = $container->get('entityManager');

$failures = 0;
$report = function (string $name, bool $pass, string $detail = '') use (&$failures): void {
    if (!$pass) {
        $failures++;
    }
    $marker = $pass ? '[PASS]' : '[FAIL]';
    echo "  $marker $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$installer = new Installer();
$seededGoogleLegacyMigration = false;
$legacyExternalAccountId = 'GoogleIntegration__smokeLegacyInstaller';
$migratedExternalAccountId = GoogleIntegrationInstaller::INTEGRATION_ID . '__smokeLegacyInstaller';

$isGoogleIntegrationConfigured = static function ($entity): bool {
    if ($entity === null) {
        return false;
    }
    if ((bool) $entity->get('enabled')) {
        return true;
    }
    foreach (['clientId', 'clientSecret'] as $field) {
        $value = $entity->get($field);
        if (is_string($value) && $value !== '') {
            return true;
        }
    }

    return false;
};

$canonicalBefore = $em->getRDBRepository('Integration')
    ->where(['id' => GoogleIntegrationInstaller::INTEGRATION_ID])
    ->findOne();
$legacyBefore = $em->getRDBRepository('Integration')
    ->where(['id' => 'GoogleIntegration'])
    ->findOne();

if ($canonicalBefore === null && $legacyBefore === null) {
    $legacy = $em->createEntity('Integration', [
        'id' => 'GoogleIntegration',
        'enabled' => true,
        'clientId' => 'smoke-client-id',
        'clientSecret' => 'smoke-client-secret',
    ]);
    $em->saveEntity($legacy);

    $legacyExternal = $em->createEntity('ExternalAccount', [
        'id' => $legacyExternalAccountId,
        'enabled' => true,
        'accessToken' => 'smoke-access-token',
        'refreshToken' => 'smoke-refresh-token',
    ]);
    $em->saveEntity($legacyExternal, [SaveOption::SKIP_ALL => true]);

    $seededGoogleLegacyMigration = true;
}

echo "Run 1: invoke post-install via Installer\n";
$installer->runPostInstall($container);
(new GoogleIntegrationInstaller())->runPostInstall($container);
$container->getByClass(\Espo\Core\Utils\Config::class)->update();

$config = $container->get('config');
$tabList = $config->get('tabList', []) ?? [];
$quickCreateList = $config->get('quickCreateList', []) ?? [];

$tabStrings = array_filter($tabList, 'is_string');
$qcStrings = array_filter($quickCreateList, 'is_string');

$report('Lead absent from tabList', !in_array('Lead', $tabStrings, true));
$report('Case absent from tabList', !in_array('Case', $tabStrings, true));
$report('Lead absent from quickCreateList', !in_array('Lead', $qcStrings, true));
$report('Case absent from quickCreateList', !in_array('Case', $qcStrings, true));

foreach (['VolunteerEmployee', 'Member', 'MealCount', 'Account', 'Opportunity', 'Document'] as $must) {
    $report("$must present in tabList", in_array($must, $tabStrings, true));
}

$roleNames = [];
foreach ($em->getRDBRepository('Role')->find() as $r) {
    $roleNames[] = $r->get('name');
}
foreach (['Admin', 'Employee', 'Manager', 'Volunteer', 'Member'] as $expectedRole) {
    $report("Role `$expectedRole` provisioned", in_array($expectedRole, $roleNames, true));
}

$adminTeam = $em->getRDBRepository('Team')->where(['name' => 'Administration'])->findOne();
$report('Team `Administration` provisioned', $adminTeam !== null);

$googleInt = $em->getRDBRepository('Integration')
    ->where(['id' => GoogleIntegrationInstaller::INTEGRATION_ID])
    ->findOne();
$report('Integration `' . GoogleIntegrationInstaller::INTEGRATION_ID . '` row exists (universal extension)', $googleInt !== null);

if ($seededGoogleLegacyMigration) {
    $legacyAfter = $em->getRDBRepository('Integration')
        ->where(['id' => 'GoogleIntegration'])
        ->findOne();
    $report('Legacy Integration `GoogleIntegration` removed after migration', $legacyAfter === null);
    $report(
        'Google integration migration preserves admin OAuth fields',
        $googleInt !== null
            && $googleInt->get('enabled') === true
            && $googleInt->get('clientId') === 'smoke-client-id'
            && $googleInt->get('clientSecret') === 'smoke-client-secret'
    );

    $legacyExternalAfter = $em->getEntityById('ExternalAccount', $legacyExternalAccountId);
    $migratedExternal = $em->getEntityById('ExternalAccount', $migratedExternalAccountId);
    $report('Legacy ExternalAccount row removed after migration', $legacyExternalAfter === null);
    $report(
        'Google external account migration preserves tokens',
        $migratedExternal !== null
            && $migratedExternal->get('enabled') === true
            && $migratedExternal->get('accessToken') === 'smoke-access-token'
            && $migratedExternal->get('refreshToken') === 'smoke-refresh-token'
    );

    if ($googleInt !== null && $isGoogleIntegrationConfigured($googleInt)) {
        $googleInt->set('enabled', false);
        $googleInt->clear('clientId');
        $googleInt->clear('clientSecret');
        $em->saveEntity($googleInt);
    }
    if ($migratedExternal !== null) {
        $em->removeEntity($migratedExternal, [SaveOption::SKIP_ALL => true]);
    }

    $configWriter = $container->getByClass(InjectableFactory::class)->create(ConfigWriter::class);
    $integrations = $config->get('integrations') ?? (object) [];
    $integrations = is_object($integrations) ? $integrations : (object) [];
    $integrations->{GoogleIntegrationInstaller::INTEGRATION_ID} = false;
    unset($integrations->GoogleIntegration, $integrations->GoogleSafehouse);
    $configWriter->set('integrations', $integrations);
    $configWriter->save();
    $config->update();
}

echo "\nRun 2: invoke again — must be idempotent\n";
$tabListBefore = $config->get('tabList', []) ?? [];
$installer->runPostInstall($container);
(new GoogleIntegrationInstaller())->runPostInstall($container);
$container->getByClass(\Espo\Core\Utils\Config::class)->update();

$configAfter = $container->getByClass(\Espo\Core\Utils\Config::class);
$configAfter->update();
$tabListAfter = $configAfter->get('tabList', []) ?? [];

$report('tabList unchanged across reruns', count($tabListBefore) === count($tabListAfter));

echo "\n=== ";
echo $failures === 0 ? 'ALL PASS' : ($failures . ' FAILURE(S)');
echo " ===\n";
exit($failures === 0 ? 0 : 1);
