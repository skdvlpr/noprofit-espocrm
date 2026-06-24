<?php
/**
 * One-shot production bootstrap after Espo web install (rsync deploy model).
 *
 *   1. Navbar / roles baseline (Installer + production ACL)
 *   2. Optional Google integration secrets
 *   3. Optional CalendarDateSource / CalendarTemplate import
 *   4. Rebuild
 *
 * Usage:
 *   php bin/provision-production.php \
 *     --integration-settings deploy/backups/integration-settings-20260624-0114.json \
 *     --google-calendar-config deploy/backups/google-calendar-config-20260624-0114.json
 *
 * Flags are optional; omit to skip that step.
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\Metadata;
use Espo\Modules\NonprofitEspocrm\Tools\Installer;
use Espo\Modules\NonprofitEspocrm\Tools\Migration\GoogleCalendarConfig;
use Espo\Modules\NonprofitEspocrm\Tools\Migration\IntegrationSettings;
use Espo\Modules\NonprofitEspocrm\Tools\ProductionAccessSetup;
use Espo\ORM\EntityManager;

$integrationPath = null;
$calendarPath = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--integration-settings' && isset($argv[$i + 1])) {
        $integrationPath = $argv[++$i];
        continue;
    }
    if ($argv[$i] === '--google-calendar-config' && isset($argv[$i + 1])) {
        $calendarPath = $argv[++$i];
        continue;
    }
    fwrite(STDERR, "Unknown argument: {$argv[$i]}\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

echo "=== 1/5 Navbar (Installer) ===\n";
(new Installer())->runPostInstall($container);
echo "OK\n\n";

echo "=== 2/5 Production roles + teams ===\n";
/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);
$access = $injectableFactory->create(ProductionAccessSetup::class);
$accessReport = $access->provision();
foreach ($accessReport['roles'] as $name => $status) {
    echo "  role $name: $status\n";
}
foreach ($accessReport['teams'] as $name => $status) {
    echo "  team $name: $status\n";
}
echo "\n";

if ($integrationPath !== null) {
    echo "=== 3/5 Google integration settings ===\n";
    if (!is_file($integrationPath)) {
        fwrite(STDERR, "File not found: $integrationPath\n");
        exit(1);
    }
    $incoming = json_decode((string) file_get_contents($integrationPath), true);
    /** @var EntityManager $em */
    $em = $container->getByClass(EntityManager::class);
    /** @var Config $config */
    $config = $container->getByClass(Config::class);
    /** @var Metadata $metadata */
    $metadata = $container->getByClass(Metadata::class);
    $metadata->init(true);
    $current = IntegrationSettings::collect($em, $config, $metadata);
    $changes = IntegrationSettings::diff($current, $incoming);
    if ($changes === []) {
        echo "  (no changes — already imported)\n\n";
    } else {
        $configWriter = $injectableFactory->create(ConfigWriter::class);
        IntegrationSettings::apply($em, $config, $configWriter, $metadata, $incoming);
        echo "  applied " . count($changes) . " integration(s)\n\n";
    }
} else {
    echo "=== 3/5 Google integration settings — skipped (pass --integration-settings)\n\n";
}

if ($calendarPath !== null) {
    echo "=== 4/5 Google calendar config ===\n";
    if (!is_file($calendarPath)) {
        fwrite(STDERR, "File not found: $calendarPath\n");
        exit(1);
    }
    $payload = json_decode((string) file_get_contents($calendarPath), true);
    /** @var EntityManager $em */
    $em = $container->getByClass(EntityManager::class);
    $report = GoogleCalendarConfig::apply($em, $payload);
    echo '  templates: ' . count($report['templates']) . ", dateSources: " . count($report['dateSources']) . "\n\n";
} else {
    echo "=== 4/5 Google calendar config — skipped (pass --google-calendar-config)\n\n";
}

echo "=== 5/5 Rebuild ===\n";
passthru('php ' . escapeshellarg(dirname(__DIR__) . '/command.php') . ' rebuild', $code);
exit($code === 0 ? 0 : 1);
