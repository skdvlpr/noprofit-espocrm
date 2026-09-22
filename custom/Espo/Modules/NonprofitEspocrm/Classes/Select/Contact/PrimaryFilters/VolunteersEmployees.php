<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Modules\NonprofitEspocrm\Tools\ContactTypeSet;
use Espo\ORM\Query\SelectBuilder;

/**
 * Regular volunteers + employees (excludes occasional volunteers).
 * NULL isOccasional is treated as regular (same as Volunteers filter).
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/select-defs.md
 */
class VolunteersEmployees implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'OR' => [
                ContactTypeSet::containsWhere('Contact', ContactTypeSet::OPTION_EMPLOYEE),
                array_merge(
                    ContactTypeSet::containsWhere('Contact', ContactTypeSet::OPTION_VOLUNTEER),
                    [
                        'OR' => [
                            ['isOccasional' => false],
                            ['isOccasional' => null],
                        ],
                    ]
                ),
            ],
        ]);
    }
}
