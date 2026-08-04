<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\Select\ActivityOffer\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

class Draft implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where(['status' => 'Draft']);
    }
}
