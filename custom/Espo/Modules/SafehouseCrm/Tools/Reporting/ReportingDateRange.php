<?php

namespace Espo\Modules\SafehouseCrm\Tools\Reporting;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Calendar month/year bounds for reporting (Europe/Rome default).
 */
final class ReportingDateRange
{
    public const DEFAULT_TIMEZONE = 'Europe/Rome';

    /**
     * @return array{0: string, 1: string} inclusive from, inclusive to (Y-m-d).
     */
    public static function currentCalendarWeek(DateTimeZone $timezone): array
    {
        $now = new DateTimeImmutable('now', $timezone);
        // ISO week: Monday = start (Europe/Rome reporting convention).
        $dayOfWeek = (int) $now->format('N');

        $from = $now->modify('-' . ($dayOfWeek - 1) . ' days')->format('Y-m-d');
        $to = $now->modify('+' . (7 - $dayOfWeek) . ' days')->format('Y-m-d');

        return [$from, $to];
    }

    /**
     * @return array{0: string, 1: string} inclusive from, inclusive to (Y-m-d).
     */
    public static function currentCalendarMonth(DateTimeZone $timezone): array
    {
        $now = new DateTimeImmutable('now', $timezone);
        $from = $now->modify('first day of this month')->format('Y-m-d');
        $to = $now->modify('last day of this month')->format('Y-m-d');

        return [$from, $to];
    }

    /**
     * @return array{0: string, 1: string} inclusive from, inclusive to (Y-m-d).
     */
    public static function currentCalendarYear(DateTimeZone $timezone): array
    {
        $now = new DateTimeImmutable('now', $timezone);
        $from = $now->modify('first day of January')->format('Y-m-d');
        $to = $now->modify('last day of December')->format('Y-m-d');

        return [$from, $to];
    }

    /**
     * @return array<string, mixed> Espo where clause for inclusive date range on $dateAttribute.
     */
    public static function dateBetweenWhere(string $dateAttribute, string $from, string $to): array
    {
        return [
            $dateAttribute . '>=' => $from,
            $dateAttribute . '<=' => $to,
        ];
    }

    public static function defaultTimezone(): DateTimeZone
    {
        return new DateTimeZone(self::DEFAULT_TIMEZONE);
    }
}
