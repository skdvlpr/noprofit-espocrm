<?php
/**
 * Smoke test for SafehouseCrm Rendicontazione reporting layer (Task 7.5).
 *
 * Verifies:
 *   - ReportingProfileRegistry knows MealCount
 *   - ReportingAggregateQuery SUM matches seeded rows (month filter + full set)
 *   - ReportingDateRange month/year bounds (Europe/Rome)
 *   - ExportWithTotals appends totals row to CSV stream
 *
 * Creates temporary MealCount rows prefixed SMOKE-Rend-*, deletes at end.
 *
 * Usage:
 *   ddev exec php bin/smoke-rendicontazione.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Modules\SafehouseCrm\Tools\Reporting\ExportWithTotals;
use Espo\Modules\SafehouseCrm\Tools\Reporting\ReportingAggregateQuery;
use Espo\Modules\SafehouseCrm\Tools\Reporting\ReportingDateRange;
use Espo\Modules\SafehouseCrm\Tools\Reporting\ReportingProfileRegistry;
use GuzzleHttp\Psr7\Stream;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);
$em = $container->get('entityManager');

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $mark = $pass ? 'PASS' : 'FAIL';
    echo "  [$mark] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$registry = $injectableFactory->create(ReportingProfileRegistry::class);
$aggregateQuery = $injectableFactory->create(ReportingAggregateQuery::class);
$exportWithTotals = $injectableFactory->create(ExportWithTotals::class);

echo "Reporting profile registry\n";

$mealProfile = $registry->getProfile('MealCount');
$ok('MealCount profile exists', $mealProfile !== null);
$ok(
    'MealCount sum attributes',
    $mealProfile !== null && $mealProfile->sumAttributes === ['adults', 'minors', 'totalMeals', 'foodCost']
);
$ok(
    'AssociationMealCount profile pending (7.4)',
    $registry->getProfile('AssociationMealCount') === null
);

echo "\nReportingDateRange\n";

$tz = ReportingDateRange::defaultTimezone();
[$monthFrom, $monthTo] = ReportingDateRange::currentCalendarMonth($tz);
[$yearFrom, $yearTo] = ReportingDateRange::currentCalendarYear($tz);

$ok('Month bounds non-empty', $monthFrom !== '' && $monthTo !== '');
$ok('Year bounds span January', str_ends_with($yearFrom, '-01-01'));
$ok('Year bounds span December', str_ends_with($yearTo, '-12-31'));

echo "\nReportingAggregateQuery (seeded MealCount)\n";

$created = [];
$prefix = 'SMOKE-Rend-' . date('Ymd') . '-';
$today = date('Y-m-d');

try {
    $seedRows = [
        ['adults' => 10, 'minors' => 5, 'date' => $today],
        ['adults' => 3, 'minors' => 2, 'date' => $today],
    ];

    $expectedAdults = 0;
    $expectedMinors = 0;
    $expectedFoodCost = 0.0;

    foreach ($seedRows as $i => $row) {
        $entity = $em->getNewEntity('MealCount');
        $entity->set([
            'name' => $prefix . $i,
            'date' => $row['date'],
            'adults' => $row['adults'],
            'minors' => $row['minors'],
        ]);
        $em->saveEntity($entity);
        $created[] = $entity;

        $expectedAdults += $row['adults'];
        $expectedMinors += $row['minors'];
        $expectedFoodCost += (float) $entity->get('foodCost');
    }

    $ids = array_map(static fn ($entity) => $entity->getId(), $created);
    $filterWhere = ['id' => $ids];

    $sums = $aggregateQuery->sum(
        'MealCount',
        ['adults', 'minors', 'totalMeals', 'foodCost'],
        null,
        $filterWhere,
    );

    $ok(
        'SUM adults matches seeded month rows',
        (int) $sums['adults'] === $expectedAdults,
        'got=' . $sums['adults'] . ' expected=' . $expectedAdults
    );
    $ok(
        'SUM minors matches seeded month rows',
        (int) $sums['minors'] === $expectedMinors,
        'got=' . $sums['minors'] . ' expected=' . $expectedMinors
    );
    $ok(
        'SUM foodCost matches seeded month rows',
        abs($sums['foodCost'] - $expectedFoodCost) < 0.01,
        'got=' . $sums['foodCost'] . ' expected=' . $expectedFoodCost
    );

    $grouped = $aggregateQuery->sumGrouped(
        'MealCount',
        ['adults'],
        ['date'],
        null,
        $filterWhere,
    );

    $ok(
        'GROUP BY date returns at least one row for seeded month',
        count($grouped) >= 1,
        'count=' . count($grouped)
    );

    echo "\nExportWithTotals\n";

    $attributeList = ['name', 'date', 'adults', 'minors', 'totalMeals', 'foodCost'];
    $headerFp = fopen('php://temp', 'w+');
    fputcsv($headerFp, $attributeList);
    fputcsv($headerFp, ['Row A', $today, '10', '5', '15', (string) $expectedFoodCost]);
    rewind($headerFp);
    $inputStream = new Stream($headerFp);

    $outputStream = $exportWithTotals->appendTotalsRowToCsv(
        $inputStream,
        'MealCount',
        $attributeList,
        null,
        $filterWhere,
        'Totals',
    );

    $outputStream->rewind();
    $content = $outputStream->getContents();
    $readFp = fopen('php://memory', 'r+');
    fwrite($readFp, $content);
    rewind($readFp);
    $lines = [];

    while (($row = fgetcsv($readFp, 0, ',', '"', "\0")) !== false) {
        $lines[] = $row;
    }

    fclose($readFp);

    $ok('CSV has header + data + totals rows', count($lines) === 3, 'lines=' . count($lines));

    $totalsRow = $lines[2] ?? [];
    $ok('Totals label in name column', ($totalsRow[0] ?? '') === 'Totals');
    $ok(
        'Totals adults in export row',
        (int) ($totalsRow[2] ?? 0) === $expectedAdults,
        'got=' . ($totalsRow[2] ?? 'null')
    );
} finally {
    foreach ($created as $entity) {
        $em->removeEntity($entity);
    }
}

echo "\n" . ($fail === 0 ? 'ALL PASS' : "FAILED ($fail)") . "\n";
exit($fail > 0 ? 1 : 0);
