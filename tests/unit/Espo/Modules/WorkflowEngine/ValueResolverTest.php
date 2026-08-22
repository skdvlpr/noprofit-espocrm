<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\WorkflowEngine;

use Espo\Core\Formula\Manager as FormulaManager;
use Espo\Modules\WorkflowEngine\Services\ValueResolver;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;

class ValueResolverTest extends TestCase
{
    private EntityManager $entityManager;

    private FormulaManager $formulaManager;

    private LoggerInterface $log;

    private ValueResolver $resolver;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManager::class);
        $this->formulaManager = $this->createMock(FormulaManager::class);
        $this->log = $this->createMock(LoggerInterface::class);
        $this->resolver = new ValueResolver($this->entityManager, $this->formulaManager, $this->log);
    }

    public function testResolveRawScalarValue(): void
    {
        $entity = $this->createMock(Entity::class);

        $this->assertSame('hello', $this->resolver->resolveValue('hello', $entity));
    }

    public function testResolveRawConstantFromArray(): void
    {
        $entity = $this->createMock(Entity::class);

        $this->assertSame(
            'Done',
            $this->resolver->resolveValue(['sourceType' => 'raw', 'value' => 'Done'], $entity)
        );
        $this->assertSame(
            42,
            $this->resolver->resolveValue(['sourceType' => 'constant', 'constantValue' => 42], $entity)
        );
    }

    public function testResolveFieldPathOnSameEntity(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('get')->with('name')->willReturn('Task A');

        $this->assertSame(
            'Task A',
            $this->resolver->resolveValue(['sourceType' => 'field', 'sourceField' => 'name'], $entity)
        );
    }

    public function testResolveFieldPathReturnsNullForRelatedPathOnPlainEntity(): void
    {
        $entity = $this->createMock(Entity::class);

        $this->assertNull(
            $this->resolver->resolveValue(
                ['sourceType' => 'field', 'sourceField' => 'assignedUser.name'],
                $entity
            )
        );
    }

    public function testResolveFieldPathReturnsNullForEmptyPath(): void
    {
        $entity = $this->createMock(Entity::class);

        $this->assertNull(
            $this->resolver->resolveValue(['sourceType' => 'field', 'sourceField' => ''], $entity)
        );
    }

    public function testResolveFormulaExpression(): void
    {
        $entity = $this->createMock(Entity::class);

        $this->formulaManager
            ->expects($this->once())
            ->method('runSafe')
            ->with('string\\concat(name, "-done")', $entity, $this->isInstanceOf(stdClass::class))
            ->willReturn('Task-done');

        $this->assertSame(
            'Task-done',
            $this->resolver->resolveValue(
                ['sourceType' => 'formula', 'expression' => 'string\\concat(name, "-done")'],
                $entity
            )
        );
    }

    public function testResolveExpressionAliasAndInferredTypes(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('get')->with('name')->willReturn('direct');

        $this->formulaManager
            ->method('runSafe')
            ->willReturn('x');

        $this->assertSame(
            'x',
            $this->resolver->resolveValue(['sourceType' => 'expression', 'formula' => '1'], $entity)
        );
        $this->assertSame(
            'x',
            $this->resolver->resolveValue(['expression' => '1'], $entity)
        );
        $this->assertSame(
            'direct',
            $this->resolver->resolveValue(['sourceField' => 'name'], $entity)
        );
    }

    public function testResolveAssignmentsFromListDefinition(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('get')->willReturnCallback(
            fn (string $field) => match ($field) {
                'status' => 'Completed',
                'priority' => 'High',
                default => null,
            }
        );

        $resolved = $this->resolver->resolveAssignments([
            ['field' => 'status', 'sourceType' => 'field', 'sourceField' => 'status'],
            ['field' => 'priority', 'sourceType' => 'raw', 'value' => 'High'],
        ], $entity);

        $this->assertSame([
            'status' => 'Completed',
            'priority' => 'High',
        ], $resolved);
    }

    public function testResolveAssignmentsFromMapDefinition(): void
    {
        $entity = $this->createMock(Entity::class);

        $resolved = $this->resolver->resolveAssignments([
            'description' => ['sourceType' => 'raw', 'value' => 'Updated by workflow'],
        ], $entity);

        $this->assertSame(['description' => 'Updated by workflow'], $resolved);
    }

    public function testResolveAssignmentsFromStdClass(): void
    {
        $entity = $this->createMock(Entity::class);
        $item = (object) [
            'field' => 'description',
            'sourceType' => 'raw',
            'value' => 'From stdClass',
        ];

        $resolved = $this->resolver->resolveAssignments([$item], $entity);

        $this->assertSame(['description' => 'From stdClass'], $resolved);
    }

    public function testResolveAssignmentsSkipsBlankFields(): void
    {
        $entity = $this->createMock(Entity::class);

        $this->assertSame([], $this->resolver->resolveAssignments([], $entity));
        $this->assertSame(
            ['valid' => 'ok'],
            $this->resolver->resolveAssignments([
                ['field' => '', 'value' => 'ignored'],
                ['field' => 'valid', 'sourceType' => 'raw', 'value' => 'ok'],
            ], $entity)
        );
    }
}
