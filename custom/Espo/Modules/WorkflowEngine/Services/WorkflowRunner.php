<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Loads matching active WorkflowDefinitions and runs conditions + actions.
 */
class WorkflowRunner
{
    private const MAX_DEPTH = 3;

    private static int $depth = 0;

    public function __construct(
        private EntityManager $entityManager,
        private ConditionEvaluator $conditionEvaluator,
        private ActionExecutor $actionExecutor,
        private ConditionStateService $conditionStateService,
        private LoggerInterface $log,
    ) {}

    /**
     * @param string|string[] $triggerType
     */
    public function process(Entity $entity, string|array $triggerType): void
    {
        if (self::$depth >= self::MAX_DEPTH) {
            $this->log->warning(
                'WorkflowEngine: max recursion depth reached for {entityType}#{id}',
                [
                    'entityType' => $entity->getEntityType(),
                    'id' => $entity->getId(),
                ]
            );

            return;
        }

        $triggerTypes = is_array($triggerType)
            ? array_values(array_unique(array_filter($triggerType, 'is_string')))
            : [$triggerType];

        if ($triggerTypes === []) {
            return;
        }

        $definitions = $this->entityManager
            ->getRDBRepository('WorkflowDefinition')
            ->where([
                'isActive' => true,
                'targetEntityType' => $entity->getEntityType(),
                'triggerType' => $triggerTypes,
            ])
            ->order('executionOrder')
            ->order('createdAt')
            ->find();

        self::$depth++;

        try {
            foreach ($definitions as $definition) {
                $this->runDefinition($definition, $entity);
            }
        } finally {
            self::$depth--;
        }
    }

    public function runDefinitionOnEntity(Entity $definition, Entity $entity): void
    {
        if (self::$depth >= self::MAX_DEPTH) {
            return;
        }

        self::$depth++;

        try {
            $this->runDefinition($definition, $entity);
        } finally {
            self::$depth--;
        }
    }

    private function runDefinition(Entity $definition, Entity $entity): void
    {
        $conditionGroup = $definition->get('conditionGroup');
        $conditionFormula = $definition->get('conditionFormula');

        $passed = $this->conditionEvaluator->passes(
            $entity,
            is_array($conditionGroup) || is_object($conditionGroup) ? $conditionGroup : null,
            is_string($conditionFormula) ? $conditionFormula : null
        );

        if (!$passed) {
            $this->log->info(
                'WorkflowEngine: conditions not met for {name} on {entityType}#{id}',
                [
                    'name' => (string) ($definition->get('name') ?? $definition->getId()),
                    'entityType' => $entity->getEntityType(),
                    'id' => $entity->getId(),
                ]
            );
        }

        if (!$this->conditionStateService->shouldExecute($definition, $entity, $passed)) {
            return;
        }

        $actions = $definition->get('actions');

        if (!is_array($actions) || $actions === []) {
            return;
        }

        $anyOk = false;

        foreach ($actions as $action) {
            if (!is_array($action)) {
                if (is_object($action)) {
                    $action = json_decode(json_encode($action), true);
                } else {
                    continue;
                }
            }

            if (!is_array($action)) {
                continue;
            }

            try {
                $result = $this->actionExecutor->execute($action, $entity);

                if ($result['ok'] ?? false) {
                    $anyOk = true;
                } else {
                    $this->log->warning(
                        'WorkflowEngine action {type} failed on {definition}: {detail}',
                        [
                            'type' => $result['type'] ?? '?',
                            'definition' => $definition->getId(),
                            'detail' => $result['detail'] ?? '',
                        ]
                    );
                }
            } catch (Throwable $e) {
                $this->log->error(
                    'WorkflowEngine action exception: {message}',
                    ['message' => $e->getMessage()]
                );
            }
        }

        if ($anyOk) {
            $this->conditionStateService->markFired($definition, $entity);
        }
    }
}
