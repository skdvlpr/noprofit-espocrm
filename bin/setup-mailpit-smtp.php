<?php

declare(strict_types=1);


require __DIR__ . '/lib/refuse-production.php';


/**
 * Configure Espo outbound SMTP for DDEV Mailpit (SMTP inside web container).
 *
 * Does NOT send mail — only writes config via ConfigWriter.
 *
 * Usage:
 *   ddev exec php bin/setup-mailpit-smtp.php
 *
 * Mailpit web UI (after sending from Espo):
 *   https://nonprofit-espocrm.ddev.site:8026
 *   or: ddev mailpit
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
    'smtpServer' => '127.0.0.1',
    'smtpPort' => 1025,
    'smtpAuth' => false,
    'smtpSecurity' => null,
    'smtpUsername' => null,
    'smtpPassword' => null,
    'outboundEmailFromAddress' => $fromAddress,
    'outboundEmailFromName' => $fromName,
]);
$configWriter->save();
$config->update();

echo "Mailpit SMTP configured for DDEV:\n";
echo "  smtpServer: 127.0.0.1\n";
echo "  smtpPort:   1025 (SMTP — not 8025/8026 web UI)\n";
echo "  smtpAuth:   false\n";
echo "  from:       {$fromName} <{$fromAddress}>\n";
echo "\nOpen Mailpit: https://nonprofit-espocrm.ddev.site:8026\n";
echo "Or run: ddev mailpit\n";
