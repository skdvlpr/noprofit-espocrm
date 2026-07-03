<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reporting;

use Espo\Core\Select\SearchParams;

class InterventionStatsProvider
{
    private const ENTITY_TYPE = 'Intervention';

    public function __construct(
        private ReportingAggregateQuery $aggregateQuery,
    ) {}

    /**
     * @return array{interventionCount: int, recordCount: int}
     */
    public function getTotals(?SearchParams $searchParams = null): array
    {
        $allowedAttributes = $this->aggregateQuery
            ->filterReadableAttributes(self::ENTITY_TYPE, ['interventionCount']);

        $interventionCount = 0;

        if ($allowedAttributes !== []) {
            $sums = $this->aggregateQuery->sum(
                self::ENTITY_TYPE,
                ['interventionCount'],
                $searchParams,
            );

            $interventionCount = (int) ($sums['interventionCount'] ?? 0);
        }

        return [
            'interventionCount' => $interventionCount,
            'recordCount' => $this->aggregateQuery->countRecords(
                self::ENTITY_TYPE,
                $searchParams,
            ),
        ];
    }
}
