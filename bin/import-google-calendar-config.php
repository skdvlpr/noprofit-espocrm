<?php

declare(strict_types=1);


require __DIR__ . '/lib/refuse-production.php';


/**
 * Import CalendarDateSource + CalendarTemplate from export-google-calendar-config.php.
 *
 * Usage:
 *   php bin/import-google-calendar-config.php [--dry-run] <input.json>
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Modules\NonprofitEspocrm\Tools\Migration\GoogleCalendarConfig;
use Espo\ORM\EntityManager;

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$args = array_values(array_filter($args, static fn ($a) => $a !== '--dry-run'));
$input = $args[0] ?? null;

if ($input === null || !is_file($input)) {
    fwrite(STDERR, "Usage: php bin/import-google-calendar-config.php [--dry-run] <input.json>\n");
    exit(1);
}

$payload = json_decode((string) file_get_contents($input), true);
if (!is_array($payload)) {
    fwrite(STDERR, "Invalid JSON file.\n");
    exit(1);
}

$templateCount = count($payload['calendarTemplates'] ?? []);
$sourceCount = count($payload['calendarDateSources'] ?? []);

echo ($dryRun ? "[DRY-RUN] " : '') . "Calendar config import from: $input\n";
echo "  CalendarTemplate rows: $templateCount\n";
echo "  CalendarDateSource rows: $sourceCount\n";

if ($dryRun) {
    echo "\nDry-run only. No changes written.\n";
    exit(0);
}

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

try {
    $report = GoogleCalendarConfig::apply($em, $payload);
} catch (\InvalidArgumentException $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "\nCalendarTemplate:\n";
foreach ($report['templates'] as $key => $status) {
    echo "  - $key: $status\n";
}

echo "\nCalendarDateSource:\n";
foreach ($report['dateSources'] as $key => $status) {
    echo "  - $key: $status\n";
}

echo "\nDone. Run: php command.php rebuild\n";
