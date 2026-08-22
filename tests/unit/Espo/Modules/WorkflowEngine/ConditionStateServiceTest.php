<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\WorkflowEngine;

use Espo\Modules\WorkflowEngine\Services\ConditionStateService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;

class ConditionStateServiceTest extends TestCase
{
    private EntityManager $entityManager;

    private ConditionStateService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManager::class);
        $this->service = new ConditionStateService($this->entityManager);
    }

    public function testShouldExecuteFalseWhenConditionsNotPassed(): void
    {
        $definition = $this->createMock(Entity::class);
        $entity = $this->createMock(Entity::class);

        $this->assertFalse($this->service->shouldExecute($definition, $entity, false));
    }

    public function testShouldExecuteTrueForEveryTimeMode(): void
    {
        $definition = $this->createMock(Entity::class);
        $definition->method('get')->willReturnMap([
            ['recurrenceMode', null, 'everyTime'],
        ]);

        $entity = $this->createMock(Entity::class);

        $this->assertTrue($this->service->shouldExecute($definition, $entity, true));
    }

    public function testShouldExecuteOnlyFirstTimeWhenNotYetFired(): void
    {
        $definition = $this->createMock(Entity::class);
        $definition->method('get')->willReturnCallback(
            fn (string $field) => match ($field) {
                'recurrenceMode' => 'onlyFirstTime',
                'id' => 'def-1',
                default => null,
            }
        );

        $entity = $this->createMock(Entity::class);
        $entity->method('getEntityType')->willReturn('Task');
        $entity->method('getId')->willReturn('task-1');

        $repository = $this->createMock(RDBRepository::class);
        $selectBuilder = $this->createMock(RDBSelectBuilder::class);
        $selectBuilder->method('findOne')->willReturn(null);
        $repository->method('where')->willReturn($selectBuilder);

        $this->entityManager
            ->method('getRDBRepository')
            ->with('WorkflowConditionState')
            ->willReturn($repository);

        $this->assertTrue($this->service->shouldExecute($definition, $entity, true));
    }

    public function testShouldExecuteOnlyFirstTimeBlocksRepeat(): void
    {
        $definition = $this->createMock(Entity::class);
        $definition->method('get')->willReturnCallback(
            fn (string $field) => match ($field) {
                'recurrenceMode' => 'onlyFirstTime',
                'id' => 'def-1',
                default => null,
            }
        );

        $entity = $this->createMock(Entity::class);
        $entity->method('getEntityType')->willReturn('Task');
        $entity->method('getId')->willReturn('task-1');

        $existing = $this->createMock(Entity::class);
        $repository = $this->createMock(RDBRepository::class);
        $selectBuilder = $this->createMock(RDBSelectBuilder::class);
        $selectBuilder->method('findOne')->willReturn($existing);
        $repository->method('where')->willReturn($selectBuilder);

        $this->entityManager
            ->method('getRDBRepository')
            ->with('WorkflowConditionState')
            ->willReturn($repository);

        $this->assertFalse($this->service->shouldExecute($definition, $entity, true));
    }

    public function testMarkFiredCreatesStateRowForOnlyFirstTime(): void
    {
        $definition = $this->createMock(Entity::class);
        $definition->method('get')->willReturnCallback(
            fn (string $field) => $field === 'recurrenceMode' ? 'onlyFirstTime' : null
        );
        $definition->method('getId')->willReturn('def-1');

        $entity = $this->createMock(Entity::class);
        $entity->method('getEntityType')->willReturn('Task');
        $entity->method('getId')->willReturn('task-1');

        $repository = $this->createMock(RDBRepository::class);
        $selectBuilder = $this->createMock(RDBSelectBuilder::class);
        $selectBuilder->method('findOne')->willReturn(null);
        $repository->method('where')->willReturn($selectBuilder);

        $stateRow = $this->createMock(Entity::class);
        $stateRow->expects($this->once())->method('set')->with($this->callback(
            fn (array $data): bool => ($data['workflowDefinitionId'] ?? null) === 'def-1'
                && ($data['targetEntityType'] ?? null) === 'Task'
                && ($data['targetEntityId'] ?? null) === 'task-1'
        ));

        $this->entityManager->method('getRDBRepository')->willReturn($repository);
        $this->entityManager->method('getNewEntity')->with('WorkflowConditionState')->willReturn($stateRow);
        $this->entityManager->expects($this->once())->method('saveEntity')->with(
            $stateRow,
            ['skipWorkflowEngine' => true]
        );

        $this->service->markFired($definition, $entity);
    }

    public function testMarkFiredNoOpForEveryTime(): void
    {
        $definition = $this->createMock(Entity::class);
        $definition->method('get')->with('recurrenceMode')->willReturn('everyTime');

        $entity = $this->createMock(Entity::class);

        $this->entityManager->expects($this->never())->method('saveEntity');

        $this->service->markFired($definition, $entity);
    }
}
