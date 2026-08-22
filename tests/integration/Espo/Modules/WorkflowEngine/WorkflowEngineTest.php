<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\WorkflowEngine;

use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\Modules\WorkflowEngine\Services\ScheduleBuilder;
use integration\Core\NoTransaction;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * WorkflowEngine metadata, scheduling, and trigger (converted from bin/smoke-workflow-engine.php).
 */
class WorkflowEngineTest extends SafehouseBaseTestCase
{
    #[NoTransaction]
    public function testMetadataScheduleBuilderAndAfterCreateTrigger(): void
    {
        $metadata = $this->getMetadata();
        $fields = $metadata->get(['entityDefs', 'WorkflowDefinition', 'fields']) ?? [];
        $triggerOptions = $fields['triggerType']['options'] ?? [];

        $this->assertSame('WorkflowEngine', $metadata->get(['scopes', 'WorkflowDefinition', 'module']));
        $this->assertSame(['manual', 'afterCreate', 'afterSave', 'scheduled'], $triggerOptions);
        $this->assertTrue(class_exists(\Espo\Modules\WorkflowEngine\Services\ConditionStateService::class));

        $builder = new ScheduleBuilder();
        $this->assertSame('15 9 * * *', $builder->build('daily', '15', '9', null, '1'));

        $em = $this->getEntityManager();
        $assignee = $em->getRDBRepository(User::ENTITY_TYPE)
            ->where(['userName' => 'admin'])
            ->findOne();

        if ($assignee === null) {
            $assignee = $this->createUser([
                'userName' => 'wf-phpunit-user',
                'firstName' => 'WF',
                'lastName' => 'PHPUnit',
                'type' => 'admin',
                'isActive' => true,
            ]);
        }

        $this->assertNotNull($assignee);

        $marker = 'wf-phpunit-' . bin2hex(random_bytes(4));

        $definition = $em->getNewEntity('WorkflowDefinition');
        $definition->set([
            'name' => 'PHPUnit Task create ' . $marker,
            'isActive' => true,
            'targetEntityType' => 'Task',
            'triggerType' => 'afterCreate',
            'recurrenceMode' => 'everyTime',
            'executionOrder' => 1,
            'conditionGroup' => [
                [
                    'type' => 'equals',
                    'attribute' => 'category',
                    'value' => 'MealDistribution',
                ],
            ],
            'actions' => [
                [
                    'type' => 'CreateNotification',
                    'to' => 'assignedUser',
                    'message' => 'WF phpunit {{name}} (' . $marker . ')',
                ],
            ],
        ]);
        $em->saveEntity($definition);

        $task = $em->getNewEntity('Task');
        $task->set([
            'name' => 'WF phpunit task ' . $marker,
            'status' => 'Not Started',
            'priority' => 'Normal',
            'category' => 'MealDistribution',
            'assignedUserId' => $assignee->getId(),
        ]);
        $em->saveEntity($task);

        $notifications = $em->getRDBRepository(Notification::ENTITY_TYPE)
            ->where([
                'userId' => $assignee->getId(),
                'type' => Notification::TYPE_MESSAGE,
            ])
            ->order('createdAt', 'DESC')
            ->limit(0, 5)
            ->find();

        $found = false;

        foreach ($notifications as $notification) {
            $message = (string) $notification->get('message');

            if (str_contains($message, $marker)) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected workflow notification containing marker ' . $marker);
    }
}
