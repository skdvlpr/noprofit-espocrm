<?php

namespace Espo\Modules\SafehouseCrm\Tools\Reporting;

use DateTimeZone;
use Espo\Core\Select\SearchParams;
use stdClass;

/**
 * MealCount month/year aggregates for reporting UI and REST (Task 7.3.1).
 */
class MealCountStatsProvider
{
    private const ENTITY_TYPE = 'MealCount';

    /** @var string[] */
    private const SUM_ATTRIBUTES = ['adults', 'minors', 'totalMeals', 'foodCost'];

    public function __construct(
        private ReportingAggregateQuery $aggregateQuery,
    ) {}

    public function getSummary(?DateTimeZone $timezone = null): stdClass
    {
        $timezone ??= ReportingDateRange::defaultTimezone();

        [$monthFrom, $monthTo] = ReportingDateRange::currentCalendarMonth($timezone);
        [$yearFrom, $yearTo] = ReportingDateRange::currentCalendarYear($timezone);

        return (object) [
            'timezone' => $timezone->getName(),
            'month' => $this->buildPeriodSummary($monthFrom, $monthTo, $timezone),
            'year' => $this->buildPeriodSummary($yearFrom, $yearTo, $timezone),
        ];
    }

    /**
     * Filter-aware totals for list footer / export scope (Tasks 7.3.3, 7.3.5).
     *
     * @return array<string, float>
     */
    public function getTotals(
        ?SearchParams $searchParams = null,
        ?array $additionalWhere = null,
    ): array {
        return $this->aggregateQuery->sum(
            self::ENTITY_TYPE,
            self::SUM_ATTRIBUTES,
            $searchParams,
            $additionalWhere,
        );
    }

    private function buildPeriodSummary(string $from, string $to, DateTimeZone $timezone): stdClass
    {
        $where = ReportingDateRange::dateBetweenWhere('date', $from, $to);
        $totals = $this->aggregateQuery->sum(self::ENTITY_TYPE, self::SUM_ATTRIBUTES, null, $where);

        return (object) [
            'from' => $from,
            'to' => $to,
            'timezone' => $timezone->getName(),
            'adults' => (int) $totals['adults'],
            'minors' => (int) $totals['minors'],
            'totalMeals' => (int) $totals['totalMeals'],
            'foodCost' => $totals['foodCost'],
        ];
    }
}
