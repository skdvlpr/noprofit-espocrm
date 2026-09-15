<?php

namespace Espo\Modules\GoogleIntegration\Core\Utils\Metadata\AdditionalBuilder;

use Espo\Core\Utils\Metadata\AdditionalBuilder;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceEntityTypesReader;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use stdClass;

/**
 * Adds per-entity description template fields to the Google Calendar integration
 * for every active CalendarDateSource target whose scope is a live entity.
 *
 * Catalog is metadata + date sources, not a closed Meeting/Call/Task/Opportunity list.
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/scopes.md
 */
class GoogleCalendarIntegrationTemplateFields implements AdditionalBuilder
{
    private const INTEGRATION_ID = Installer::INTEGRATION_ID;

    private const DEFAULT_TEMPLATE = "{{name}}\n\nEspoCRM: {{espocrmUrl}}";

    public function build(stdClass $data): void
    {
        $entityTypes = self::filterLiveEntityTypes(
            (new DateSourceEntityTypesReader())->readActiveTargetEntityTypes(),
            $data
        );

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
     * Keep types that exist as live entity scopes on this instance.
     *
     * @param list<string> $candidates
     * @return list<string>
     */
    public static function filterLiveEntityTypes(array $candidates, stdClass $data): array
    {
        $entityTypes = [];

        foreach ($candidates as $entityType) {
            if (!is_string($entityType) || $entityType === '') {
                continue;
            }

            if (!self::isLiveEntityScope($data, $entityType)) {
                continue;
            }

            $entityTypes[$entityType] = true;
        }

        $list = array_keys($entityTypes);
        sort($list);

        return $list;
    }

    private static function isLiveEntityScope(stdClass $data, string $entityType): bool
    {
        if (!isset($data->scopes) || !is_object($data->scopes)) {
            return false;
        }

        $scope = $data->scopes->$entityType ?? null;

        if (!is_object($scope)) {
            return false;
        }

        return ($scope->entity ?? false) === true;
    }
}
