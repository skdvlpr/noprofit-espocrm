<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

/**
 * Regular (non-occasional) volunteers.
 * `isOccasional != true` would drop SQL NULL rows from older dumps.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/select-defs.md
 */
class Volunteers implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'contactType' => 'Volunteer',
            'OR' => [
                ['isOccasional' => false],
                ['isOccasional' => null],
            ],
        ]);
    }
}
