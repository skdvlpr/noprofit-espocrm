<?php

declare(strict_types=1);

/**
 * Smoke: WorkflowEngine W2 — definition CRUD path + condition match + CreateNotification.
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

$check('scope entity=true', ($scope['entity'] ?? false) === true);
$check('module WorkflowEngine', ($scope['module'] ?? null) === 'WorkflowEngine');
$check('default ACL read=no', ($acl['read'] ?? null) === 'no');
$check('Controller class exists', class_exists(\Espo\Modules\WorkflowEngine\Controllers\WorkflowDefinition::class));
$check('AfterSave hook class exists', class_exists(\Espo\Modules\WorkflowEngine\Hooks\Common\WorkflowTrigger::class));

foreach ([
    'name', 'isActive', 'targetEntityType', 'triggerType',
    'conditionGroup', 'conditionFormula', 'actions', 'executionOrder',
] as $field) {
    $check("field {$field}", isset($fields[$field]));
}

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

// Non-matching task should not notify with marker
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

// Cleanup
$definition->set('isActive', false);
$em->saveEntity($definition);

foreach ([$notification, $task, $task2, $definition] as $row) {
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

echo "WorkflowEngine W2 smoke ALL PASSED\n";
