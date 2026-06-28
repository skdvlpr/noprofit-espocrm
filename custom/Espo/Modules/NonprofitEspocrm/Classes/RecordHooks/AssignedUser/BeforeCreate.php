<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\RecordHooks\AssignedUser;

use Espo\Core\Record\Hook\SaveHook;
use Espo\Modules\NonprofitEspocrm\Tools\Record\AssignedUserDefaultApplier;
use Espo\ORM\Entity;

/**
 * @implements SaveHook<Entity>
 */
class BeforeCreate implements SaveHook
{
    public function __construct(
        private AssignedUserDefaultApplier $assignedUserDefaultApplier,
    ) {}

    public function process(Entity $entity): void
    {
        $this->assignedUserDefaultApplier->applyIfEmpty($entity);
    }
}
