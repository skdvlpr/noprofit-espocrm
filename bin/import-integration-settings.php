<?php
/**
 * Import app-level integration settings produced by
 * bin/export-integration-settings.php into this Espo instance.
 *
 * - Writes Integration entity rows via EntityManager (no raw SQL) and the
 *   config.integrations flag via ConfigWriter.
 * - Idempotent: re-running with the same file makes no further changes.
 * - --dry-run prints the masked diff and writes nothing.
 *
 * Usage:
 *   ddev exec php bin/import-integration-settings.php [--dry-run] <input.json>
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;
use Espo\Modules\SafehouseCrm\Tools\Migration\IntegrationSettings;

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$args = array_values(array_filter($args, static fn ($a) => $a !== '--dry-run'));
$input = $args[0] ?? null;

if ($input === null || !is_file($input)) {
    fwrite(STDERR, "Usage: php bin/import-integration-settings.php [--dry-run] <input.json>\n");
    exit(1);
}

$incoming = json_decode((string) file_get_contents($input), true);
if (!is_array($incoming) || !isset($incoming['integrations'])) {
    fwrite(STDERR, "Invalid settings file: missing 'integrations'.\n");
    exit(1);
}
if (($incoming['version'] ?? null) !== IntegrationSettings::FORMAT_VERSION) {
    fwrite(STDERR, "Unsupported format version: " . var_export($incoming['version'] ?? null, true) . "\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

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
    echo "No changes — instance already matches the file (idempotent).\n";
    exit(0);
}

echo ($dryRun ? "[DRY-RUN] " : "") . "Pending changes:\n";
foreach ($changes as $name => $lines) {
    echo "  $name:\n";
    foreach ($lines as $line) {
        echo "    - $line\n";
    }
}

if ($dryRun) {
    echo "\nDry-run only. No changes written.\n";
    exit(0);
}

/** @var ConfigWriter $configWriter */
$configWriter = $container->getByClass(InjectableFactory::class)->create(ConfigWriter::class);

$applied = IntegrationSettings::apply($em, $config, $configWriter, $metadata, $incoming);

echo "\nApplied " . count($applied) . " integration change-set(s). Run a rebuild to refresh caches:\n";
echo "  ddev exec php command.php rebuild\n";
