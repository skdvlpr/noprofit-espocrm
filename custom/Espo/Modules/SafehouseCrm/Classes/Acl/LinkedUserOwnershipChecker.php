<?php

namespace Espo\Modules\SafehouseCrm\Classes\Acl;

use Espo\Core\Acl\DefaultOwnershipChecker;
use Espo\Core\Acl\OwnershipOwnChecker;
use Espo\Core\Acl\OwnershipSharedChecker;
use Espo\Core\Acl\OwnershipTeamChecker;
use Espo\Entities\User;
use Espo\ORM\Entity;

/**
 * Personnel records are owned by their linked user account, not by whichever
 * staff member happens to be assigned for administration.
 */
class LinkedUserOwnershipChecker implements OwnershipOwnChecker, OwnershipTeamChecker, OwnershipSharedChecker
{
    public function __construct(private DefaultOwnershipChecker $defaultOwnershipChecker) {}

    public function checkOwn(User $user, Entity $entity): bool
    {
        $linkedUserId = $entity->get('userId');

        if (is_string($linkedUserId) && $linkedUserId !== '') {
            return $user->getId() === $linkedUserId;
        }

        return $this->defaultOwnershipChecker->checkOwn($user, $entity);
    }

    public function checkTeam(User $user, Entity $entity): bool
    {
        return $this->defaultOwnershipChecker->checkTeam($user, $entity);
    }

    public function checkShared(User $user, Entity $entity, string $action): bool
    {
        return $this->defaultOwnershipChecker->checkShared($user, $entity, $action);
    }
}
