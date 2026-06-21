<?php
/**
 * Smoke test that exercises the unified post-install path used by both the
 * ZIP-install script (`scripts/AfterInstall.php`) and the module class
 * (`Espo\Modules\SafehouseCrm\AfterInstall`).
 *
 * Asserts after the run:
 *   - `Case` is absent from `tabList` and `quickCreateList`;
 *   - `Lead` is present in `tabList` and `quickCreateList`;
 *   - all Safehouse domain entities (`VolunteerEmployee`, `Member`) are present
 *     in `tabList`;
 *   - `MealCount` lives in `$Rendicontazione` group tab (`type: group`), not as
 *     a top-level tab; `Opportunity` (F&F) is NOT in that group;
 *   - canonical roles + Administration team exist;
 *   - universal `GoogleCalendarDrive` extension post-install (Integration row, legacy cleanup);
 *   - rerunning is idempotent (counts stable).
 *
 * Usage:
 *   ddev exec php bin/smoke-installer.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Modules\GoogleIntegration\Tools\Installer as GoogleIntegrationInstaller;
use Espo\Modules\SafehouseCrm\Tools\Installer;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

$failures = 0;
$report = function (string $name, bool $pass, string $detail = '') use (&$failures): void {
    if (!$pass) {
        $failures++;
    }
    $marker = $pass ? '[PASS]' : '[FAIL]';
    echo "  $marker $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$findReportingGroup = static function (array $tabList): ?object {
    foreach ($tabList as $item) {
        if (
            is_object($item)
            && ($item->type ?? null) === 'group'
            && ($item->text ?? null) === '$Rendicontazione'
        ) {
            return $item;
        }
    }

    return null;
};

$installer = new Installer();

echo "Run 1: invoke post-install via Installer\n";
$installer->runPostInstall($container);
(new GoogleIntegrationInstaller())->runPostInstall($container);
$container->getByClass(\Espo\Core\Utils\Config::class)->update();

$config = $container->get('config');
$tabList = $config->get('tabList', []) ?? [];
$quickCreateList = $config->get('quickCreateList', []) ?? [];

$tabStrings = array_filter($tabList, 'is_string');
$qcStrings = array_filter($quickCreateList, 'is_string');

$report('Lead present in tabList', in_array('Lead', $tabStrings, true));
$report('Case absent from tabList', !in_array('Case', $tabStrings, true));
$report('Lead present in quickCreateList', in_array('Lead', $qcStrings, true));
$report('Case absent from quickCreateList', !in_array('Case', $qcStrings, true));

foreach (['VolunteerEmployee', 'Member', 'Account', 'Opportunity', 'Document'] as $must) {
    $report("$must present in tabList", in_array($must, $tabStrings, true));
}

$reportingGroup = $findReportingGroup($tabList);
$groupItemList = $reportingGroup !== null ? ($reportingGroup->itemList ?? []) : [];

$report('$Rendicontazione group tab present (type=group)', $reportingGroup !== null);
$report('Legacy $Rendicontazione divider absent', !array_reduce(
    $tabList,
    static function (bool $found, $item): bool {
        return $found || (
            is_object($item)
            && ($item->type ?? null) === 'divider'
            && ($item->text ?? null) === '$Rendicontazione'
        );
    },
    false
));
$report('MealCount in Rendicontazione group itemList', in_array('MealCount', $groupItemList, true));
$report('AssociationMealCount in Rendicontazione group itemList', in_array('AssociationMealCount', $groupItemList, true));
$report('MealCount not a top-level tab', !in_array('MealCount', $tabStrings, true));
$report('AssociationMealCount not a top-level tab', !in_array('AssociationMealCount', $tabStrings, true));
$report('Opportunity NOT in Rendicontazione group', !in_array('Opportunity', $groupItemList, true));

$opportunityIndex = null;
$groupIndex = null;
foreach ($tabList as $i => $item) {
    if ($item === 'Opportunity') {
        $opportunityIndex = $i;
    }
    if (
        is_object($item)
        && ($item->type ?? null) === 'group'
        && ($item->text ?? null) === '$Rendicontazione'
    ) {
        $groupIndex = $i;
    }
}
$report(
    'Opportunity tab before Rendicontazione group',
    $opportunityIndex !== null
        && $groupIndex !== null
        && $opportunityIndex < $groupIndex
);

$em = $container->get('entityManager');
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
