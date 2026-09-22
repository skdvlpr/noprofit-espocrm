<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\Modules\NonprofitEspocrm\Tools\ContactTypeSet;
use Espo\ORM\Query\SelectBuilder;

/** Associati (= MemberContact type), including Volunteer+Member / Employee+Member. */
class Associati implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where(
            ContactTypeSet::containsWhere('Contact', ContactTypeSet::OPTION_MEMBER)
        );
    }
}
