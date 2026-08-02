<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Hooks\Common;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Modules\WorkflowEngine\Services\WorkflowRunner;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Universal afterSave trigger. Uses AfterSave (not LateAfterSave) so entity->isNew()
 * still distinguishes create vs update — matching Espo + royalacademy prototype behaviour.
 *
 * @implements AfterSave<Entity>
 */
class WorkflowTrigger implements AfterSave
{
    public static int $order = 99;

    private const INTERNAL_ENTITY_TYPE_LIST = [
        'WorkflowDefinition',
        'WorkflowConditionState',
        'Notification',
        'Email',
        'Note',
        'Job',
        'ScheduledJob',
        'AuthToken',
        'AuthLogRecord',
        'UniqueId',
        'Attachment',
    ];

    public function __construct(
        private WorkflowRunner $workflowRunner
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
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

        // Vtiger-like: creation-only OR updated-includes-creation (afterSave).
        $triggerTypes = $entity->isNew()
            ? ['afterCreate', 'afterSave']
            : ['afterSave'];

        $this->workflowRunner->process($entity, $triggerTypes);
    }
}
