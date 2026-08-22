<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\GoogleIntegration;

use Espo\Core\Utils\Config;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarDateTimeResolver;
use PHPUnit\Framework\TestCase;

class CalendarDateTimeResolverTest extends TestCase
{
    public function testGetExportTimeZoneMatchesConfig(): void
    {
        $resolver = new CalendarDateTimeResolver($this->createConfig(['timeZone' => 'Europe/Rome']));

        $this->assertSame('Europe/Rome', $resolver->getExportTimeZone());
    }

    public function testUtcStorageToWallClockDateTimeConvertsTimezone(): void
    {
        $resolver = new CalendarDateTimeResolver($this->createConfig(['timeZone' => 'Europe/Rome']));

        $this->assertSame(
            '2026-06-15T10:00:00',
            $resolver->utcStorageToWallClockDateTime('2026-06-15 08:00:00')
        );
    }

    public function testBuildGoogleTimedRangeExportsWallClockAndTimezone(): void
    {
        $resolver = new CalendarDateTimeResolver($this->createConfig(['timeZone' => 'Europe/Rome']));

        $range = $resolver->buildGoogleTimedRange(
            '2026-06-15 08:00:00',
            '2026-06-15 08:45:00'
        );

        $this->assertSame('2026-06-15T10:00:00', $range['start']['dateTime']);
        $this->assertSame('2026-06-15T10:45:00', $range['end']['dateTime']);
        $this->assertSame('Europe/Rome', $range['start']['timeZone']);
        $this->assertSame('Europe/Rome', $range['end']['timeZone']);
    }

    public function testBuildGoogleTimedRangeExtendsEndWhenNotAfterStart(): void
    {
        $resolver = new CalendarDateTimeResolver($this->createConfig(['timeZone' => 'UTC']));

        $range = $resolver->buildGoogleTimedRange(
            '2026-06-15 08:00:00',
            '2026-06-15 08:00:00'
        );

        $this->assertSame('2026-06-15T08:00:00', $range['start']['dateTime']);
        $this->assertSame('2026-06-15T08:30:00', $range['end']['dateTime']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createConfig(array $values): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            static fn (string $key) => $values[$key] ?? null
        );

        return $config;
    }
}
