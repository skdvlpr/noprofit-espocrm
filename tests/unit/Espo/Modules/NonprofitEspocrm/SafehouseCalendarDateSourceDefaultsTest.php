<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\Calendar\SafehouseCalendarDateSourceDefaults;
use PHPUnit\Framework\TestCase;

class SafehouseCalendarDateSourceDefaultsTest extends TestCase
{
    public function testCanonicalLabels(): void
    {
        $this->assertSame('Presentation', SafehouseCalendarDateSourceDefaults::CANONICAL_LABELS['Opportunity:presentationDate']);
        $this->assertSame('Shift', SafehouseCalendarDateSourceDefaults::CANONICAL_LABELS['ActivityOfferSlot:main']);
    }

    public function testSourcesReturnsExpectedEntries(): void
    {
        $sources = SafehouseCalendarDateSourceDefaults::sources();

        $this->assertCount(2, $sources);

        $this->assertSame('Opportunity', $sources[0]['targetEntityType']);
        $this->assertSame('presentationDate', $sources[0]['dateField']);
        $this->assertTrue($sources[0]['allDay']);

        $this->assertSame('ActivityOfferSlot', $sources[1]['targetEntityType']);
        $this->assertSame('dateStart', $sources[1]['dateField']);
        $this->assertSame('dateEnd', $sources[1]['endDateField']);
        $this->assertFalse($sources[1]['allDay']);
        $this->assertFalse($sources[1]['calendarViewEnabled']);
    }

    public function testDefaultCalendarTemplatesStructure(): void
    {
        $templates = SafehouseCalendarDateSourceDefaults::DEFAULT_CALENDAR_TEMPLATES;

        $this->assertCount(1, $templates);
        $this->assertSame('Opportunity', $templates[0]['targetEntityType']);
        $this->assertStringContainsString('{{name}}', $templates[0]['summaryTemplate']);
    }
}
