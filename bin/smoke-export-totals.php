<?php

declare(strict_types=1);


require __DIR__ . '/lib/refuse-production.php';


/**
 * Smoke: reporting export totals row (Task 7.3.5) + field-ACL-graceful summary.
 *
 * Runs a real MealCount export (CSV + XLSX) through the core Export tool with the
 * SafehouseCrm processor override, then asserts a totals row is appended.
 *
 * Usage: ddev exec php bin/smoke-export-totals.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Metadata;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\MealCountStatsProvider;
use Espo\Tools\Export\Export;
use Espo\Tools\Export\Factory as ExportFactory;
use Espo\Tools\Export\Params as ExportParams;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Entities\Attachment;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

$fail = [];
$pass = [];

/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);

$csvClass = $metadata->get(['app', 'export', 'formatDefs', 'csv', 'processorClassName']);
$xlsxClass = $metadata->get(['app', 'export', 'formatDefs', 'xlsx', 'processorClassName']);

$expectedCsv = 'Espo\\Modules\\NonprofitEspocrm\\Tools\\Export\\Csv\\TotalsProcessor';
$expectedXlsx = 'Espo\\Modules\\NonprofitEspocrm\\Tools\\Export\\Xlsx\\TotalsProcessor';

$dayOfWeekType = $metadata->get(['entityDefs', 'MealCount', 'fields', 'dayOfWeek', 'type']);
$dayOfWeekType === 'enum'
    ? $pass[] = 'MealCount dayOfWeek field type is enum'
    : $fail[] = "MealCount dayOfWeek type = $dayOfWeekType (expected enum)";

$itDayOptions = json_decode(
    file_get_contents('custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/MealCount.json'),
    true
)['options']['dayOfWeek']['Friday'] ?? null;

$itDayOptions === 'Venerdì'
    ? $pass[] = 'it_IT dayOfWeek Friday -> Venerdì'
    : $fail[] = 'it_IT dayOfWeek Friday translation missing or wrong';

$csvClass === $expectedCsv
    ? $pass[] = "csv processorClassName overridden"
    : $fail[] = "csv processorClassName = $csvClass (expected $expectedCsv)";

$xlsxClass === $expectedXlsx
    ? $pass[] = "xlsx processorClassName overridden"
    : $fail[] = "xlsx processorClassName = $xlsxClass (expected $expectedXlsx)";

// Provider summary must be an array of scalars (field-ACL graceful, no throw).
$provider = $injectableFactory->create(MealCountStatsProvider::class);
$summary = $provider->getSummary();
$metricList = $summary->metricList ?? [];

count($metricList) > 0
    ? $pass[] = 'summary metricList: ' . implode(',', $metricList)
    : $fail[] = 'summary metricList empty';

$runExport = function (string $format, ?array $fieldList = null) use ($container, $em, $injectableFactory): string {
    /** @var Export $export */
    $export = $injectableFactory->create(ExportFactory::class)->create();

    $params = ExportParams::create('MealCount')
        ->withFormat($format)
        ->withParam('includeTotals', true);

    if ($fieldList !== null) {
        $params = $params->withFieldList($fieldList);
    }

    $result = $export->setParams($params)->run();

    /** @var ?Attachment $attachment */
    $attachment = $em->getEntityById(Attachment::ENTITY_TYPE, $result->getAttachmentId());

    if (!$attachment) {
        throw new RuntimeException("No attachment for $format export.");
    }

    /** @var FileStorageManager $fsm */
    $fsm = $injectableFactory->create(FileStorageManager::class);
    $contents = $fsm->getContents($attachment);

    // Clean up the temp attachment.
    $em->removeEntity($attachment);

    return $contents;
};

// CSV: last non-empty line should carry the totals label and summed values.
try {
    $csv = $runExport('csv');
    $lines = array_values(array_filter(array_map('trim', explode("\n", $csv)), fn ($l) => $l !== ''));
    $lastLine = end($lines);

    $hasLabel = str_contains($csv, 'Totali') || str_contains($csv, 'Totals');

    $hasLabel
        ? $pass[] = "csv totals row present: $lastLine"
        : $fail[] = "csv totals row missing. last line: $lastLine";

    $firstLine = $lines[0] ?? '';
    $hasTranslatedHeader = str_contains($firstLine, 'Adults')
        && !preg_match('/(^|,)(adults|foodCost)(,|$)/', $firstLine);

    $hasTranslatedHeader
        ? $pass[] = "csv header uses translated labels: $firstLine"
        : $fail[] = "csv header still raw attribute names: $firstLine";
} catch (\Throwable $e) {
    $fail[] = 'csv export threw: ' . $e->getMessage();
}

// CSV (Bug 1 regression): export ONLY sum columns (no name, no non-sum column).
// The totals row must still carry an identifying caption.
try {
    $csv = $runExport('csv', ['adults', 'minors', 'totalMeals']);
    $lines = array_values(array_filter(array_map('trim', explode("\n", $csv)), fn ($l) => $l !== ''));
    $lastLine = end($lines);

    $hasLabel = str_contains($csv, 'Totali') || str_contains($csv, 'Totals');

    $hasLabel
        ? $pass[] = "csv sum-only totals caption present: $lastLine"
        : $fail[] = "csv sum-only totals caption missing. last line: $lastLine";
} catch (\Throwable $e) {
    $fail[] = 'csv sum-only export threw: ' . $e->getMessage();
}

// XLSX: load the produced workbook and assert the last row is the totals row.
try {
    $xlsx = $runExport('xlsx');

    $tmp = tempnam(sys_get_temp_dir(), 'espo-xlsx-smoke') . '.xlsx';
    file_put_contents($tmp, $xlsx);

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, false, false);
    @unlink($tmp);

    $lastRow = array_values(array_filter(
        $rows,
        fn ($r) => implode('', array_map('strval', $r)) !== ''
    ));
    $lastRow = end($lastRow);

    $joined = implode('|', array_map('strval', $lastRow ?: []));

    $hasLabel = str_contains($joined, 'Totali') || str_contains($joined, 'Totals');
    $hasSum = in_array('127', array_map('strval', $lastRow ?: []), true)
        || in_array('191', array_map('strval', $lastRow ?: []), true);

    $hasLabel && $hasSum
        ? $pass[] = "xlsx totals row present: $joined"
        : $fail[] = "xlsx totals row missing/incomplete: $joined";
} catch (\Throwable $e) {
    $fail[] = 'xlsx export threw: ' . $e->getMessage();
}

echo "\n=== smoke-export-totals ===\n";
foreach ($pass as $p) {
    echo "PASS  $p\n";
}
foreach ($fail as $f) {
    echo "FAIL  $f\n";
}

echo "\n" . ($fail === [] ? "ALL PASS\n" : count($fail) . " FAILURE(S)\n");

exit($fail === [] ? 0 : 1);
