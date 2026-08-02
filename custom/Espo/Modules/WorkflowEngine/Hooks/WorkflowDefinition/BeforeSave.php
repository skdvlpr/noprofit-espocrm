<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Hooks\WorkflowDefinition;

use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveInterface;
use Espo\Modules\WorkflowEngine\Services\ScheduleBuilder;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSaveInterface<Entity>
 */
class BeforeSave implements BeforeSaveInterface
{
    public static int $order = 9;

    public function __construct(
        private ScheduleBuilder $scheduleBuilder
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->scheduleBuilder->applyToEntity($entity);

        if ((string) $entity->get('triggerType') === 'manual') {
            $entity->set('recurrenceMode', 'everyTime');
        }
    }
}
