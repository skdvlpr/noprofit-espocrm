<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\WorkflowEngine;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\InjectableFactory;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\Modules\WorkflowEngine\Jobs\RunScheduledWorkflows;
use Espo\Modules\WorkflowEngine\Services\ConditionEvaluator;
use Espo\Modules\WorkflowEngine\Services\ScheduleBuilder;
use Espo\Modules\WorkflowEngine\Services\TemplateRenderer;
use Espo\ORM\Entity;
use integration\Core\NoTransaction;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * WorkflowEngine metadata, scheduling, triggers, actions, and scheduled jobs.
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
        $assignee = $this->resolveAssignee($em);

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

        $this->assertTrue($this->notificationExistsForUser($assignee->getId(), $marker));
    }

    #[NoTransaction]
    public function testBeforeSaveAppliesScheduleBuilderOnScheduledDefinition(): void
    {
        $em = $this->getEntityManager();

        $definition = $em->getNewEntity('WorkflowDefinition');
        $definition->set([
            'name' => 'PHPUnit scheduled preset ' . bin2hex(random_bytes(3)),
            'isActive' => true,
            'targetEntityType' => 'Task',
            'triggerType' => 'scheduled',
            'schedulePreset' => 'daily',
            'scheduleMinute' => '15',
            'scheduleHour' => '9',
            'scheduleMonthDay' => '1',
            'recurrenceMode' => 'everyTime',
            'executionOrder' => 1,
            'actions' => [],
        ]);
        $em->saveEntity($definition);

        $this->assertSame('15 9 * * *', $definition->get('scheduling'));

        $reloaded = $em->getEntityById('WorkflowDefinition', $definition->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame('15 9 * * *', $reloaded->get('scheduling'));
    }

    #[NoTransaction]
    public function testUpdateFieldsActionOnAfterSave(): void
    {
        $em = $this->getEntityManager();
        $assignee = $this->resolveAssignee($em);
        $marker = 'wf-update-' . bin2hex(random_bytes(4));

        $definition = $em->getNewEntity('WorkflowDefinition');
        $definition->set([
            'name' => 'PHPUnit UpdateFields ' . $marker,
            'isActive' => true,
            'targetEntityType' => 'Task',
            'triggerType' => 'afterSave',
            'recurrenceMode' => 'everyTime',
            'executionOrder' => 1,
            'conditionGroup' => [
                [
                    'type' => 'equals',
                    'attribute' => 'description',
                    'value' => $marker,
                ],
            ],
            'actions' => [
                [
                    'type' => 'UpdateFields',
                    'assignments' => [
                        [
                            'field' => 'priority',
                            'sourceType' => 'raw',
                            'value' => 'High',
                        ],
                    ],
                ],
            ],
        ]);
        $em->saveEntity($definition);

        $task = $em->getNewEntity('Task');
        $task->set([
            'name' => 'WF update task ' . $marker,
            'status' => 'Not Started',
            'priority' => 'Normal',
            'description' => $marker,
            'assignedUserId' => $assignee->getId(),
        ]);
        $em->saveEntity($task);

        $reloaded = $em->getEntityById('Task', $task->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame('High', $reloaded->get('priority'));
    }

    #[NoTransaction]
    public function testOnlyFirstTimeRecurrenceBlocksSecondNotification(): void
    {
        $em = $this->getEntityManager();
        $assignee = $this->resolveAssignee($em);
        $marker = 'wf-once-' . bin2hex(random_bytes(4));

        $definition = $em->getNewEntity('WorkflowDefinition');
        $definition->set([
            'name' => 'PHPUnit onlyFirstTime ' . $marker,
            'isActive' => true,
            'targetEntityType' => 'Task',
            'triggerType' => 'afterSave',
            'recurrenceMode' => 'onlyFirstTime',
            'executionOrder' => 1,
            'conditionGroup' => [
                [
                    'type' => 'equals',
                    'attribute' => 'description',
                    'value' => $marker,
                ],
            ],
            'actions' => [
                [
                    'type' => 'CreateNotification',
                    'to' => 'assignedUser',
                    'message' => 'WF once ' . $marker,
                ],
            ],
        ]);
        $em->saveEntity($definition);

        $task = $em->getNewEntity('Task');
        $task->set([
            'name' => 'WF once task ' . $marker,
            'status' => 'Not Started',
            'priority' => 'Normal',
            'description' => $marker,
            'assignedUserId' => $assignee->getId(),
        ]);
        $em->saveEntity($task);

        $countAfterFirst = $this->countNotificationsForUser($assignee->getId(), $marker);
        $this->assertSame(1, $countAfterFirst);

        $task->set('priority', 'High');
        $em->saveEntity($task);

        $countAfterSecond = $this->countNotificationsForUser($assignee->getId(), $marker);
        $this->assertSame(1, $countAfterSecond);
    }

    #[NoTransaction]
    public function testRunScheduledWorkflowsJobUpdatesMatchingTask(): void
    {
        $em = $this->getEntityManager();
        $assignee = $this->resolveAssignee($em);
        $marker = 'wf-cron-' . bin2hex(random_bytes(4));

        $definition = $em->getNewEntity('WorkflowDefinition');
        $definition->set([
            'name' => 'PHPUnit scheduled job ' . $marker,
            'isActive' => true,
            'targetEntityType' => 'Task',
            'triggerType' => 'scheduled',
            'schedulePreset' => 'cron',
            'scheduling' => '* * * * *',
            'recurrenceMode' => 'everyTime',
            'executionOrder' => 1,
            'conditionGroup' => [
                [
                    'type' => 'equals',
                    'attribute' => 'description',
                    'value' => $marker,
                ],
            ],
            'actions' => [
                [
                    'type' => 'UpdateFields',
                    'assignments' => [
                        [
                            'field' => 'priority',
                            'sourceType' => 'raw',
                            'value' => 'Urgent',
                        ],
                    ],
                ],
            ],
        ]);
        $em->saveEntity($definition);

        $this->assertSame('* * * * *', (string) $definition->get('scheduling'));

        $task = $em->getNewEntity('Task');
        $task->set([
            'name' => 'WF cron task ' . $marker,
            'status' => 'Not Started',
            'priority' => 'Normal',
            'description' => $marker,
            'assignedUserId' => $assignee->getId(),
        ]);
        $em->saveEntity($task);

        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $job = $factory->create(RunScheduledWorkflows::class);
        $job->run();

        $reloaded = $em->getEntityById('Task', $task->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame('Urgent', $reloaded->get('priority'));
    }

    #[NoTransaction]
    public function testConditionEvaluatorEqualsAndOrGroupsAndFormula(): void
    {
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        /** @var ConditionEvaluator $evaluator */
        $evaluator = $factory->create(ConditionEvaluator::class);

        $em = $this->getEntityManager();
        $task = $em->getNewEntity('Task');
        $task->set([
            'name' => 'Condition eval task',
            'status' => 'Completed',
            'priority' => 'High',
        ]);

        $this->assertTrue($evaluator->passes($task, [
            ['type' => 'equals', 'attribute' => 'status', 'value' => 'Completed'],
        ], null));

        $this->assertFalse($evaluator->passes($task, [
            ['type' => 'equals', 'attribute' => 'status', 'value' => 'Not Started'],
        ], null));

        $this->assertTrue($evaluator->passes($task, [
            [
                'type' => 'and',
                'value' => [
                    ['type' => 'equals', 'attribute' => 'status', 'value' => 'Completed'],
                    ['type' => 'equals', 'attribute' => 'priority', 'value' => 'High'],
                ],
            ],
        ], null));

        $this->assertTrue($evaluator->passes($task, [
            [
                'type' => 'or',
                'value' => [
                    ['type' => 'equals', 'attribute' => 'status', 'value' => 'Not Started'],
                    ['type' => 'equals', 'attribute' => 'priority', 'value' => 'High'],
                ],
            ],
        ], null));

        $this->assertTrue($evaluator->passes($task, [], 'status == "Completed"'));
        $this->assertFalse($evaluator->passes($task, [], 'status == "Not Started"'));
    }

    #[NoTransaction]
    public function testTemplateRendererNormalizesEmailTemplatePlaceholders(): void
    {
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        /** @var TemplateRenderer $renderer */
        $renderer = $factory->create(TemplateRenderer::class);

        $em = $this->getEntityManager();
        $task = $em->getNewEntity('Task');
        $task->set(['name' => 'Render me']);

        $normalized = $renderer->normalizeEmailTemplatePlaceholders(
            $task,
            'Task {Task.name} parent {Parent.status}'
        );

        $this->assertSame('Task {{name}} parent {{status}}', $normalized);
    }

    private function resolveAssignee(\Espo\ORM\EntityManager $em): User
    {
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

        return $assignee;
    }

    private function notificationExistsForUser(string $userId, string $marker): bool
    {
        return $this->countNotificationsForUser($userId, $marker) > 0;
    }

    private function countNotificationsForUser(string $userId, string $marker): int
    {
        $em = $this->getEntityManager();
        $count = 0;

        $notifications = $em->getRDBRepository(Notification::ENTITY_TYPE)
            ->where([
                'userId' => $userId,
                'type' => Notification::TYPE_MESSAGE,
            ])
            ->order('createdAt', 'DESC')
            ->limit(0, 20)
            ->find();

        foreach ($notifications as $notification) {
            if (str_contains((string) $notification->get('message'), $marker)) {
                $count++;
            }
        }

        return $count;
    }
}
