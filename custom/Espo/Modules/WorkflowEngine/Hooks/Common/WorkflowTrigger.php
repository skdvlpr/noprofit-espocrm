<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Hooks\Common;

use Espo\Core\Hook\Hook\LateAfterSave;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Modules\WorkflowEngine\Services\WorkflowRunner;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements LateAfterSave<Entity>
 */
class WorkflowTrigger implements LateAfterSave
{
    private const INTERNAL_ENTITY_TYPE_LIST = [
        'WorkflowDefinition',
    ];

    public function __construct(
        private WorkflowRunner $workflowRunner
    ) {}

    public function lateAfterSave(Entity $entity, SaveOptions $options): void
    {
        if (
            !$entity instanceof CoreEntity
            || $options->get(SaveOption::SILENT)
            || $options->get(SaveOption::SKIP_HOOKS)
            || $options->get('skipWorkflowEngine')
            || in_array($entity->getEntityType(), self::INTERNAL_ENTITY_TYPE_LIST, true)
        ) {
            return;
        }

        $this->workflowRunner->observe($entity);
    }
}
