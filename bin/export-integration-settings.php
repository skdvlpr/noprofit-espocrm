<?php
/**
 * Export app-level integration settings (Integration entity rows + their
 * config.integrations flag) to a portable JSON file for migration to another
 * Espo instance (e.g. local -> production).
 *
 * SECURITY: the output file contains client secrets. It is gitignored
 * (integration-settings*.json). Do not commit it; transfer it securely.
 *
 * Per-user OAuth tokens (ExternalAccount) are intentionally NOT exported —
 * each user re-authorizes Google on the target instance.
 *
 * Usage:
 *   ddev exec php bin/export-integration-settings.php [output.json]
 *   (default output: ./integration-settings.json)
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;
use Espo\Modules\SafehouseCrm\Tools\Migration\IntegrationSettings;

$out = $argv[1] ?? (dirname(__DIR__) . '/integration-settings.json');

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

$data = IntegrationSettings::collect($em, $config, $metadata);

if ($data['integrations'] === []) {
    fwrite(STDERR, "No stored integrations found. Nothing exported.\n");
    exit(1);
}

file_put_contents($out, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo "Exported " . count($data['integrations']) . " integration(s) to: $out\n";
foreach ($data['integrations'] as $name => $row) {
    echo "  - $name (enabled=" . ($row['enabled'] ? 'true' : 'false') . ")\n";
    foreach ($row['fields'] as $field => $value) {
        echo "      $field: " . IntegrationSettings::maskValue($field, $value) . "\n";
    }
}
echo "\nWARNING: this file contains client secrets — do NOT commit it. Transfer securely.\n";
