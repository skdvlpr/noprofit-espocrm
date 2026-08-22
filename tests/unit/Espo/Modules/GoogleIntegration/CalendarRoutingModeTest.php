<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\GoogleIntegration;

use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarRoutingMode;
use PHPUnit\Framework\TestCase;

class CalendarRoutingModeTest extends TestCase
{
    public function testIsValidAcceptsKnownModes(): void
    {
        $this->assertTrue(CalendarRoutingMode::isValid(CalendarRoutingMode::PRIMARY));
        $this->assertTrue(CalendarRoutingMode::isValid(CalendarRoutingMode::USER_PICK));
        $this->assertTrue(CalendarRoutingMode::isValid(CalendarRoutingMode::AUTO_DEDICATED));
    }

    public function testIsValidRejectsEmptyAndUnknown(): void
    {
        $this->assertFalse(CalendarRoutingMode::isValid(null));
        $this->assertFalse(CalendarRoutingMode::isValid(''));
        $this->assertFalse(CalendarRoutingMode::isValid('unknown'));
    }

    public function testNormalizeFallsBackToPrimary(): void
    {
        $this->assertSame(CalendarRoutingMode::PRIMARY, CalendarRoutingMode::normalize(null));
        $this->assertSame(CalendarRoutingMode::PRIMARY, CalendarRoutingMode::normalize('bad'));
    }

    public function testNormalizePreservesValidMode(): void
    {
        $this->assertSame(
            CalendarRoutingMode::AUTO_DEDICATED,
            CalendarRoutingMode::normalize(CalendarRoutingMode::AUTO_DEDICATED)
        );
    }
}
