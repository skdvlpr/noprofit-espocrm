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
     * @return array{recordCount: int}
     */
    public function getTotals(?SearchParams $searchParams = null): array
    {
        return [
            'recordCount' => $this->aggregateQuery->countRecords(
                self::ENTITY_TYPE,
                $searchParams,
            ),
        ];
    }
}
