<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

/**
 * Regular volunteers + employees (excludes occasional volunteers).
 */
class VolunteersEmployees implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'OR' => [
                ['contactType' => 'Employee'],
                [
                    'contactType' => 'Volunteer',
                    'isOccasional!=' => true,
                ],
            ],
        ]);
    }
}
