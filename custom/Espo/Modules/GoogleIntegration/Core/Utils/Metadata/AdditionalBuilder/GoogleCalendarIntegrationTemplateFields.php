<?php

namespace Espo\Modules\GoogleIntegration\Core\Utils\Metadata\AdditionalBuilder;

use Espo\Core\Utils\Metadata\AdditionalBuilder;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceEntityTypesReader;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use stdClass;

/**
 * Adds per-entity description template fields to the Google Calendar integration
 * for every active CalendarDateSource target (and core export entities).
 */
class GoogleCalendarIntegrationTemplateFields implements AdditionalBuilder
{
    private const INTEGRATION_ID = Installer::INTEGRATION_ID;

    private const DEFAULT_TEMPLATE = "{{name}}\n\nEspoCRM: {{espocrmUrl}}";

    /** @var list<string> */
    private const CORE_ENTITY_TYPES = [
        'Meeting',
        'Call',
        'Task',
        'Opportunity',
        'VolunteerEmployee',
    ];

    public function build(stdClass $data): void
    {
        $entityTypes = $this->collectEntityTypes();

        if ($entityTypes === []) {
            return;
        }

        $data->integrations ??= (object) [];

        if (!isset($data->integrations->{self::INTEGRATION_ID})) {
            $data->integrations->{self::INTEGRATION_ID} = (object) [];
        }

        $integration = $data->integrations->{self::INTEGRATION_ID};
        $integration->fields ??= (object) [];

        foreach ($entityTypes as $entityType) {
            $fieldName = 'googleCalendarDescriptionTemplate' . $entityType;

            if (isset($integration->fields->$fieldName)) {
                continue;
            }

            $integration->fields->$fieldName = (object) [
                'type' => 'text',
                'default' => self::DEFAULT_TEMPLATE,
            ];
        }
    }

    /**
     * @return list<string>
     */
    private function collectEntityTypes(): array
    {
        $entityTypes = [];

        foreach (self::CORE_ENTITY_TYPES as $entityType) {
            $entityTypes[$entityType] = true;
        }

        foreach ((new DateSourceEntityTypesReader())->readActiveTargetEntityTypes() as $entityType) {
            $entityTypes[$entityType] = true;
        }

        $list = array_keys($entityTypes);
        sort($list);

        return $list;
    }
}
