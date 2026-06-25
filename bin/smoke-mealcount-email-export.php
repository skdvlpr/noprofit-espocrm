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
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\ReportingEmailExporter;

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
$ccRecipient = 'smoke-reporting-cc@example.test';
/** @var ?\Espo\Entities\User $crmRecipientUser */
$crmRecipientUser = $em->getRDBRepository('User')
    ->where([
        'deleted' => false,
        'emailAddress!=' => '',
    ])
    ->findOne();
$crmRecipientEmail = $crmRecipientUser ? trim((string) $crmRecipientUser->get('emailAddress')) : '';

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
            'format' => 'csv-email',
            'ids' => [$mealCount->getId()],
            'fieldList' => ['date', 'adults', 'minors', 'totalMeals'],
            'params' => (object) [
                'includeTotals' => true,
                'emailTo' => $recipient,
                'emailCc' => $ccRecipient,
            ],
        ]);
        $ok('MealCount CSV email export sent', true, "to=$recipient cc=$ccRecipient");
    } catch (\Throwable $e) {
        $ok('MealCount CSV email export sent', false, $e->getMessage());
    }

    if ($crmRecipientUser !== null && $crmRecipientEmail !== '') {
        try {
            $exporter->send([
                'entityType' => 'MealCount',
                'format' => 'csv-email',
                'ids' => [$mealCount->getId()],
                'fieldList' => ['date', 'adults', 'minors'],
                'params' => (object) [
                    'includeTotals' => false,
                    'emailTo' => $crmRecipientEmail,
                ],
            ]);
            $ok('MealCount CSV email export (native To field)', true, "user=$crmRecipientEmail");
        } catch (\Throwable $e) {
            $ok('MealCount CSV email export (native To field)', false, $e->getMessage());
        }
    } else {
        $ok('MealCount CSV email export (native To field)', true, 'skipped — no user with email in DB');
    }

    $mealCountOther = $em->getNewEntity('MealCount');
    $mealCountOther->set([
        'name' => $prefix . 'meal-other',
        'date' => $today,
        'adults' => 99,
        'minors' => 0,
    ]);
    $em->saveEntity($mealCountOther);
    $created[] = $mealCountOther;

    try {
        $export = $injectableFactory->create(\Espo\Tools\Export\Factory::class)->create();
        $params = \Espo\Tools\Export\Params::fromRaw([
            'entityType' => 'MealCount',
            'format' => 'csv',
            'ids' => [$mealCount->getId()],
        ])->withParam('includeTotals', false);
        $result = $export->setParams($params)->run();
        /** @var ?\Espo\Entities\Attachment $attachment */
        $attachment = $em->getEntityById(\Espo\Entities\Attachment::ENTITY_TYPE, $result->getAttachmentId());
        $csv = $attachment
            ? $injectableFactory->create(\Espo\Core\FileStorage\Manager::class)->getContents($attachment)
            : '';
        if ($attachment) {
            $em->removeEntity($attachment);
        }
        $hasSelected = str_contains($csv, $prefix . 'meal') || str_contains($csv, (string) $mealCount->get('adults'));
        $hasOther = str_contains($csv, $prefix . 'meal-other') || str_contains($csv, '99');
        $ok(
            'Export ids[] limits rows to selection',
            $hasSelected && !$hasOther,
            'selected=' . ($hasSelected ? 'yes' : 'no') . ' other=' . ($hasOther ? 'yes' : 'no')
        );
    } catch (\Throwable $e) {
        $ok('Export ids[] limits rows to selection', false, $e->getMessage());
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
            'format' => 'xlsx-email',
            'ids' => [$assoc->getId()],
            'fieldList' => ['date', 'portionCount'],
            'params' => (object) [
                'includeTotals' => true,
                'emailTo' => $recipient,
            ],
        ]);
        $ok('AssociationMealCount XLSX email export sent', true, "to=$recipient");
    } catch (\Throwable $e) {
        $ok('AssociationMealCount XLSX email export sent', false, $e->getMessage());
    }

    echo "\nRoutes + frontend\n";

    $exportMetaPath = 'custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/export.json';
    $exportMeta = is_readable($exportMetaPath)
        ? json_decode(file_get_contents($exportMetaPath), true)
        : null;
    $csvEmailToView = $exportMeta['formatDefs']['csv-email']['params']['fields']['emailTo']['view'] ?? null;

    $ok(
        'export.json uses native email-address-varchar for To',
        $csvEmailToView === 'views/email/fields/email-address-varchar'
    );

    $routesPath = 'custom/Espo/Modules/NonprofitEspocrm/Resources/routes.json';
    $routesJson = is_readable($routesPath) ? json_decode(file_get_contents($routesPath), true) : null;
    $routePaths = is_array($routesJson) ? array_column($routesJson, 'route') : [];

    $ok('routes.json reporting/email-export registered', in_array('/NonprofitEspocrm/reporting/email-export', $routePaths, true));

    $recipientResolverSource = file_get_contents(
        'custom/Espo/Modules/NonprofitEspocrm/Tools/Reporting/EmailRecipientResolver.php'
    ) ?: '';
    $ok(
        'reporting email recipient resolver enforces record-level read ACL',
        str_contains($recipientResolverSource, 'checkEntityRead($entity)')
    );

    foreach (['MealCount', 'AssociationMealCount'] as $entityType) {
        $clientDefsPath = "custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/{$entityType}.json";
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
        'reporting export modal JS exists',
        is_readable('client/custom/modules/nonprofit-espocrm/src/views/export/modals/export.js')
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
