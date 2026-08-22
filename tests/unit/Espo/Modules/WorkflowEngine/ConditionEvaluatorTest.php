<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\WorkflowEngine;

use Espo\Core\Formula\Manager as FormulaManager;
use Espo\Modules\WorkflowEngine\Services\ConditionEvaluator;
use Espo\ORM\Entity;
use Espo\Tools\DynamicLogic\ConditionChecker;
use Espo\Tools\DynamicLogic\ConditionCheckerFactory;
use Espo\Tools\DynamicLogic\Exceptions\BadCondition;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConditionEvaluatorTest extends TestCase
{
    private ConditionCheckerFactory $conditionCheckerFactory;

    private FormulaManager $formulaManager;

    private LoggerInterface $log;

    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->conditionCheckerFactory = $this->createMock(ConditionCheckerFactory::class);
        $this->formulaManager = $this->createMock(FormulaManager::class);
        $this->log = $this->createMock(LoggerInterface::class);
        $this->evaluator = new ConditionEvaluator(
            $this->conditionCheckerFactory,
            $this->formulaManager,
            $this->log
        );
    }

    public function testPassesWhenConditionGroupMissingOrEmpty(): void
    {
        $entity = $this->createMock(Entity::class);

        $this->conditionCheckerFactory->expects($this->never())->method('create');

        $this->assertTrue($this->evaluator->passes($entity, null, null));
        $this->assertTrue($this->evaluator->passes($entity, [], null));
    }

    public function testPassesWhenCheckerReturnsTrue(): void
    {
        $entity = $this->createMock(Entity::class);
        $checker = $this->createMock(ConditionChecker::class);

        $this->conditionCheckerFactory->method('create')->with($entity)->willReturn($checker);
        $checker->method('check')->willReturn(true);

        $group = [
            ['type' => 'equals', 'attribute' => 'status', 'value' => 'Completed'],
        ];

        $this->assertTrue($this->evaluator->passes($entity, $group, null));
    }

    public function testFailsWhenCheckerReturnsFalse(): void
    {
        $entity = $this->createMock(Entity::class);
        $checker = $this->createMock(ConditionChecker::class);

        $this->conditionCheckerFactory->method('create')->with($entity)->willReturn($checker);
        $checker->method('check')->willReturn(false);

        $group = [
            ['type' => 'equals', 'attribute' => 'status', 'value' => 'Completed'],
        ];

        $this->assertFalse($this->evaluator->passes($entity, $group, null));
    }

    public function testAcceptsWrappedConditionGroupKey(): void
    {
        $entity = $this->createMock(Entity::class);
        $checker = $this->createMock(ConditionChecker::class);

        $this->conditionCheckerFactory->method('create')->willReturn($checker);
        $checker->method('check')->willReturn(true);

        $wrapped = [
            'conditionGroup' => [
                ['type' => 'equals', 'attribute' => 'priority', 'value' => 'High'],
            ],
        ];

        $this->assertTrue($this->evaluator->passes($entity, $wrapped, null));
    }

    public function testFormulaMustBeTruthyWhenPresent(): void
    {
        $entity = $this->createMock(Entity::class);
        $checker = $this->createMock(ConditionChecker::class);

        $this->conditionCheckerFactory->method('create')->willReturn($checker);
        $checker->method('check')->willReturn(true);

        $this->formulaManager
            ->expects($this->once())
            ->method('run')
            ->with('status == "Completed"', $entity)
            ->willReturn(true);

        $this->assertTrue(
            $this->evaluator->passes(
                $entity,
                [],
                'status == "Completed"'
            )
        );
    }

    public function testFormulaFalseFailsEvenWhenGroupPasses(): void
    {
        $entity = $this->createMock(Entity::class);
        $checker = $this->createMock(ConditionChecker::class);

        $this->conditionCheckerFactory->method('create')->willReturn($checker);
        $checker->method('check')->willReturn(true);
        $this->formulaManager->method('run')->willReturn(false);

        $this->assertFalse($this->evaluator->passes($entity, [], 'false'));
    }

    public function testBadConditionReturnsFalse(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('getEntityType')->willReturn('Task');
        $entity->method('getId')->willReturn('1');

        $checker = $this->createMock(ConditionChecker::class);
        $this->conditionCheckerFactory->method('create')->willReturn($checker);
        $checker->method('check')->willThrowException(new BadCondition('invalid group'));

        $this->log->expects($this->once())->method('error');

        $this->assertFalse(
            $this->evaluator->passes(
                $entity,
                [['type' => 'unknown', 'attribute' => 'status', 'value' => 'x']],
                null
            )
        );
    }
}
