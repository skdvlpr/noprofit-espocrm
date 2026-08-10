<?php

namespace Espo\Modules\GoogleIntegration\Classes\Select\User\BoolFilters;

use Espo\Core\Select\Bool\Filter;
use Espo\Modules\GoogleIntegration\Tools\Calendar\ManagerCalendarShare;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Where\OrGroupBuilder;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\SelectBuilder;

/**
 * Keep only users with a connected Google Calendar External Account.
 *
 * @noinspection PhpUnused
 */
class GoogleCalendarConnected implements Filter
{
    public function __construct(
        private ManagerCalendarShare $managerCalendarShare,
    ) {}

    public function apply(SelectBuilder $queryBuilder, OrGroupBuilder $orGroupBuilder): void
    {
        $ids = $this->managerCalendarShare->listConnectedUserIds();

        if ($ids === []) {
            $orGroupBuilder->add(
                WhereClause::fromRaw([Attribute::ID => null])
            );

            return;
        }

        $orGroupBuilder->add(
            WhereClause::fromRaw([Attribute::ID => $ids])
        );
    }
}
