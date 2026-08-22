<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\WorkflowEngine;

use Espo\Modules\WorkflowEngine\Services\ScheduleBuilder;
use PHPUnit\Framework\TestCase;

class ScheduleBuilderTest extends TestCase
{
    private ScheduleBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ScheduleBuilder();
    }

    public function testBuildDaily(): void
    {
        $this->assertSame('15 9 * * *', $this->builder->build('daily', '15', '9', null, '1'));
    }

    public function testBuildWeekly(): void
    {
        $this->assertSame(
            '0 8 * * 1,3,5',
            $this->builder->build('weekly', '0', '8', ['1', '3', '5'], '1')
        );
    }

    public function testBuildMonthlyLastDay(): void
    {
        $this->assertSame(
            '0 10 L * *',
            $this->builder->build('monthly', '0', '10', null, 'last')
        );
    }

    public function testBuildHourly(): void
    {
        $this->assertSame('45 * * * *', $this->builder->build('hourly', '45', '9', null, '1'));
    }
}
