<?php

declare(strict_types=1);


require __DIR__ . '/lib/refuse-production.php';


/**
 * Clear Admin "system SMTP" (config smtp*) so Espo uses the Group Email Account
 * matching outboundEmailFromAddress — same path as "Send Test Email".
 *
 * Mailpit is for smokes only (bin/smoke-*-email*.php temporarily retargets the
 * group account). Do not permanently point interactive Espo at Mailpit.
 *
 * Usage:
 *   ddev exec php bin/setup-mailpit-smtp.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);
$config = $container->getByClass(Config::class);
$configWriter = $injectableFactory->create(ConfigWriter::class);

$fromAddress = $config->get('outboundEmailFromAddress') ?: 'noreply@nonprofit-espocrm.ddev.site';
$fromName = $config->get('outboundEmailFromName') ?: 'Safehouse CRM';

$configWriter->setMultiple([
    'smtpServer' => null,
    'smtpPort' => null,
    'smtpAuth' => false,
    'smtpSecurity' => null,
    'smtpUsername' => null,
    'smtpPassword' => null,
    'outboundEmailFromAddress' => $fromAddress,
    'outboundEmailFromName' => $fromName,
]);
$configWriter->save();
$config->update();

$em = $container->get('entityManager');
$group = $em->getRDBRepository('InboundEmail')
    ->where(['status' => 'Active', 'useSmtp' => true, 'emailAddress' => $fromAddress])
    ->findOne();

echo "System SMTP config cleared (interactive mail uses Group Email Account).\n";
echo "  outbound from: {$fromName} <{$fromAddress}>\n";
if ($group) {
    echo "  Group Email SMTP host: " . (string) $group->get('smtpHost')
        . ':' . (string) $group->get('smtpPort') . "\n";
} else {
    echo "  WARNING: no Active InboundEmail with useSmtp for {$fromAddress}\n";
    echo "  Configure Admin → Group Email Accounts SMTP (e.g. Aruba).\n";
}
echo "\nSmokes capture mail via temporary Mailpit retarget (not this script).\n";
echo "Mailpit UI (smoke traffic only): https://nonprofit-espocrm.ddev.site:8026\n";
