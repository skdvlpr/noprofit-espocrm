<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\GoogleIntegration;

use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarPlainText;
use PHPUnit\Framework\TestCase;

class GoogleCalendarPlainTextTest extends TestCase
{
    public function testNormalizeEmptyReturnsEmpty(): void
    {
        $this->assertSame('', GoogleCalendarPlainText::normalize(''));
    }

    public function testNormalizeDecodesHtmlEntities(): void
    {
        $this->assertSame(
            'Tom & Jerry "quotes"',
            GoogleCalendarPlainText::normalize('Tom &amp; Jerry &quot;quotes&quot;')
        );
    }
}
