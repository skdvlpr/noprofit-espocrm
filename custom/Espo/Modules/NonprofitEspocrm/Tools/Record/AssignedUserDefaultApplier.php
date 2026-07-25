<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Record;

use Espo\Core\Name\Field;
use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Sets assignedUser to the current user when creating a record and the field is empty.
 */
class AssignedUserDefaultApplier
{
    public function __construct(
        private User $user,
        private EntityManager $entityManager,
        private Metadata $metadata,
    ) {}

    public function applyIfEmpty(Entity $entity): void
    {
        if ($this->user->isPortal() || $this->user->isSystem()) {
            return;
        }

        $entityType = $entity->getEntityType();

        if ($this->isAssignedUserDisabled($entityType)) {
            return;
        }

        $field = $this->entityManager
            ->getDefs()
            ->getEntity($entityType)
            ->tryGetField(Field::ASSIGNED_USER);

        if ($field?->getType() !== FieldType::LINK) {
            return;
        }

        $assignedUserId = $entity->get(Field::ASSIGNED_USER . 'Id');

        if ($assignedUserId !== null && $assignedUserId !== '') {
            return;
        }

        $entity->set(Field::ASSIGNED_USER . 'Id', $this->user->getId());
        $entity->set(Field::ASSIGNED_USER . 'Name', $this->user->getName());
    }

    private function isAssignedUserDisabled(string $entityType): bool
    {
        return ($this->metadata->get([
            'entityDefs',
            $entityType,
            'fields',
            Field::ASSIGNED_USER,
            'disabled',
        ]) ?? false) === true;
    }
}
