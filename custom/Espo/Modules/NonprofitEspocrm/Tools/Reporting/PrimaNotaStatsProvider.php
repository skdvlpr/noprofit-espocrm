<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reporting;

use DateTimeZone;
use Espo\Core\Select\SearchParams;
use stdClass;

class PrimaNotaStatsProvider
{
    private const ENTITY_TYPE = 'PrimaNota';

    /** @var string[] */
    private const SUM_ATTRIBUTES = ['amountIn', 'amountOut'];

    private const BALANCE_KEY = 'managementBalance';

    /**
     * Income / expense totals count only Paid rows (plus legacy null status).
     * Cancelled / Refunded / Disputed / Problematic / Planned are excluded.
     *
     * @return array<string, mixed>
     */
    public static function incomeCountedWhere(): array
    {
        return [
            'OR' => [
                ['paymentStatus' => 'Paid'],
                ['paymentStatus' => null],
            ],
        ];
    }

    public function __construct(
        private ReportingAggregateQuery $aggregateQuery,
    ) {}

    public function getSummary(
        ?DateTimeZone $timezone = null,
        ?array $additionalWhere = null,
    ): stdClass
    {
        $timezone ??= ReportingDateRange::defaultTimezone();

        [$monthFrom, $monthTo] = ReportingDateRange::currentCalendarMonth($timezone);
        [$yearFrom, $yearTo] = ReportingDateRange::currentCalendarYear($timezone);
        [$todayFrom, $todayTo] = ReportingDateRange::currentCalendarDay($timezone);

        $allowedAttributes = $this->aggregateQuery
            ->filterReadableAttributes(self::ENTITY_TYPE, self::SUM_ATTRIBUTES);

        $metricList = $this->buildMetricList($allowedAttributes);

        return (object) [
            'timezone' => $timezone->getName(),
            'metricList' => $metricList,
            'today' => $this->buildPeriodSummary(
                $todayFrom,
                $todayTo,
                $timezone,
                $allowedAttributes,
                $additionalWhere
            ),
            'month' => $this->buildPeriodSummary(
                $monthFrom,
                $monthTo,
                $timezone,
                $allowedAttributes,
                $additionalWhere
            ),
            'year' => $this->buildPeriodSummary(
                $yearFrom,
                $yearTo,
                $timezone,
                $allowedAttributes,
                $additionalWhere
            ),
        ];
    }

    /**
     * @return array<string, float>
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
            $this->mergeIncomeWhere($additionalWhere),
        );

        return $this->normalizeTotals($totals);
    }

    /**
     * @param string[] $allowedAttributes
     * @return string[]
     */
    private function buildMetricList(array $allowedAttributes): array
    {
        $metrics = [];

        foreach (self::SUM_ATTRIBUTES as $attribute) {
            if (in_array($attribute, $allowedAttributes, true)) {
                $metrics[] = $attribute;
            }
        }

        if ($metrics !== []) {
            $metrics[] = self::BALANCE_KEY;
        }

        return $metrics;
    }

    /**
     * @param array<string, float> $totals
     * @return array<string, float>
     */
    private function normalizeTotals(array $totals): array
    {
        $result = [];

        foreach (self::SUM_ATTRIBUTES as $attribute) {
            if (!array_key_exists($attribute, $totals)) {
                continue;
            }

            $result[$attribute] = (float) $totals[$attribute];
        }

        if ($result !== []) {
            $result[self::BALANCE_KEY] = ($result['amountIn'] ?? 0.0) - ($result['amountOut'] ?? 0.0);
        }

        return $result;
    }

    /**
     * @param string[] $allowedAttributes
     * @param array<string, mixed>|null $additionalWhere
     */
    private function buildPeriodSummary(
        string $from,
        string $to,
        DateTimeZone $timezone,
        array $allowedAttributes,
        ?array $additionalWhere = null,
    ): stdClass {
        $periodWhere = ReportingDateRange::dateBetweenWhere('transactionDate', $from, $to);

        if ($additionalWhere !== null && $additionalWhere !== []) {
            $periodWhere = [
                'AND' => [
                    $periodWhere,
                    $additionalWhere,
                ],
            ];
        }

        $where = $this->mergeIncomeWhere($periodWhere);

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

            $result[$attribute] = (float) $totals[$attribute];
        }

        if (array_key_exists('amountIn', $result) || array_key_exists('amountOut', $result)) {
            $result[self::BALANCE_KEY] = ($result['amountIn'] ?? 0.0) - ($result['amountOut'] ?? 0.0);
        }

        return (object) $result;
    }

    /**
     * @param array<string, mixed>|null $additionalWhere
     * @return array<string, mixed>
     */
    private function mergeIncomeWhere(?array $additionalWhere): array
    {
        $incomeWhere = self::incomeCountedWhere();

        if ($additionalWhere === null || $additionalWhere === []) {
            return $incomeWhere;
        }

        return [
            'AND' => [
                $additionalWhere,
                $incomeWhere,
            ],
        ];
    }
}
