<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Services;

use Espo\Core\Formula\Manager as FormulaManager;
use Espo\ORM\Entity;
use Espo\Tools\DynamicLogic\ConditionCheckerFactory;
use Espo\Tools\DynamicLogic\Exceptions\BadCondition;
use Espo\Tools\DynamicLogic\Item;
use Psr\Log\LoggerInterface;
use stdClass;
use Throwable;

/**
 * Evaluates WorkflowDefinition conditions (dynamicLogic conditionGroup + optional formula).
 */
class ConditionEvaluator
{
    public function __construct(
        private ConditionCheckerFactory $conditionCheckerFactory,
        private FormulaManager $formulaManager,
        private LoggerInterface $log,
    ) {}

    /**
     * @param array<string, mixed>|stdClass|null $conditionGroup
     */
    public function passes(
        Entity $entity,
        array|stdClass|null $conditionGroup,
        ?string $conditionFormula
    ): bool {
        if (!$this->passesConditionGroup($entity, $conditionGroup)) {
            return false;
        }

        return $this->passesFormula($entity, $conditionFormula);
    }

    /**
     * @param array<string, mixed>|stdClass|null $conditionGroup
     */
    private function passesConditionGroup(Entity $entity, array|stdClass|null $conditionGroup): bool
    {
        if ($conditionGroup === null) {
            return true;
        }

        if ($conditionGroup instanceof stdClass) {
            $conditionGroup = (array) $conditionGroup;
        }

        if ($conditionGroup === []) {
            return true;
        }

        // Accept either raw group array or {conditionGroup: [...]}
        if (array_key_exists('conditionGroup', $conditionGroup)) {
            $inner = $conditionGroup['conditionGroup'];

            if ($inner instanceof stdClass) {
                $inner = (array) $inner;
            }

            if (!is_array($inner) || $inner === []) {
                return true;
            }

            $conditionGroup = $inner;
        }

        try {
            $item = Item::fromGroupDefinition($this->toStdClassList($conditionGroup));
            $checker = $this->conditionCheckerFactory->create($entity);

            return $checker->check($item);
        } catch (BadCondition $e) {
            $this->log->error(
                'WorkflowEngine: bad conditionGroup for {entityType}#{id}: {message}',
                [
                    'entityType' => $entity->getEntityType(),
                    'id' => $entity->getId(),
                    'message' => $e->getMessage(),
                ]
            );

            return false;
        } catch (Throwable $e) {
            $this->log->error(
                'WorkflowEngine: conditionGroup evaluation failed: {message}',
                ['message' => $e->getMessage()]
            );

            return false;
        }
    }

    private function passesFormula(Entity $entity, ?string $conditionFormula): bool
    {
        $script = trim((string) $conditionFormula);

        if ($script === '') {
            return true;
        }

        try {
            $result = $this->formulaManager->run($script, $entity);

            return (bool) $result;
        } catch (Throwable $e) {
            $this->log->error(
                'WorkflowEngine: conditionFormula failed: {message}',
                ['message' => $e->getMessage()]
            );

            return false;
        }
    }

    /**
     * @param array<int|string, mixed> $group
     * @return list<stdClass>
     */
    private function toStdClassList(array $group): array
    {
        $out = [];

        foreach (array_values($group) as $item) {
            $converted = $this->toStdClass($item);

            if ($converted instanceof stdClass) {
                $out[] = $converted;
            }
        }

        return $out;
    }

    private function toStdClass(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $k => $v) {
                $value->$k = $this->toStdClass($v);
            }

            return $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        $isList = array_keys($value) === range(0, count($value) - 1);

        if ($isList) {
            return array_map(fn ($v) => $this->toStdClass($v), $value);
        }

        $obj = (object) [];

        foreach ($value as $k => $v) {
            $obj->{$k} = $this->toStdClass($v);
        }

        return $obj;
    }
}
