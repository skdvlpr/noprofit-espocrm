<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Services;

use Espo\Core\Field\DateTime as DateTimeField;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Vtiger-style recurrence: everyTime vs onlyFirstTime (once ever per definition+record).
 *
 * @see https://www.vtiger.com/docs/workflows
 */
class ConditionStateService
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function shouldExecute(Entity $definition, Entity $entity, bool $conditionsPassed): bool
    {
        if (!$conditionsPassed) {
            return false;
        }

        $mode = (string) ($definition->get('recurrenceMode') ?? 'everyTime');

        if ($mode !== 'onlyFirstTime') {
            return true;
        }

        return !$this->hasFired($definition, $entity);
    }

    public function markFired(Entity $definition, Entity $entity): void
    {
        $mode = (string) ($definition->get('recurrenceMode') ?? 'everyTime');

        if ($mode !== 'onlyFirstTime') {
            return;
        }

        if ($this->hasFired($definition, $entity)) {
            return;
        }

        $row = $this->entityManager->getNewEntity('WorkflowConditionState');
        $row->set([
            'name' => sprintf(
                '%s:%s:%s',
                $definition->getId(),
                $entity->getEntityType(),
                $entity->getId()
            ),
            'workflowDefinitionId' => $definition->getId(),
            'targetEntityType' => $entity->getEntityType(),
            'targetEntityId' => $entity->getId(),
            'firedAt' => DateTimeField::createNow()->toString(),
        ]);

        $this->entityManager->saveEntity($row, ['skipWorkflowEngine' => true]);
    }

    private function hasFired(Entity $definition, Entity $entity): bool
    {
        $existing = $this->entityManager
            ->getRDBRepository('WorkflowConditionState')
            ->where([
                'workflowDefinitionId' => $definition->getId(),
                'targetEntityType' => $entity->getEntityType(),
                'targetEntityId' => $entity->getId(),
            ])
            ->findOne();

        return $existing !== null;
    }
}
