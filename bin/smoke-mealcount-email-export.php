<?php
/**
 * Smoke: reporting email export (Task 7.3.6 / 7.4.2).
 *
 * Sends CSV/XLSX exports via ReportingEmailExporter for all reporting entities.
 * On DDEV, Mailpit captures outbound mail (SMTP port 1025 inside web container).
 *
 * Usage: ddev exec php bin/smoke-mealcount-email-export.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\SafehouseCrm\Tools\Reporting\ReportingEmailExporter;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $mark = $pass ? 'PASS' : 'FAIL';
    echo "  [$mark] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$em = $container->get('entityManager');
$config = $container->get('config');
/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);

$configWriter = $injectableFactory->create(ConfigWriter::class);
$smtpBackup = [
    'smtpServer' => $config->get('smtpServer'),
    'smtpPort' => $config->get('smtpPort'),
    'smtpAuth' => $config->get('smtpAuth'),
    'smtpSecurity' => $config->get('smtpSecurity'),
    'smtpUsername' => $config->get('smtpUsername'),
    'outboundEmailFromAddress' => $config->get('outboundEmailFromAddress'),
    'outboundEmailFromName' => $config->get('outboundEmailFromName'),
];

$configWriter->setMultiple([
    'smtpServer' => '127.0.0.1',
    'smtpPort' => 1025,
    'smtpAuth' => false,
    'smtpSecurity' => null,
    'smtpUsername' => null,
    'outboundEmailFromAddress' => 'smoke@nonprofit-espocrm.test',
    'outboundEmailFromName' => 'Safehouse Smoke',
]);
$configWriter->save();
$config->update();

$created = [];
$prefix = 'SMOKE-Email-' . date('Ymd') . '-';
$today = date('Y-m-d');
$recipient = 'smoke-reporting-export@example.test';

echo "ReportingEmailExporter\n";

try {
    $exporter = $injectableFactory->create(ReportingEmailExporter::class);

    $mealCount = $em->getNewEntity('MealCount');
    $mealCount->set([
        'name' => $prefix . 'meal',
        'date' => $today,
        'adults' => 4,
        'minors' => 2,
    ]);
    $em->saveEntity($mealCount);
    $created[] = $mealCount;

    try {
        $exporter->send([
            'entityType' => 'MealCount',
            'where' => [['type' => 'equals', 'attribute' => 'id', 'value' => $mealCount->getId()]],
            'format' => 'csv',
            'includeTotals' => true,
            'emailAddressList' => [$recipient],
        ]);
        $ok('MealCount CSV email export sent', true, "to=$recipient");
    } catch (\Throwable $e) {
        $ok('MealCount CSV email export sent', false, $e->getMessage());
    }

    $account = $em->getRDBRepository('Account')->where(['deleted' => false])->findOne();

    if ($account === null) {
        $account = $em->getNewEntity('Account');
        $account->set('name', $prefix . 'Account');
        $em->saveEntity($account);
        $created[] = $account;
    }

    $assoc = $em->getNewEntity('AssociationMealCount');
    $assoc->set([
        'accountId' => $account->getId(),
        'date' => $today,
        'portionCount' => 15,
    ]);
    $em->saveEntity($assoc);
    $created[] = $assoc;

    try {
        $exporter->send([
            'entityType' => 'AssociationMealCount',
            'where' => [['type' => 'equals', 'attribute' => 'id', 'value' => $assoc->getId()]],
            'format' => 'xlsx',
            'includeTotals' => true,
            'emailAddressList' => [$recipient],
        ]);
        $ok('AssociationMealCount XLSX email export sent', true, "to=$recipient");
    } catch (\Throwable $e) {
        $ok('AssociationMealCount XLSX email export sent', false, $e->getMessage());
    }

    echo "\nRoutes + frontend\n";

    $routesPath = 'custom/Espo/Modules/SafehouseCrm/Resources/routes.json';
    $routesJson = is_readable($routesPath) ? json_decode(file_get_contents($routesPath), true) : null;
    $routePaths = is_array($routesJson) ? array_column($routesJson, 'route') : [];

    $ok('routes.json reporting/email-export registered', in_array('/SafehouseCrm/reporting/email-export', $routePaths, true));

    foreach (['MealCount', 'AssociationMealCount'] as $entityType) {
        $clientDefsPath = "custom/Espo/Modules/SafehouseCrm/Resources/metadata/clientDefs/{$entityType}.json";
        $clientDefs = is_readable($clientDefsPath)
            ? json_decode(file_get_contents($clientDefsPath), true)
            : null;
        $buttons = $clientDefs['menu']['list']['buttons'] ?? [];
        $hasEmailButton = false;

        foreach ($buttons as $button) {
            if (($button['name'] ?? '') === 'reportingEmailExport') {
                $hasEmailButton = true;
                break;
            }
        }

        $ok("$entityType list menu reportingEmailExport button", $hasEmailButton);
    }

    $ok(
        'reporting-email-export modal JS exists',
        is_readable('client/custom/modules/safehouse-crm/src/views/modals/reporting-email-export.js')
    );
} finally {
    foreach ($created as $entity) {
        $em->removeEntity($entity);
    }

    $writer = $injectableFactory->create(ConfigWriter::class);

    foreach ($smtpBackup as $key => $value) {
        $writer->set($key, $value);
    }

    $writer->save();
    $config->update();
}

echo "\n" . ($fail === 0 ? 'ALL PASS' : "FAILED ($fail)") . "\n";
exit($fail > 0 ? 1 : 0);
