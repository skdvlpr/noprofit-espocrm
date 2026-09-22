<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Modules\NonprofitEspocrm\Tools\ContactTypeSet;
use Espo\ORM\Query\SelectBuilder;

/** Occasional volunteers (Registro volontari occasionali). */
class VolunteersOccasionali implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where(array_merge(
            ContactTypeSet::containsWhere('Contact', ContactTypeSet::OPTION_VOLUNTEER),
            ['isOccasional' => true]
        ));
    }
}
