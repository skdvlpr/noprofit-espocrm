<?php

declare(strict_types=1);


require __DIR__ . '/lib/refuse-production.php';


/**
 * Smoke: reporting email export (Task 7.3.6 / 7.4.2).
 *
 * Sends CSV/XLSX exports via ReportingEmailExporter for all reporting entities.
 * Temporarily retargets the system Group Email Account SMTP to DDEV Mailpit
 * for capture only — restores Aruba/real SMTP in finally. Interactive Espo
 * mail keeps using the instance-configured Group Email Account.
 *
 * Usage: ddev exec php bin/smoke-mealcount-email-export.php
 */

include __DIR__ . '/../bootstrap.php';
require __DIR__ . '/lib/mailpit-smtp-for-smoke.php';

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

// Clear Admin system smtp* if a previous Mailpit helper left them set —
// EmailSender uses Group Email Account when smtpServer is empty.
$configWriter = $injectableFactory->create(ConfigWriter::class);
$configSmtpBackup = [
    'smtpServer' => $config->get('smtpServer'),
    'smtpPort' => $config->get('smtpPort'),
    'smtpAuth' => $config->get('smtpAuth'),
    'smtpSecurity' => $config->get('smtpSecurity'),
    'smtpUsername' => $config->get('smtpUsername'),
    'smtpPassword' => $config->get('smtpPassword'),
];
$configWriter->setMultiple([
    'smtpServer' => null,
    'smtpPort' => null,
    'smtpAuth' => false,
    'smtpSecurity' => null,
    'smtpUsername' => null,
    'smtpPassword' => null,
]);
$configWriter->save();
$config->update();

$mailpitArmed = safehouse_mailpit_smoke_arm($em, $config);
$ok(
    'Mailpit armed on system Group Email Account',
    $mailpitArmed['account'] !== null,
    $mailpitArmed['account']
        ? (string) $mailpitArmed['account']->get('emailAddress')
        : 'no Active InboundEmail for outboundEmailFromAddress — set Group Email SMTP'
);

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
    if ($mailpitArmed['account'] === null) {
        throw new RuntimeException(
            'Cannot smoke email export: system Group Email Account missing for '
            . (string) $config->get('outboundEmailFromAddress')
        );
    }

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

    $globalDefsPath = 'custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/Global.json';
    $globalDefs = is_readable($globalDefsPath)
        ? json_decode(file_get_contents($globalDefsPath), true)
        : null;
    $globalButtons = $globalDefs['menu']['list']['buttons'] ?? [];
    $hasGlobalEmailButton = false;

    foreach ($globalButtons as $button) {
        if (($button['name'] ?? '') === 'reportingEmailExport') {
            $hasGlobalEmailButton = true;
            break;
        }
    }

    $ok('Global list menu reportingEmailExport (all entities)', $hasGlobalEmailButton);

    $ok(
        'reporting export modal JS exists',
        is_readable('client/custom/modules/nonprofit-espocrm/src/views/export/modals/export.js')
    );

    // Account (non-reporting): email export must send, never download-only path.
    try {
        $exporter = $injectableFactory->create(ReportingEmailExporter::class);
        $exporter->send([
            'entityType' => 'Account',
            'format' => 'xlsx-email',
            'params' => [
                'emailTo' => $recipient,
                'includeTotals' => true,
            ],
        ]);
        $ok('Account XLSX email export sent', true, "to=$recipient");
    } catch (Throwable $e) {
        $ok('Account XLSX email export sent', false, $e->getMessage());
    }
} finally {
    foreach ($created as $entity) {
        $em->removeEntity($entity);
    }

    safehouse_mailpit_smoke_disarm($em, $mailpitArmed);

    $writer = $injectableFactory->create(ConfigWriter::class);

    foreach ($configSmtpBackup as $key => $value) {
        // Prefer leaving Admin system SMTP empty so UI uses Group Email Account.
        // Only restore a non-Mailpit host if the instance had a real one.
        if ($key === 'smtpServer' && ($value === '127.0.0.1' || $value === 'localhost')) {
            $writer->set($key, null);
            continue;
        }
        if (in_array($key, ['smtpPort', 'smtpAuth', 'smtpSecurity', 'smtpUsername', 'smtpPassword'], true)
            && ($configSmtpBackup['smtpServer'] === '127.0.0.1'
                || $configSmtpBackup['smtpServer'] === 'localhost'
                || $configSmtpBackup['smtpServer'] === null
                || $configSmtpBackup['smtpServer'] === '')
        ) {
            $writer->set($key, $key === 'smtpAuth' ? false : null);
            continue;
        }
        $writer->set($key, $value);
    }

    $writer->save();
    $config->update();
}

echo "\n" . ($fail === 0 ? 'ALL PASS' : "FAILED ($fail)") . "\n";
exit($fail > 0 ? 1 : 0);
