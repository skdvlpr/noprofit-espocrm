<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\Select\ActivityInvite\AccessControlFilters;

use Espo\Core\Select\AccessControl\Filter;
use Espo\Entities\User;
use Espo\ORM\Query\SelectBuilder;

class OnlyOwn implements Filter
{
    public function __construct(private User $user)
    {}

    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where([
            'userId' => $this->user->getId(),
        ]);
    }
}
