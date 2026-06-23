<?php
/**
 * Smoke: integration-settings migration helper (export/import) — read-only.
 *
 * Validates the pure diff/mask logic and the round-trip idempotency contract
 * WITHOUT mutating live config: a fresh collect() of the instance, diffed
 * against itself, must yield zero changes (idempotent). Secret masking and a
 * synthetic change are also asserted.
 *
 * Usage:
 *   ddev exec php bin/smoke-integration-settings-migration.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;
use Espo\Modules\NonprofitEspocrm\Tools\Migration\IntegrationSettings;

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    echo '  [' . ($pass ? 'PASS' : 'FAIL') . "] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

// Scripts present.
$ok('export script exists', is_file(__DIR__ . '/export-integration-settings.php'));
$ok('import script exists', is_file(__DIR__ . '/import-integration-settings.php'));

// Secret masking.
$ok('masks clientSecret', IntegrationSettings::maskValue('clientSecret', 'super-secret-value') === '***');
$ok('masks refreshToken', IntegrationSettings::maskValue('refreshToken', 'abc') === '***');
$ok('does not mask clientId', IntegrationSettings::maskValue('clientId', 'abc.apps') === 'abc.apps');
$ok('empty value rendered as ∅', IntegrationSettings::maskValue('clientId', null) === '∅');
$ok('isSecretField(clientSecret)', IntegrationSettings::isSecretField('clientSecret'));
$ok('!isSecretField(clientId)', !IntegrationSettings::isSecretField('clientId'));

// Pure diff: identical structures => no changes (idempotency contract).
$sample = [
    'version' => IntegrationSettings::FORMAT_VERSION,
    'integrations' => [
        'GoogleCalendarDrive' => [
            'enabled' => true,
            'configFlag' => true,
            'fields' => ['clientId' => 'x', 'clientSecret' => 'y', 'googleCalendarAutoCreateEnabled' => false],
        ],
    ],
];
$ok('diff(identical) is empty (idempotent)', IntegrationSettings::diff($sample, $sample) === []);

// Pure diff: a changed field is detected and the secret is masked.
$changed = $sample;
$changed['integrations']['GoogleCalendarDrive']['fields']['clientSecret'] = 'z';
$changed['integrations']['GoogleCalendarDrive']['enabled'] = false;
$d = IntegrationSettings::diff($sample, $changed);
$ok('diff detects changed integration', isset($d['GoogleCalendarDrive']));
$lines = implode("\n", $d['GoogleCalendarDrive'] ?? []);
$ok('diff masks secret values', str_contains($lines, '***') && !str_contains($lines, 'z'));
$ok('diff reports enabled flip', str_contains($lines, 'enabled: true -> false'));

// Live round-trip idempotency: collect() the real instance, diff against
// itself => zero changes. Read-only; nothing is written.
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

$live = IntegrationSettings::collect($em, $config, $metadata);
$ok('collect() returns versioned structure', ($live['version'] ?? null) === IntegrationSettings::FORMAT_VERSION);
$ok('collect() round-trips with zero diff (idempotent)', IntegrationSettings::diff($live, $live) === []);

if ($fail > 0) {
    fwrite(STDERR, "\n$fail check(s) failed.\n");
    exit(1);
}

echo "\nAll integration-settings migration checks passed.\n";
