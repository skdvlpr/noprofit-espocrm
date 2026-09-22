<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Modules\NonprofitEspocrm\Tools\ContactTypeSet;
use Espo\ORM\Query\SelectBuilder;

/**
 * Employees (Dipendenti), including Employee+Member.
 */
class Employees implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where(
            ContactTypeSet::containsWhere('Contact', ContactTypeSet::OPTION_EMPLOYEE)
        );
    }
}
