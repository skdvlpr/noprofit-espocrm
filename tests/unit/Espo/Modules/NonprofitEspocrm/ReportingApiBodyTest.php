<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\Reporting\Api\ReportingApiBody;
use PHPUnit\Framework\TestCase;
use stdClass;

class ReportingApiBodyTest extends TestCase
{
    public function testNullBodyReturnsEmptyArray(): void
    {
        $this->assertSame([], ReportingApiBody::toArray(null));
    }

    public function testStdClassBodyConvertsToAssociativeArray(): void
    {
        $body = new stdClass();
        $body->from = '2026-01-01';
        $body->to = '2026-01-31';
        $body->entityType = 'MealCount';

        $this->assertSame([
            'from' => '2026-01-01',
            'to' => '2026-01-31',
            'entityType' => 'MealCount',
        ], ReportingApiBody::toArray($body));
    }

    public function testNestedObjectIsDecoded(): void
    {
        $filters = new stdClass();
        $filters->status = 'Active';

        $body = new stdClass();
        $body->filters = $filters;

        $result = ReportingApiBody::toArray($body);

        $this->assertSame(['status' => 'Active'], $result['filters']);
    }
}
