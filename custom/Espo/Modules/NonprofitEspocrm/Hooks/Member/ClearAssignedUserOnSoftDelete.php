<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Member;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Releases assignedUserId on soft-delete so UNIQ_ASSIGNED_USER (assignedUserId, deleted)
 * does not block deleting a second record for the same user.
 */
class ClearAssignedUserOnSoftDelete implements BeforeSave
{
    public static int $order = 5;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isAttributeChanged('deleted') || !$entity->get('deleted')) {
            return;
        }

        $entity->set('assignedUserId', null);
        $entity->set('assignedUserName', null);
    }
}
