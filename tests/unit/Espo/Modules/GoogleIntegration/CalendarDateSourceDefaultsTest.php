<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\GoogleIntegration;

use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarDateSourceDefaults;
use PHPUnit\Framework\TestCase;

class CalendarDateSourceDefaultsTest extends TestCase
{
    public function testSourcesIncludeCoreEntityTypes(): void
    {
        $entityTypes = array_column(CalendarDateSourceDefaults::sources(), 'targetEntityType');

        $this->assertContains('Meeting', $entityTypes);
        $this->assertContains('Call', $entityTypes);
        $this->assertContains('Task', $entityTypes);
        $this->assertContains('Opportunity', $entityTypes);
    }

    public function testLabelKeyUsesMainWhenSourceDateTypeEmpty(): void
    {
        $this->assertSame('Meeting:main', CalendarDateSourceDefaults::labelKey('Meeting', ''));
    }

    public function testCanonicalLabelReturnsConfiguredLabel(): void
    {
        $this->assertSame('Meeting', CalendarDateSourceDefaults::canonicalLabel('Meeting', 'main'));
        $this->assertSame('Close', CalendarDateSourceDefaults::canonicalLabel('Opportunity', 'closeDate'));
    }

    public function testCanonicalLabelReturnsNullForUnknownKey(): void
    {
        $this->assertNull(CalendarDateSourceDefaults::canonicalLabel('Unknown', 'main'));
    }
}
