<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reporting;

use DateTimeZone;
use Espo\Core\Select\SearchParams;
use Espo\Core\Utils\Config;
use stdClass;

class PrimaNotaStatsProvider
{
    private const ENTITY_TYPE = 'PrimaNota';

    /** @var string[] */
    private const SUM_ATTRIBUTES = ['amountIn', 'amountOut'];

    private const BALANCE_KEY = 'managementBalance';

    public const CONFIG_OPENING_CASH = 'primaNotaOpeningCashBalance';

    public const CONFIG_OPENING_CASH_AS_OF = 'primaNotaOpeningCashAsOf';

    /**
     * Income / expense totals count only Inviato rows (plus legacy null status).
     * Cancelled / Refunded / Disputed / Problematic / Planned are excluded.
     *
     * @return array<string, mixed>
     */
    public static function incomeCountedWhere(): array
    {
        return [
            'OR' => [
                ['paymentStatus' => 'Inviato'],
                ['paymentStatus' => null],
            ],
        ];
    }

    /**
     * Planned forecast rows (Stripe awaiting payout / manual forecasts).
     *
     * @return array<string, mixed>
     */
    public static function plannedCountedWhere(): array
    {
        return [
            'paymentStatus' => 'Planned',
        ];
    }

    public function __construct(
        private ReportingAggregateQuery $aggregateQuery,
        private Config $config,
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
            'cashBalance' => $this->buildCashBalance($timezone, $allowedAttributes, $additionalWhere),
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
     * Opening cash + cumulative Inviato net (amountIn − amountOut) through today.
     *
     * Config:
     * - primaNotaOpeningCashBalance (float, default 0)
     * - primaNotaOpeningCashAsOf (Y-m-d optional) — if set, only rows with
     *   transactionDate > asOf are added (opening = bank balance on that date).
     *
     * @param string[] $allowedAttributes
     * @param array<string, mixed>|null $additionalWhere
     */
    public function buildCashBalance(
        ?DateTimeZone $timezone = null,
        ?array $allowedAttributes = null,
        ?array $additionalWhere = null,
    ): stdClass {
        $timezone ??= ReportingDateRange::defaultTimezone();
        [, $todayTo] = ReportingDateRange::currentCalendarDay($timezone);

        $opening = (float) ($this->config->get(self::CONFIG_OPENING_CASH) ?? 0);
        $asOf = trim((string) ($this->config->get(self::CONFIG_OPENING_CASH_AS_OF) ?? ''));

        $allowedAttributes ??= $this->aggregateQuery
            ->filterReadableAttributes(self::ENTITY_TYPE, self::SUM_ATTRIBUTES);

        $dateWhere = [
            'transactionDate<=' => $todayTo,
        ];

        if ($asOf !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf) === 1) {
            $dateWhere['transactionDate>'] = $asOf;
        }

        $where = $this->mergeIncomeWhere($dateWhere);

        if ($additionalWhere !== null && $additionalWhere !== []) {
            $where = [
                'AND' => [
                    $where,
                    $additionalWhere,
                ],
            ];
        }

        $in = 0.0;
        $out = 0.0;

        if ($allowedAttributes !== []) {
            $totals = $this->aggregateQuery->sum(
                self::ENTITY_TYPE,
                $allowedAttributes,
                null,
                $where
            );
            $in = (float) ($totals['amountIn'] ?? 0);
            $out = (float) ($totals['amountOut'] ?? 0);
        }

        $ledgerNet = $in - $out;

        return (object) [
            'asOf' => $todayTo,
            'timezone' => $timezone->getName(),
            'opening' => $opening,
            'openingAsOf' => $asOf !== '' ? $asOf : null,
            'ledgerAmountIn' => $in,
            'ledgerAmountOut' => $out,
            'ledgerNet' => $ledgerNet,
            'balance' => $opening + $ledgerNet,
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
            $metrics[] = 'plannedAmountIn';
            $metrics[] = 'plannedAmountOut';
            $metrics[] = 'plannedBalance';
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
        $plannedWhere = $this->mergeStatusWhere($periodWhere, self::plannedCountedWhere());

        $totals = $allowedAttributes !== []
            ? $this->aggregateQuery->sum(self::ENTITY_TYPE, $allowedAttributes, null, $where)
            : [];

        $plannedTotals = $allowedAttributes !== []
            ? $this->aggregateQuery->sum(self::ENTITY_TYPE, $allowedAttributes, null, $plannedWhere)
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

        foreach (self::SUM_ATTRIBUTES as $attribute) {
            $plannedKey = 'planned' . ucfirst($attribute);
            $result[$plannedKey] = (float) ($plannedTotals[$attribute] ?? 0);
        }

        $result['plannedBalance'] =
            ($result['plannedAmountIn'] ?? 0.0) - ($result['plannedAmountOut'] ?? 0.0);

        return (object) $result;
    }

    /**
     * @param array<string, mixed>|null $additionalWhere
     * @return array<string, mixed>
     */
    private function mergeIncomeWhere(?array $additionalWhere): array
    {
        return $this->mergeStatusWhere($additionalWhere, self::incomeCountedWhere());
    }

    /**
     * @param array<string, mixed>|null $additionalWhere
     * @param array<string, mixed> $statusWhere
     * @return array<string, mixed>
     */
    private function mergeStatusWhere(?array $additionalWhere, array $statusWhere): array
    {
        if ($additionalWhere === null || $additionalWhere === []) {
            return $statusWhere;
        }

        return [
            'AND' => [
                $additionalWhere,
                $statusWhere,
            ],
        ];
    }
}
