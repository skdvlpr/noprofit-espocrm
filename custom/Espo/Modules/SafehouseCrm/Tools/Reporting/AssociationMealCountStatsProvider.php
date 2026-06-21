<?php

namespace Espo\Modules\SafehouseCrm\Tools\Reporting;

use DateTimeZone;
use Espo\Core\Select\SearchParams;
use stdClass;

/**
 * AssociationMealCount reporting aggregates (Task 7.4.2).
 */
class AssociationMealCountStatsProvider
{
    private const ENTITY_TYPE = 'AssociationMealCount';

    /** @var string[] */
    private const SUM_ATTRIBUTES = ['portionCount'];

    public function __construct(
        private ReportingAggregateQuery $aggregateQuery,
    ) {}

    public function getSummary(?DateTimeZone $timezone = null): stdClass
    {
        $timezone ??= ReportingDateRange::defaultTimezone();

        [$monthFrom, $monthTo] = ReportingDateRange::currentCalendarMonth($timezone);
        [$yearFrom, $yearTo] = ReportingDateRange::currentCalendarYear($timezone);
        [$weekFrom, $weekTo] = ReportingDateRange::currentCalendarWeek($timezone);

        $allowedAttributes = $this->aggregateQuery
            ->filterReadableAttributes(self::ENTITY_TYPE, self::SUM_ATTRIBUTES);

        return (object) [
            'timezone' => $timezone->getName(),
            'metricList' => $allowedAttributes,
            'week' => $this->buildPeriodSummary($weekFrom, $weekTo, $timezone, $allowedAttributes),
            'month' => $this->buildPeriodSummary($monthFrom, $monthTo, $timezone, $allowedAttributes),
            'year' => $this->buildPeriodSummary($yearFrom, $yearTo, $timezone, $allowedAttributes),
        ];
    }

    /**
     * @return array<string, int>
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

        $result = [];

        foreach (self::SUM_ATTRIBUTES as $attribute) {
            if (array_key_exists($attribute, $totals)) {
                $result[$attribute] = (int) $totals[$attribute];
            }
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
            if (array_key_exists($attribute, $totals)) {
                $result[$attribute] = (int) $totals[$attribute];
            }
        }

        return (object) $result;
    }
}
