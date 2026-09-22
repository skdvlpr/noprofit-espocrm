<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Modules\NonprofitEspocrm\Tools\ContactTypeSet;
use Espo\ORM\Query\SelectBuilder;

/**
 * Regular (non-occasional) volunteers, including Volunteer+Member.
 * `isOccasional != true` would drop SQL NULL rows from older dumps.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/select-defs.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/api-search-params.md
 */
class Volunteers implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where(array_merge(
            ContactTypeSet::containsWhere('Contact', ContactTypeSet::OPTION_VOLUNTEER),
            [
                'OR' => [
                    ['isOccasional' => false],
                    ['isOccasional' => null],
                ],
            ]
        ));
    }
}
