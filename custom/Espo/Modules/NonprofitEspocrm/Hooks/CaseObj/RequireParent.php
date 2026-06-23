<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\CaseObj;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Server-side guard: intake records must reference a CRM entity via linkParent.
 */
class RequireParent implements BeforeSave
{
    public static int $order = 5;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        $parentId = $entity->get('parentId');
        $parentType = $entity->get('parentType');

        if (
            $parentId === null
            || $parentId === ''
            || $parentType === null
            || $parentType === ''
        ) {
            throw new BadRequest('Case requires a related CRM record.');
        }
    }
}
