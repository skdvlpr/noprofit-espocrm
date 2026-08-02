<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Services;

use Espo\Core\Formula\Manager as FormulaManager;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use stdClass;
use Throwable;

/**
 * Resolves action field values: raw constant | field path | formula expression.
 */
class ValueResolver
{
    public function __construct(
        private EntityManager $entityManager,
        private FormulaManager $formulaManager,
        private LoggerInterface $log,
    ) {}

    /**
     * @param list<array<string, mixed>>|array<string, mixed> $assignments
     * @return array<string, mixed>
     */
    public function resolveAssignments(array $assignments, Entity $entity): array
    {
        $assignments = $this->normalizeToArray($assignments);

        if ($assignments === []) {
            return [];
        }

        if (!$this->isListArray($assignments)) {
            $attributes = [];

            foreach ($assignments as $field => $valueDefinition) {
                if (!is_string($field) || trim($field) === '') {
                    continue;
                }

                $attributes[$field] = $this->resolveValue($valueDefinition, $entity);
            }

            return $attributes;
        }

        $attributes = [];

        foreach ($assignments as $item) {
            if (!is_array($item)) {
                $item = $this->normalizeToArray($item);
            }

            if (!is_array($item)) {
                continue;
            }

            $field = trim((string) ($item['field'] ?? ''));

            if ($field === '') {
                continue;
            }

            $attributes[$field] = $this->resolveValue($item, $entity);
        }

        return $attributes;
    }

    public function resolveValue(mixed $valueDefinition, Entity $entity): mixed
    {
        if ($valueDefinition instanceof \stdClass) {
            $valueDefinition = $this->normalizeToArray($valueDefinition);
        }

        if (!is_array($valueDefinition)) {
            return $valueDefinition;
        }

        return match ($this->normalizeSourceType($valueDefinition)) {
            'field' => $this->resolveFieldPath(
                $entity,
                (string) ($valueDefinition['sourceField'] ?? '')
            ),
            'formula', 'expression' => $this->resolveFormula(
                $entity,
                (string) ($valueDefinition['expression'] ?? $valueDefinition['formula'] ?? '')
            ),
            default => $valueDefinition['value']
                ?? $valueDefinition['constantValue']
                ?? null,
        };
    }

    /**
     * @return array<string|int, mixed>
     */
    private function normalizeToArray(mixed $value): array
    {
        if (is_array($value)) {
            $out = [];

            foreach ($value as $key => $item) {
                $out[$key] = is_object($item) ? $this->normalizeToArray($item) : $item;
            }

            return $out;
        }

        if (is_object($value)) {
            $decoded = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @param array<string|int, mixed> $value
     */
    private function isListArray(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        $expected = 0;

        foreach ($value as $key => $_) {
            if ($key !== $expected) {
                return false;
            }

            $expected++;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $valueDefinition
     */
    private function normalizeSourceType(array $valueDefinition): string
    {
        $sourceType = strtolower(trim((string) ($valueDefinition['sourceType'] ?? '')));

        if ($sourceType === 'raw' || $sourceType === 'constant') {
            return 'raw';
        }

        if ($sourceType === 'expression') {
            return 'formula';
        }

        if ($sourceType !== '') {
            return $sourceType;
        }

        if (trim((string) ($valueDefinition['expression'] ?? $valueDefinition['formula'] ?? '')) !== '') {
            return 'formula';
        }

        if (trim((string) ($valueDefinition['sourceField'] ?? '')) !== '') {
            return 'field';
        }

        return 'raw';
    }

    private function resolveFieldPath(Entity $entity, string $path): mixed
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (!str_contains($path, '.')) {
            return $entity->get($path);
        }

        [$link, $attribute] = explode('.', $path, 2);

        if ($attribute === '' || !$entity instanceof CoreEntity) {
            return null;
        }

        try {
            $related = $this->entityManager
                ->getRDBRepository($entity->getEntityType())
                ->getRelation($entity, $link)
                ->findOne();
        } catch (Throwable $e) {
            $this->log->warning(
                'WorkflowEngine ValueResolver related read failed: {message}',
                ['message' => $e->getMessage()]
            );

            return null;
        }

        if (!$related instanceof Entity) {
            return null;
        }

        return $related->get($attribute);
    }

    private function resolveFormula(Entity $entity, string $expression): mixed
    {
        $expression = trim($expression);

        if ($expression === '') {
            return null;
        }

        try {
            return $this->formulaManager->runSafe($expression, $entity, new stdClass());
        } catch (Throwable $e) {
            $this->log->error(
                'WorkflowEngine ValueResolver formula failed: {message}',
                ['message' => $e->getMessage()]
            );

            return null;
        }
    }
}
