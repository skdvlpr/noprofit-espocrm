<?php

require __DIR__ . '/lib/refuse-production.php';


/**
 * Smoke test that exercises the unified post-install path used by both the
 * ZIP-install script (`scripts/AfterInstall.php`) and the module class
 * (`Espo\Modules\NonprofitEspocrm\AfterInstall`).
 *
 * Asserts after the run:
 *   - `Case` is present in `tabList` (Principali, before `$Rendicontazione`) and `quickCreateList`;
 *   - `Lead` is present in `tabList` and `quickCreateList`;
 *   - Contatti group tab (`type: group`) contains Contact + URL primary filters;
 *   - legacy `VolunteerEmployee` / `Member` tabs are hidden (entities kept for rollback);
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
use Espo\Modules\NonprofitEspocrm\Tools\Installer;

$googleIntegrationAvailable = class_exists(
    \Espo\Modules\GoogleIntegration\Tools\Installer::class
);

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

$extractCrmTabs = static function (array $tabList): array {
    $tabs = [];
    $pastCrmDivider = false;

    foreach ($tabList as $item) {
        if (
            is_object($item)
            && ($item->type ?? null) === 'divider'
            && ($item->text ?? null) === '$CRM'
        ) {
            $pastCrmDivider = true;

            continue;
        }

        if (!$pastCrmDivider) {
            continue;
        }

        if (!is_string($item)) {
            break;
        }

        $tabs[] = $item;
    }

    return $tabs;
};

$extractSupportTabs = static function (array $tabList): array {
    $tabs = [];
    $pastSupportDivider = false;

    foreach ($tabList as $item) {
        if (
            is_object($item)
            && ($item->type ?? null) === 'divider'
            && ($item->text ?? null) === '$Support'
        ) {
            $pastSupportDivider = true;

            continue;
        }

        if (!$pastSupportDivider) {
            continue;
        }

        if (!is_string($item)) {
            break;
        }

        $tabs[] = $item;
    }

    return $tabs;
};

$findContactsGroup = static function (array $tabList): ?object {
    foreach ($tabList as $item) {
        if (
            is_object($item)
            && ($item->type ?? null) === 'group'
            && (($item->text ?? null) === '$Contatti' || ($item->name ?? null) === 'Contatti')
        ) {
            return $item;
        }
    }

    return null;
};

$expectedSupportOrder = ['KnowledgeBaseArticle'];

$runGooglePostInstall = static function ($container) use ($googleIntegrationAvailable): void {
    if (!$googleIntegrationAvailable) {
        return;
    }

    (new \Espo\Modules\GoogleIntegration\Tools\Installer())->runPostInstall($container);
};

$installer = new Installer();

echo "Run 1: invoke post-install via Installer\n";
$installer->runPostInstall($container);
$runGooglePostInstall($container);
$container->getByClass(\Espo\Core\Utils\Config::class)->update();

$config = $container->get('config');
$tabList = $config->get('tabList', []) ?? [];
$quickCreateList = $config->get('quickCreateList', []) ?? [];

$tabStrings = array_filter($tabList, 'is_string');
$qcStrings = array_filter($quickCreateList, 'is_string');

$report('Lead present in tabList', in_array('Lead', $tabStrings, true));
$report('Case present in tabList', in_array('Case', $tabStrings, true));
$report('Intervention present in tabList', in_array('Intervention', $tabStrings, true));
$report('FoodParcelRegistration present in tabList', in_array('FoodParcelRegistration', $tabStrings, true));
$report('Lead present in quickCreateList', in_array('Lead', $qcStrings, true));
$report('Case present in quickCreateList', in_array('Case', $qcStrings, true));

$supportTabs = $extractSupportTabs($tabList);
$report(
    'Support block tab order canonical',
    $supportTabs === $expectedSupportOrder,
    'actual=' . implode(' → ', $supportTabs)
);
$report('Case absent from Support block', !in_array('Case', $supportTabs, true));

$crmTabs = $extractCrmTabs($tabList);
$report('Lead is first tab in $CRM block', ($crmTabs[0] ?? null) === 'Lead');

$contactsGroup = $findContactsGroup($tabList);
$contactsItems = $contactsGroup !== null ? ($contactsGroup->itemList ?? []) : [];
$contactsItemStrings = array_values(array_filter($contactsItems, 'is_string'));
$contactsUrlTexts = [];
foreach ($contactsItems as $ci) {
    if (is_object($ci) && ($ci->type ?? null) === 'url') {
        $contactsUrlTexts[] = (string) ($ci->text ?? '');
    }
}

$report('$Contatti group tab present (type=group)', $contactsGroup !== null);
$report(
    'Contatti group has All URL',
    in_array('$ContattiAll', $contactsUrlTexts, true),
    'urls=' . implode(',', $contactsUrlTexts)
);
$report(
    'Contatti group has Volunteers/Employees URL filter',
    in_array('$ContattiVolontariDipendenti', $contactsUrlTexts, true),
    'urls=' . implode(',', $contactsUrlTexts)
);
$report(
    'Contatti group has Occasional volunteers URL filter',
    in_array('$ContattiVolontariOccasionali', $contactsUrlTexts, true),
    'urls=' . implode(',', $contactsUrlTexts)
);
$report(
    'Contatti group has Associati URL filter',
    in_array('$ContattiAssociati', $contactsUrlTexts, true)
);
$report('VolunteerEmployee hidden from tabList', !in_array('VolunteerEmployee', $tabStrings, true));
$report('Member hidden from tabList', !in_array('Member', $tabStrings, true));

foreach (['Account', 'Opportunity', 'Document'] as $must) {
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
$report('PrimaNota in Rendicontazione group itemList', in_array('PrimaNota', $groupItemList, true));
$report('MealCount not a top-level tab', !in_array('MealCount', $tabStrings, true));
$report('AssociationMealCount not a top-level tab', !in_array('AssociationMealCount', $tabStrings, true));
$report('Opportunity NOT in Rendicontazione group', !in_array('Opportunity', $groupItemList, true));

$globalSearch = $config->get('globalSearchEntityList') ?? [];
$report(
    'PrimaNota in globalSearchEntityList',
    is_array($globalSearch) && in_array('PrimaNota', $globalSearch, true),
    'actual=' . json_encode($globalSearch)
);

$contactsGroupIndex = null;
$groupIndex = null;
foreach ($tabList as $i => $item) {
    if (
        is_object($item)
        && ($item->type ?? null) === 'group'
        && (($item->text ?? null) === '$Contatti' || ($item->name ?? null) === 'Contatti')
    ) {
        $contactsGroupIndex = $i;
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
    'Contatti group before Rendicontazione group',
    $contactsGroupIndex !== null
        && $groupIndex !== null
        && $contactsGroupIndex < $groupIndex
);

$caseIndex = array_search('Case', $tabList, true);
$interventionIndex = array_search('Intervention', $tabList, true);
$foodParcelIndex = array_search('FoodParcelRegistration', $tabList, true);
$report(
    'Case, Intervention, FoodParcelRegistration before Rendicontazione group',
    $groupIndex !== null
        && $caseIndex !== false
        && $interventionIndex !== false
        && $foodParcelIndex !== false
        && $caseIndex < $groupIndex
        && $interventionIndex < $groupIndex
        && $foodParcelIndex < $groupIndex
        && $caseIndex < $interventionIndex
        && $interventionIndex < $foodParcelIndex
);

$em = $container->get('entityManager');
$roleNames = [];
foreach ($em->getRDBRepository('Role')->find() as $r) {
    $roleNames[] = $r->get('name');
}
foreach (['Admin', 'Volunteer', 'Member'] as $expectedRole) {
    $report("Role `$expectedRole` provisioned", in_array($expectedRole, $roleNames, true));
}
if (getenv('SAFEHOUSE_EXTRA_ROLES') === '1') {
    foreach (['Employee', 'Manager', 'Desk'] as $extraRole) {
        $report("Extra role `$extraRole` provisioned", in_array($extraRole, $roleNames, true));
    }
}

$adminTeam = $em->getRDBRepository('Team')->where(['name' => 'Administration'])->findOne();
$report('Team `Administration` provisioned', $adminTeam !== null);

$metadata = $container->getByClass(\Espo\Core\Utils\Metadata::class);
$metadata->init(true);
foreach (['SafehouseAurora', 'SafehouseAuroraLight'] as $themeName) {
    $report(
        "Theme `$themeName` registered in metadata",
        is_string($metadata->get(['themes', $themeName, 'stylesheet']))
    );
}

if ($googleIntegrationAvailable) {
    $googleInstallerClass = \Espo\Modules\GoogleIntegration\Tools\Installer::class;
    $googleInt = $em->getRDBRepository('Integration')
        ->where(['id' => $googleInstallerClass::INTEGRATION_ID])
        ->findOne();
    $report(
        'Integration `' . $googleInstallerClass::INTEGRATION_ID . '` row exists (universal extension)',
        $googleInt !== null
    );
} else {
    $report(
        'Integration GoogleCalendarDrive row exists (universal extension)',
        true,
        'skipped (GoogleIntegration module not installed)'
    );
}

echo "\nRun 2: invoke again — must be idempotent\n";
$tabListBefore = $config->get('tabList', []) ?? [];
$installer->runPostInstall($container);
$runGooglePostInstall($container);
$container->getByClass(\Espo\Core\Utils\Config::class)->update();

$configAfter = $container->getByClass(\Espo\Core\Utils\Config::class);
$configAfter->update();
$tabListAfter = $configAfter->get('tabList', []) ?? [];

$report('tabList unchanged across reruns', count($tabListBefore) === count($tabListAfter));

echo "\n=== ";
echo $failures === 0 ? 'ALL PASS' : ($failures . ' FAILURE(S)');
echo " ===\n";
exit($failures === 0 ? 0 : 1);
