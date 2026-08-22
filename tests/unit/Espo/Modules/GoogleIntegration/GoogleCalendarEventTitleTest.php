<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\GoogleIntegration;

use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarEventTitle;
use PHPUnit\Framework\TestCase;

class GoogleCalendarEventTitleTest extends TestCase
{
    public function testSeparatorIsCanonicalDash(): void
    {
        $this->assertSame(' - ', GoogleCalendarEventTitle::SEPARATOR);
    }

    public function testFormatCombinesRecordNameAndLabel(): void
    {
        $this->assertSame(
            'Board meeting - Main',
            GoogleCalendarEventTitle::format('Board meeting', 'Main')
        );
    }

    public function testFormatReturnsLabelWhenRecordNameEmpty(): void
    {
        $this->assertSame('Close', GoogleCalendarEventTitle::format('', 'Close'));
    }

    public function testFormatReturnsRecordNameWhenLabelEmpty(): void
    {
        $this->assertSame('Board meeting', GoogleCalendarEventTitle::format('Board meeting', ''));
    }

    public function testFormatTrimsWhitespace(): void
    {
        $this->assertSame(
            'Board meeting - Main',
            GoogleCalendarEventTitle::format('  Board meeting  ', '  Main  ')
        );
    }
}
