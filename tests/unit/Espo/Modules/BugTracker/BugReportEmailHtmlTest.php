<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\BugTracker;

use Espo\Modules\BugTracker\Tools\BugReportEmailHtml;
use PHPUnit\Framework\TestCase;

class BugReportEmailHtmlTest extends TestCase
{
    public function testEscapeNeutralizesAnchorMarkup(): void
    {
        $payload = '<p>CRM admin action required.</p>'
            . '<p><a href="https://evil.example/login">Re-authenticate here</a></p>';

        $escaped = BugReportEmailHtml::escape($payload);

        $this->assertStringNotContainsString('<a href=', $escaped);
        $this->assertStringContainsString('&lt;a href=', $escaped);
        $this->assertStringContainsString('https://evil.example/login', $escaped);
    }

    public function testEscapeQuotesAndAmpersands(): void
    {
        $this->assertSame(
            'Tom &amp; Jerry &quot;quotes&quot; &lt;tag&gt;',
            BugReportEmailHtml::escape('Tom & Jerry "quotes" <tag>')
        );
    }
}
