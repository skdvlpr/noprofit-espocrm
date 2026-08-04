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

// CSV: first column empty header; last row starts with Totale/Total + count + sums.
try {
    $csv = $runExport('csv');
    $lines = array_values(array_filter(array_map('trim', explode("\n", $csv)), fn ($l) => $l !== ''));
    $lastLine = end($lines);
    $recordCount = max(0, count($lines) - 2); // header + totals

    $hasCaption = (bool) preg_match('/(^|,)"?(Totale|Total)"?(,|$)/', $lastLine);
    $hasCount = $recordCount === 0
        || preg_match('/(^|,)"?' . preg_quote((string) $recordCount, '/') . '"?(,|$)/', $lastLine) === 1;

    $hasCaption && $hasCount
        ? $pass[] = "csv totals row has Total caption + count $recordCount: $lastLine"
        : $fail[] = "csv totals missing caption/count. last line: $lastLine";

    $firstLine = $lines[0] ?? '';
    $hasTranslatedHeader = str_contains($firstLine, 'Adults')
        && !preg_match('/(^|,)(adults|foodCost)(,|$)/', $firstLine);

    $hasTranslatedHeader
        ? $pass[] = "csv header uses translated labels: $firstLine"
        : $fail[] = "csv header still raw attribute names: $firstLine";

    // Marker column is first: header cell empty (leading comma or empty first field).
    $startsWithEmptyMarker = str_starts_with($firstLine, ',') || str_starts_with($firstLine, '"",');
    $startsWithEmptyMarker
        ? $pass[] = 'csv first column is empty Total marker header'
        : $fail[] = "csv missing empty Total marker column: $firstLine";
} catch (\Throwable $e) {
    $fail[] = 'csv export threw: ' . $e->getMessage();
}

// CSV (Bug 1 regression): export ONLY sum columns (no name, no non-sum column).
try {
    $csv = $runExport('csv', ['adults', 'minors', 'totalMeals']);
    $lines = array_values(array_filter(array_map('trim', explode("\n", $csv)), fn ($l) => $l !== ''));
    $lastLine = end($lines);
    $recordCount = max(0, count($lines) - 2);

    $hasCaption = (bool) preg_match('/(^|,)"?(Totale|Total)"?(,|$)/', $lastLine);
    $hasCount = $recordCount === 0
        || preg_match('/(^|,)"?' . preg_quote((string) $recordCount, '/') . '"?(,|$)/', $lastLine) === 1;

    $hasCaption && $hasCount
        ? $pass[] = "csv sum-only totals caption+count ($recordCount): $lastLine"
        : $fail[] = "csv sum-only totals missing. last line: $lastLine";
} catch (\Throwable $e) {
    $fail[] = 'csv sum-only export threw: ' . $e->getMessage();
}

// XLSX: last NON-EMPTY row = totals (caption + count + sums), pastel + bold on THAT row.
try {
    $xlsx = $runExport('xlsx');

    $tmp = tempnam(sys_get_temp_dir(), 'espo-xlsx-smoke') . '.xlsx';
    file_put_contents($tmp, $xlsx);

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, false, false);
    @unlink($tmp);

    $findLastNonEmpty = static function (array $rows): array {
        for ($i = count($rows) - 1; $i >= 0; $i--) {
            $joined = implode('', array_map(static fn ($v) => (string) $v, $rows[$i] ?? []));
            if ($joined !== '') {
                return [$i + 1, $rows[$i]]; // 1-based sheet row
            }
        }

        return [0, []];
    };

    [$totalsSheetRow, $lastRow] = $findLastNonEmpty($rows);
    $joined = implode('|', array_map('strval', $lastRow ?: []));
    $nonEmptyCount = count(array_filter(
        $rows,
        fn ($r) => implode('', array_map('strval', $r)) !== ''
    ));
    $recordCount = max(0, $nonEmptyCount - 2);

    $hasCaption = in_array('Totale', array_map('strval', $lastRow ?: []), true)
        || in_array('Total', array_map('strval', $lastRow ?: []), true);
    $hasCount = $recordCount === 0 || in_array((string) $recordCount, array_map('strval', $lastRow ?: []), true);
    $firstCell = (string) ($lastRow[0] ?? '');

    $fillRgb = strtoupper((string) $sheet->getStyle('A' . $totalsSheetRow)
        ->getFill()
        ->getStartColor()
        ->getRGB());
    $isBold = (bool) $sheet->getStyle('A' . $totalsSheetRow)->getFont()->getBold();
    $colBBold = (bool) $sheet->getStyle('B' . $totalsSheetRow)->getFont()->getBold();
    $expectedFill = 'E8F0E9';

    // Phantom row after totals must NOT be the styled one.
    $phantomRow = $totalsSheetRow + 1;
    $phantomFill = strtoupper((string) $sheet->getStyle('A' . $phantomRow)
        ->getFill()
        ->getStartColor()
        ->getRGB());

    $hasCaption && $hasCount && ($firstCell === 'Totale' || $firstCell === 'Total')
        ? $pass[] = "xlsx totals row present on sheet row $totalsSheetRow: $joined"
        : $fail[] = "xlsx totals missing/incomplete on row $totalsSheetRow: $joined";

    $fillRgb === $expectedFill
        ? $pass[] = "xlsx totals row pastel fill $fillRgb"
        : $fail[] = "xlsx totals row fill = $fillRgb (expected $expectedFill)";

    $isBold && $colBBold
        ? $pass[] = 'xlsx totals row is bold (marker + count cells)'
        : $fail[] = "xlsx totals bold marker=$isBold countCol=$colBBold";

    $phantomFill !== $expectedFill
        ? $pass[] = 'xlsx phantom row after totals is not pastel-styled'
        : $fail[] = "xlsx wrongly styled phantom row $phantomRow with $phantomFill";
} catch (\Throwable $e) {
    $fail[] = 'xlsx export threw: ' . $e->getMessage();
}

// Generic entity (Contact): includeTotals=true must append Total caption + count.
try {
    /** @var Export $export */
    $export = $injectableFactory->create(ExportFactory::class)->create();

    $params = ExportParams::create('Contact')
        ->withFormat('csv')
        ->withParam('includeTotals', true)
        ->withFieldList(['name', 'weeklyHours', 'monthlyHours']);

    $result = $export->setParams($params)->run();
    /** @var ?Attachment $attachment */
    $attachment = $em->getEntityById(Attachment::ENTITY_TYPE, $result->getAttachmentId());

    if (!$attachment) {
        throw new RuntimeException('No attachment for Contact export.');
    }

    /** @var FileStorageManager $fsm */
    $fsm = $injectableFactory->create(FileStorageManager::class);
    $csv = $fsm->getContents($attachment);
    $em->removeEntity($attachment);

    $lines = array_values(array_filter(array_map('trim', explode("\n", $csv)), fn ($l) => $l !== ''));
    $lastLine = end($lines) ?: '';
    $recordCount = max(0, count($lines) - 2);

    $hasCaption = (bool) preg_match('/(^|,)"?(Totale|Total)"?(,|$)/', $lastLine);
    $hasCount = $recordCount === 0
        || preg_match('/(^|,)"?' . preg_quote((string) $recordCount, '/') . '"?(,|$)/', $lastLine) === 1;

    $hasCaption && $hasCount
        ? $pass[] = "Contact csv generic totals Total+$recordCount present"
        : $fail[] = 'Contact csv generic totals missing: ' . substr($csv, -200);
} catch (\Throwable $e) {
    $fail[] = 'Contact csv export threw: ' . $e->getMessage();
}

// Account XLSX: Total column + style on correct row (user regression).
try {
    /** @var Export $export */
    $export = $injectableFactory->create(ExportFactory::class)->create();

    $params = ExportParams::create('Account')
        ->withFormat('xlsx')
        ->withParam('includeTotals', true)
        ->withFieldList(['name', 'type', 'billingAddressCity']);

    $result = $export->setParams($params)->run();
    /** @var ?Attachment $attachment */
    $attachment = $em->getEntityById(Attachment::ENTITY_TYPE, $result->getAttachmentId());

    if (!$attachment) {
        throw new RuntimeException('No attachment for Account export.');
    }

    /** @var FileStorageManager $fsm */
    $fsm = $injectableFactory->create(FileStorageManager::class);
    $xlsx = $fsm->getContents($attachment);
    $em->removeEntity($attachment);

    $tmp = tempnam(sys_get_temp_dir(), 'espo-account-xlsx') . '.xlsx';
    file_put_contents($tmp, $xlsx);
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
    $sheet = $spreadsheet->getActiveSheet();
    @unlink($tmp);

    $rows = $sheet->toArray(null, true, false, false);
    $totalsRowIdx = 0;
    $totalsRow = [];

    for ($i = count($rows) - 1; $i >= 0; $i--) {
        if (implode('', array_map('strval', $rows[$i])) !== '') {
            $totalsRowIdx = $i + 1;
            $totalsRow = $rows[$i];
            break;
        }
    }

    $first = (string) ($totalsRow[0] ?? '');
    $fill = strtoupper((string) $sheet->getStyle('A' . $totalsRowIdx)->getFill()->getStartColor()->getRGB());
    $bold = (bool) $sheet->getStyle('A' . $totalsRowIdx)->getFont()->getBold();

    ($first === 'Totale' || $first === 'Total') && $fill === 'E8F0E9' && $bold
        ? $pass[] = "Account xlsx totals OK row=$totalsRowIdx caption=$first"
        : $fail[] = "Account xlsx totals bad row=$totalsRowIdx first=$first fill=$fill bold=" . ($bold ? '1' : '0');
} catch (\Throwable $e) {
    $fail[] = 'Account xlsx export threw: ' . $e->getMessage();
}

// Bool cells must use CRM language labels (not Excel BOOL / OS locale).
try {
    $boolClass = $metadata->get([
        'app', 'export', 'formatDefs', 'xlsx', 'cellValuePreparatorClassNameMap', 'bool',
    ]);
    $expectedBool = 'Espo\\Modules\\NonprofitEspocrm\\Tools\\Export\\Xlsx\\CellValuePreparators\\Boolean';

    $boolClass === $expectedBool
        ? $pass[] = 'xlsx bool preparator overridden for CRM language'
        : $fail[] = "xlsx bool preparator = $boolClass";

    $formatList = $metadata->get(['app', 'export', 'formatList']) ?? [];
    $emailFormatsInDownload = array_intersect(['xlsx-email', 'csv-email'], $formatList);

    $emailFormatsInDownload === []
        ? $pass[] = 'app.export.formatList has no email formats (download-safe)'
        : $fail[] = 'app.export.formatList still includes email formats: ' . implode(',', $emailFormatsInDownload);

    $globalEmailBtn = $metadata->get(['clientDefs', 'Global', 'menu', 'list', 'buttons']) ?? [];
    $hasGlobalEmail = false;

    foreach ($globalEmailBtn as $btn) {
        if (($btn['name'] ?? null) === 'reportingEmailExport') {
            $hasGlobalEmail = true;
            break;
        }
    }

    $hasGlobalEmail
        ? $pass[] = 'Global list menu has reportingEmailExport for all entities'
        : $fail[] = 'Global list menu missing reportingEmailExport';
} catch (\Throwable $e) {
    $fail[] = 'metadata export checks threw: ' . $e->getMessage();
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
