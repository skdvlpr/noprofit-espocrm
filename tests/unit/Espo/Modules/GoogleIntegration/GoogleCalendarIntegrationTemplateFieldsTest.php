<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\GoogleIntegration;

use Espo\Modules\GoogleIntegration\Core\Utils\Metadata\AdditionalBuilder\GoogleCalendarIntegrationTemplateFields;
use PHPUnit\Framework\TestCase;
use stdClass;

class GoogleCalendarIntegrationTemplateFieldsTest extends TestCase
{
    public function testFilterSkipsTypesThatAreNotLiveEntityScopes(): void
    {
        $data = new stdClass();
        $data->scopes = (object) [
            'Meeting' => (object) ['entity' => true],
            'Opportunity' => (object) ['entity' => false],
        ];

        $this->assertSame(
            ['Meeting'],
            GoogleCalendarIntegrationTemplateFields::filterLiveEntityTypes(
                ['Meeting', 'Opportunity', 'MissingType', ''],
                $data
            )
        );
    }

    public function testFilterKeepsAnyLiveDateSourceTypeNotJustLegacyFour(): void
    {
        $data = new stdClass();
        $data->scopes = (object) [
            'Campaign' => (object) ['entity' => true],
        ];

        $this->assertSame(
            ['Campaign'],
            GoogleCalendarIntegrationTemplateFields::filterLiveEntityTypes(['Campaign'], $data)
        );
    }
}
