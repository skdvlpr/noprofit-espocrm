<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Common;

use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Modules\NonprofitEspocrm\Tools\Record\AssignedUserDefaultApplier;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSaveHook<Entity>
 */
class AssignedUserBeforeSave implements BeforeSaveHook
{
    public static int $order = 9;

    public function __construct(
        private AssignedUserDefaultApplier $assignedUserDefaultApplier,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if (!$entity->isNew()) {
            return;
        }

        $this->assignedUserDefaultApplier->applyIfEmpty($entity);
    }
}
