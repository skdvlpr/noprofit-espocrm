<?php

namespace Espo\Modules\NonprofitEspocrm\Core\Record\Defaults;

use Espo\Core\Record\Defaults\DefaultPopulator;
use Espo\Core\Record\Defaults\Populator;
use Espo\Modules\NonprofitEspocrm\Tools\Record\AssignedUserDefaultApplier;
use Espo\ORM\Entity;

/**
 * Extends Espo defaults so assignedUser is always prefilled with the current user when empty.
 *
 * @implements Populator<Entity>
 */
class AssignedUserPopulator implements Populator
{
    public function __construct(
        private DefaultPopulator $defaultPopulator,
        private AssignedUserDefaultApplier $assignedUserDefaultApplier,
    ) {}

    public function populate(Entity $entity): void
    {
        $this->defaultPopulator->populate($entity);
        $this->assignedUserDefaultApplier->applyIfEmpty($entity);
    }
}
