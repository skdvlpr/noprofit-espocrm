<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\ReportingDateRange;
use PHPUnit\Framework\TestCase;

class ReportingDateRangeTest extends TestCase
{
    public function testCurrentCalendarMonthBounds(): void
    {
        $tz = new DateTimeZone('Europe/Rome');
        [$from, $to] = ReportingDateRange::currentCalendarMonth($tz);

        $now = new DateTimeImmutable('now', $tz);

        $this->assertSame($now->modify('first day of this month')->format('Y-m-d'), $from);
        $this->assertSame($now->modify('last day of this month')->format('Y-m-d'), $to);
    }

    public function testDateBetweenWhere(): void
    {
        $where = ReportingDateRange::dateBetweenWhere('transactionDate', '2026-01-01', '2026-01-31');

        $this->assertSame([
            'transactionDate>=' => '2026-01-01',
            'transactionDate<=' => '2026-01-31',
        ], $where);
    }

    public function testDefaultTimezone(): void
    {
        $this->assertSame('Europe/Rome', ReportingDateRange::defaultTimezone()->getName());
    }
}
