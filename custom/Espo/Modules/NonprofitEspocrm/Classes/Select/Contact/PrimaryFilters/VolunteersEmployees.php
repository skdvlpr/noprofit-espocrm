<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

/** Volunteer + Employee contacts (reporting cohort; further filterable in UI). */
class VolunteersEmployees implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'contactType' => ['Volunteer', 'Employee'],
        ]);
    }
}
