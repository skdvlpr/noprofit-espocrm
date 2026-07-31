<?php

namespace Espo\Modules\VolunteerActivityDispatch\Classes\Select\ActivityOffer\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

class Published implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where(['status' => 'Published']);
    }
}
