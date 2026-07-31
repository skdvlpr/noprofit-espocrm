<?php

namespace Espo\Modules\VolunteerActivityDispatch\Classes\Acl\ActivityInvite;

use Espo\Core\Acl\OwnershipOwnChecker;
use Espo\Entities\User;
use Espo\ORM\Entity;

/**
 * @implements OwnershipOwnChecker<\Espo\ORM\Entity>
 */
class OwnershipChecker implements OwnershipOwnChecker
{
    public function checkOwn(User $user, Entity $entity): bool
    {
        return $entity->get('userId') === $user->getId();
    }
}
