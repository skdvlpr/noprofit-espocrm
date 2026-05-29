<?php

namespace Espo\Modules\SafehouseCrm\Classes\Record;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Record\Deleted\DefaultRestorer;
use Espo\Core\Record\Deleted\Restorer;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Restores the user link preserved before soft-delete.
 *
 * @implements Restorer<Entity>
 */
class RestoreDeletedAssignedUser implements Restorer
{
    public function __construct(
        private DefaultRestorer $defaultRestorer,
        private EntityManager $entityManager
    ) {}

    public function restore(Entity $entity): void
    {
        $storedUserId = $this->storedAssignedUserId($entity);
        $storedUserName = $this->storedAssignedUserName($entity);

        $this->assertAssignedUserAvailable($entity, $storedUserId);

        $this->entityManager
            ->getTransactionManager()
            ->run(function () use ($entity, $storedUserId, $storedUserName): void {
                $this->defaultRestorer->restore($entity);

                if ($storedUserId === null) {
                    return;
                }

                $this->entityManager->refreshEntity($entity);

                $entity->set('assignedUserId', $storedUserId);
                $entity->set('assignedUserName', $storedUserName);
                $entity->set('deletedAssignedUserId', null);
                $entity->set('deletedAssignedUserName', null);

                $this->entityManager->saveEntity($entity, [
                    SaveOption::SKIP_HOOKS => true,
                    SaveOption::SILENT => true,
                ]);
            });
    }

    private function storedAssignedUserId(Entity $entity): ?string
    {
        $value = $entity->get('deletedAssignedUserId');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function storedAssignedUserName(Entity $entity): ?string
    {
        $value = $entity->get('deletedAssignedUserName');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @throws Conflict
     */
    private function assertAssignedUserAvailable(Entity $entity, ?string $assignedUserId): void
    {
        if ($assignedUserId === null) {
            return;
        }

        $duplicate = $this->entityManager
            ->getRDBRepository($entity->getEntityType())
            ->where([
                'assignedUserId' => $assignedUserId,
                'id!=' => $entity->getId(),
            ])
            ->findOne();

        if ($duplicate !== null) {
            throw new Conflict('assignedUserAlreadyUsed');
        }
    }
}
