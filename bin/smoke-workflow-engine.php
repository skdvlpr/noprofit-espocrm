<?php

declare(strict_types=1);

/**
 * Smoke: WorkflowEngine — metadata, triggers, recurrence (Vtiger onlyFirstTime), scheduling.
 *
 * Usage: ddev exec php bin/smoke-workflow-engine.php
 */

require __DIR__ . '/lib/refuse-production.php';

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\Modules\WorkflowEngine\Services\ScheduleBuilder;
use Espo\Modules\WorkflowEngine\Tools\Installer;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

(new Installer())->runPostInstall($container);

/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);
$metadata->init(true);

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);

$failures = 0;
$check = static function (string $name, bool $pass, string $detail = '') use (&$failures): void {
    if (!$pass) {
        $failures++;
    }

    echo sprintf("[%s] %s%s\n", $pass ? 'PASS' : 'FAIL', $name, $detail === '' ? '' : " — {$detail}");
};

$fields = $metadata->get(['entityDefs', 'WorkflowDefinition', 'fields']) ?? [];
$scope = $metadata->get(['scopes', 'WorkflowDefinition']) ?? [];
$acl = $metadata->get(['aclDefs', 'WorkflowDefinition']) ?? [];
$triggerOptions = $fields['triggerType']['options'] ?? [];
$recurrenceOptions = $fields['recurrenceMode']['options'] ?? [];

$check('scope entity=true', ($scope['entity'] ?? false) === true);
$check('module WorkflowEngine', ($scope['module'] ?? null) === 'WorkflowEngine');
$check('default ACL read=no', ($acl['read'] ?? null) === 'no');
$check('Controller class exists', class_exists(\Espo\Modules\WorkflowEngine\Controllers\WorkflowDefinition::class));
$check('AfterSave hook class exists', class_exists(\Espo\Modules\WorkflowEngine\Hooks\Common\WorkflowTrigger::class));
$check('ConditionStateService exists', class_exists(\Espo\Modules\WorkflowEngine\Services\ConditionStateService::class));
$check('ScheduleBuilder exists', class_exists(ScheduleBuilder::class));
$check(
    'WorkflowConditionState scope',
    ($metadata->get(['scopes', 'WorkflowConditionState', 'entity']) ?? false) === true
);

$check(
    'trigger options Vtiger set',
    $triggerOptions === ['manual', 'afterCreate', 'afterSave', 'scheduled'],
    json_encode($triggerOptions) ?: ''
);
$check('no signal trigger', !in_array('signal', $triggerOptions, true));
$check('no afterUpdate trigger', !in_array('afterUpdate', $triggerOptions, true));
$check(
    'recurrenceMode options',
    $recurrenceOptions === ['everyTime', 'onlyFirstTime'],
    json_encode($recurrenceOptions) ?: ''
);
$check(
    'scheduling view',
    ($fields['scheduling']['view'] ?? null) === 'workflow-engine:views/fields/scheduling'
);
$check(
    'scheduling JS file',
    is_file(__DIR__ . '/../client/custom/modules/workflow-engine/src/views/fields/scheduling.js')
);

$builder = new ScheduleBuilder();
$check(
    'ScheduleBuilder daily',
    $builder->build('daily', '15', '9', null, '1') === '15 9 * * *'
);
$check(
    'ScheduleBuilder weekly',
    $builder->build('weekly', '0', '8', ['1', '3', '5'], '1') === '0 8 * * 1,3,5'
);
$check(
    'ScheduleBuilder monthly last',
    $builder->build('monthly', '0', '10', null, 'last') === '0 10 L * *'
);

$users = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['isActive' => true, 'type' => ['admin', 'regular']])
    ->limit(0, 1)
    ->find();

$userList = iterator_to_array($users);
$check('active user available', $userList !== []);

if ($userList === []) {
    echo "Cannot continue without a user.\n";
    exit(1);
}

$assignee = $userList[0];
$marker = 'wf-smoke-' . bin2hex(random_bytes(4));

$definition = $em->getNewEntity('WorkflowDefinition');
$definition->set([
    'name' => 'Smoke Task create ' . $marker,
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
        [
            'type' => 'equals',
            'attribute' => 'status',
            'value' => 'Not Started',
        ],
    ],
    'conditionFormula' => null,
    'actions' => [
        [
            'type' => 'CreateNotification',
            'to' => 'assignedUser',
            'message' => 'WF smoke {{name}} (' . $marker . ')',
        ],
    ],
]);
$em->saveEntity($definition);
$check('created WorkflowDefinition', $definition->hasId());

$task = $em->getNewEntity('Task');
$task->set([
    'name' => 'WF smoke task ' . $marker,
    'status' => 'Not Started',
    'priority' => 'Normal',
    'category' => 'MealDistribution',
    'assignedUserId' => $assignee->getId(),
]);
$em->saveEntity($task);
$check('created matching Task', $task->hasId());

$notification = $em->getRDBRepository(Notification::ENTITY_TYPE)
    ->where([
        'userId' => $assignee->getId(),
        'type' => Notification::TYPE_MESSAGE,
    ])
    ->order('createdAt', 'DESC')
    ->findOne();

$msg = $notification ? (string) $notification->get('message') : '';
$check(
    'CreateNotification fired for matching Task',
    $notification !== null && str_contains($msg, $marker),
    $msg !== '' ? substr($msg, 0, 120) : 'none'
);

$task2 = $em->getNewEntity('Task');
$task2->set([
    'name' => 'WF smoke skip ' . $marker,
    'status' => 'Not Started',
    'priority' => 'Normal',
    'category' => 'Administrative',
    'assignedUserId' => $assignee->getId(),
]);
$em->saveEntity($task2);

$recent = $em->getRDBRepository(Notification::ENTITY_TYPE)
    ->where([
        'userId' => $assignee->getId(),
        'type' => Notification::TYPE_MESSAGE,
        'relatedId' => $task2->getId(),
        'relatedType' => 'Task',
    ])
    ->findOne();

$check('non-matching Task did not notify', $recent === null);

// Vtiger onlyFirstTime: fire once per record, never again even if conditions still true.
$onceMarker = 'wf-once-' . bin2hex(random_bytes(3));
$onceDef = $em->getNewEntity('WorkflowDefinition');
$onceDef->set([
    'name' => 'Smoke onlyFirstTime ' . $onceMarker,
    'isActive' => true,
    'targetEntityType' => 'Account',
    'triggerType' => 'afterSave',
    'recurrenceMode' => 'onlyFirstTime',
    'executionOrder' => 1,
    'conditionGroup' => [
        [
            'type' => 'equals',
            'attribute' => 'type',
            'value' => 'Customer',
        ],
    ],
            'actions' => [
                [
                    'type' => 'UpdateFields',
                    'assignments' => [
                        [
                            'field' => 'description',
                            'type' => 'raw',
                            'value' => 'ONCE-' . $onceMarker,
                        ],
                    ],
                ],
            ],
        ]);
        $em->saveEntity($onceDef);

        $account = $em->getNewEntity('Account');
        $account->set([
            'name' => 'WF once ' . $onceMarker,
            'type' => 'Customer',
        ]);
        $em->saveEntity($account);
        $em->refreshEntity($account);

        $check(
            'onlyFirstTime first save wrote description',
            str_contains((string) $account->get('description'), 'ONCE-' . $onceMarker),
            substr((string) $account->get('description'), 0, 80)
        );

$state = $em->getRDBRepository('WorkflowConditionState')
    ->where([
        'workflowDefinitionId' => $onceDef->getId(),
        'targetEntityType' => 'Account',
        'targetEntityId' => $account->getId(),
    ])
    ->findOne();

$check('onlyFirstTime ConditionState row created', $state !== null);

$account->set('description', 'RESET-' . $onceMarker);
$em->saveEntity($account);
$em->refreshEntity($account);

$check(
    'onlyFirstTime second save did not re-fire',
    (string) $account->get('description') === 'RESET-' . $onceMarker
);

// Scheduled definition persists cron from visual preset fields via BeforeSave.
$sched = $em->getNewEntity('WorkflowDefinition');
$sched->set([
    'name' => 'Smoke scheduled ' . $marker,
    'isActive' => false,
    'targetEntityType' => 'Task',
    'triggerType' => 'scheduled',
    'schedulePreset' => 'daily',
    'scheduleMinute' => '30',
    'scheduleHour' => '7',
    'recurrenceMode' => 'everyTime',
    'executionOrder' => 50,
    'conditionGroup' => [],
    'actions' => [],
]);
$em->saveEntity($sched);
$em->refreshEntity($sched);
$check(
    'scheduled BeforeSave builds cron',
    (string) $sched->get('scheduling') === '30 7 * * *',
    (string) $sched->get('scheduling')
);

// Cleanup
$definition->set('isActive', false);
$em->saveEntity($definition);
$onceDef->set('isActive', false);
$em->saveEntity($onceDef);

foreach ([$notification, $task, $task2, $definition, $state, $account, $onceDef, $sched] as $row) {
    if ($row) {
        $em->removeEntity($row);
    }
}

$check(
    'ZIP build script exists',
    is_file(__DIR__ . '/build-workflow-engine.sh')
);

if ($failures > 0) {
    echo "FAILED: {$failures}\n";
    exit(1);
}

echo "WorkflowEngine Vtiger recurrence/schedule smoke ALL PASSED\n";
