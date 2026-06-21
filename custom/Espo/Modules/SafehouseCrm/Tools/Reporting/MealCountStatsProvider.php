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
        [$todayFrom, $todayTo] = ReportingDateRange::currentCalendarDay($timezone);

        // Per-role: a Volunteer cannot read foodCost — drop it instead of 403.
        $allowedAttributes = $this->aggregateQuery
            ->filterReadableAttributes(self::ENTITY_TYPE, self::SUM_ATTRIBUTES);

        return (object) [
            'timezone' => $timezone->getName(),
            'metricList' => $allowedAttributes,
            'today' => $this->buildPeriodSummary($todayFrom, $todayTo, $timezone, $allowedAttributes),
            'month' => $this->buildPeriodSummary($monthFrom, $monthTo, $timezone, $allowedAttributes),
            'year' => $this->buildPeriodSummary($yearFrom, $yearTo, $timezone, $allowedAttributes),
        ];
    }

    /**
     * Filter-aware totals for list footer / export scope (Tasks 7.3.3, 7.3.5).
     *
     * @return array<string, float|int>
     */
    public function getTotals(
        ?SearchParams $searchParams = null,
        ?array $additionalWhere = null,
    ): array {
        $allowedAttributes = $this->aggregateQuery
            ->filterReadableAttributes(self::ENTITY_TYPE, self::SUM_ATTRIBUTES);

        if ($allowedAttributes === []) {
            return [];
        }

        $totals = $this->aggregateQuery->sum(
            self::ENTITY_TYPE,
            $allowedAttributes,
            $searchParams,
            $additionalWhere,
        );

        return $this->normalizeTotals($totals);
    }

    /**
     * @param array<string, float> $totals
     * @return array<string, float|int>
     */
    private function normalizeTotals(array $totals): array
    {
        $result = [];

        foreach (self::SUM_ATTRIBUTES as $attribute) {
            if (!array_key_exists($attribute, $totals)) {
                continue;
            }

            $result[$attribute] = $attribute === 'foodCost'
                ? $totals[$attribute]
                : (int) $totals[$attribute];
        }

        return $result;
    }

    /**
     * @param string[] $allowedAttributes
     */
    private function buildPeriodSummary(
        string $from,
        string $to,
        DateTimeZone $timezone,
        array $allowedAttributes,
    ): stdClass {
        $where = ReportingDateRange::dateBetweenWhere('date', $from, $to);

        $totals = $allowedAttributes !== []
            ? $this->aggregateQuery->sum(self::ENTITY_TYPE, $allowedAttributes, null, $where)
            : [];

        $result = [
            'from' => $from,
            'to' => $to,
            'timezone' => $timezone->getName(),
        ];

        foreach (self::SUM_ATTRIBUTES as $attribute) {
            if (!array_key_exists($attribute, $totals)) {
                continue;
            }

            $result[$attribute] = $attribute === 'foodCost'
                ? $totals[$attribute]
                : (int) $totals[$attribute];
        }

        return (object) $result;
    }
}
