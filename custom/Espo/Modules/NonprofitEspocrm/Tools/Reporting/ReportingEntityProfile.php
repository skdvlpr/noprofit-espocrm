<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reporting;

/**
 * Per-entity reporting configuration for Rendicontazione aggregates and export totals.
 */
class ReportingEntityProfile
{
    /** @var string[] */
    public readonly array $exportTotalAttributes;

    /**
     * @param string[] $sumAttributes DB-level SUM targets (int/float/currency amount columns).
     * @param string[] $exportTotalAttributes Subset shown in export totals row (defaults to sumAttributes).
     */
    public function __construct(
        public readonly string $entityType,
        public readonly string $dateAttribute,
        public readonly array $sumAttributes,
        array $exportTotalAttributes = [],
        public readonly string $totalsLabelAttribute = 'name',
    ) {
        $this->exportTotalAttributes = $exportTotalAttributes !== []
            ? $exportTotalAttributes
            : $sumAttributes;
    }
}
