<?php

namespace Espo\Modules\SafehouseCrm\Hooks\VolunteerEmployee;

use Espo\Core\Hook\Hook\BeforeRemove;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Releases assignedUserId on soft-delete so UNIQ_ASSIGNED_USER (assignedUserId, deleted)
 * does not block deleting a second record for the same user.
 */
class ClearAssignedUserOnSoftDelete implements BeforeSave, BeforeRemove
{
    public static int $order = 5;

    public function beforeRemove(Entity $entity, RemoveOptions $options): void
    {
        $this->releaseAssignedUser($entity);
    }

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isAttributeChanged('deleted') || !$entity->get('deleted')) {
            return;
        }

        $this->releaseAssignedUser($entity);
    }

    private function releaseAssignedUser(Entity $entity): void
    {
        $entity->set('assignedUserId', null);
        $entity->set('assignedUserName', null);
    }
}
