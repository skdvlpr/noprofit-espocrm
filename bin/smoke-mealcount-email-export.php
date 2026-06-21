<?php
/**
 * Smoke: MealCount email export (Task 7.3.6).
 *
 * Sends a CSV export via MealCountEmailExporter to a test recipient.
 * On DDEV, Mailpit captures outbound mail (Administration → Outbound Email must be configured).
 *
 * Usage: ddev exec php bin/smoke-mealcount-email-export.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\SafehouseCrm\Tools\Reporting\MealCountEmailExporter;

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

// DDEV Mailpit (web container port 8025) — temporary smoke SMTP config.
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
$recipient = 'smoke-mealcount-export@example.test';

echo "Seed MealCount row\n";

try {
    $entity = $em->getNewEntity('MealCount');
    $entity->set([
        'name' => $prefix . 'row',
        'date' => $today,
        'adults' => 4,
        'minors' => 2,
    ]);
    $em->saveEntity($entity);
    $created[] = $entity;

    $ok('Seed row saved', $entity->getId() !== null);

    echo "\nMealCountEmailExporter\n";

    $exporter = $injectableFactory->create(MealCountEmailExporter::class);

    try {
        $exporter->send([
            'where' => [['type' => 'equals', 'attribute' => 'id', 'value' => $entity->getId()]],
            'format' => 'csv',
            'includeTotals' => true,
            'emailAddressList' => [$recipient],
        ]);

        $ok('CSV email export sent', true, "to=$recipient");
    } catch (\Throwable $e) {
        $ok('CSV email export sent', false, $e->getMessage());
    }

    try {
        $exporter->send([
            'where' => [['type' => 'equals', 'attribute' => 'id', 'value' => $entity->getId()]],
            'format' => 'xlsx',
            'includeTotals' => false,
            'emailAddressList' => [$recipient],
        ]);

        $ok('XLSX email export sent (no totals)', true, "to=$recipient");
    } catch (\Throwable $e) {
        $ok('XLSX email export sent (no totals)', false, $e->getMessage());
    }

    echo "\nFrontend handler metadata\n";

    $clientDefsPath = 'custom/Espo/Modules/SafehouseCrm/Resources/metadata/clientDefs/MealCount.json';
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

    $ok('MealCount list menu reportingEmailExport button', $hasEmailButton);
    $ok(
        'email-export handler JS exists',
        is_readable('client/custom/modules/safehouse-crm/src/handlers/reporting/email-export.js')
    );
    $ok(
        'meal-count-email-export modal JS exists',
        is_readable('client/custom/modules/safehouse-crm/src/views/modals/meal-count-email-export.js')
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
