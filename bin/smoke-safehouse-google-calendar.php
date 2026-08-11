<?php

declare(strict_types=1);


require __DIR__ . '/lib/refuse-production.php';


/**
 * Smoke: Safehouse-specific Google Calendar seeds (requires SafehouseCrm + GoogleIntegration).
 *
 * Usage:
 *   ddev exec php bin/smoke-safehouse-google-calendar.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Metadata;
use Espo\Modules\NonprofitEspocrm\Tools\Calendar\SafehouseGoogleCalendarProvisioner;
use Espo\Modules\NonprofitEspocrm\Tools\Installer as SafehouseInstaller;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

if (!class_exists(SafehouseGoogleCalendarProvisioner::class)) {
    fwrite(STDERR, "SKIP: SafehouseCrm not installed.\n");
    exit(0);
}

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $m = $pass ? 'PASS' : 'FAIL';
    echo "  [$m] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

echo "Provisioning Safehouse Google Calendar seeds\n";
(new SafehouseInstaller())->runPostInstall($container);

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);
$metadata->init(true);

$enGlobal = json_decode(
    (string) file_get_contents(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/en_US/Global.json'),
    true
) ?: [];
$ok(
    'Safehouse Global renames Opportunity to Grants & Funding',
    ($enGlobal['scopeNames']['Opportunity'] ?? '') === 'Grants & Funding'
);

$integrationOppDefault = (string) ($metadata->get([
    'integrations',
    'GoogleCalendarDrive',
    'fields',
    'googleCalendarDescriptionTemplateOpportunity',
    'default',
]) ?? '');
$ok(
    'Merged integration Opportunity template uses Safehouse branding',
    str_contains($integrationOppDefault, 'Grants & Funding')
        && str_contains($integrationOppDefault, 'presentationDate')
);
$ok(
    'Merged integration has no VolunteerEmployee description template field',
    $metadata->get([
        'integrations',
        'GoogleCalendarDrive',
        'fields',
        'googleCalendarDescriptionTemplateVolunteerEmployee',
    ]) === null
);

foreach ([
    'Opportunity:presentationDate',
] as $sourceKey) {
    [$entityType, $sourceDateType] = explode(':', $sourceKey);
    $row = $em->getRDBRepository('CalendarDateSource')
        ->where([
            'targetEntityType' => $entityType,
            'sourceDateType' => $sourceDateType,
            'deleted' => false,
        ])
        ->findOne();
    $ok("CalendarDateSource $sourceKey seeded", $row !== null);
}

foreach (['VolunteerEmployee', 'Member'] as $retired) {
    $row = $em->getRDBRepository('CalendarDateSource')
        ->where([
            'targetEntityType' => $retired,
            'deleted' => false,
            'isActive' => true,
        ])
        ->findOne();
    $ok("No active CalendarDateSource for retired $retired", $row === null);
}

echo "\n=== " . ($fail === 0 ? 'ALL PASS' : ($fail . ' FAILURE(S)')) . " ===\n";
exit($fail === 0 ? 0 : 1);
