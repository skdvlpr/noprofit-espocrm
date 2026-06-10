<?php

namespace Espo\Modules\SafehouseCrm\Core\Utils\Metadata\AdditionalBuilder;

use Espo\Core\Utils\Metadata\AdditionalBuilder;
use stdClass;

/**
 * Registers Quick view as the default record opener for all Safehouse CRM entities.
 */
class QuickViewDefaultNavigation implements AdditionalBuilder
{
    private const LIST_HANDLER = 'safehouse-crm:handlers/quick-view-list';

    private const KANBAN_HANDLER = 'safehouse-crm:handlers/quick-view-kanban';

    /** Espo record/list uses setupHandlerType `record/list`, not `list`. */
    private const LIST_HANDLER_TYPE = 'record/list';

    /** @var list<string> */
    private const ENTITY_TYPES = [
        'VolunteerEmployee',
        'Member',
        'MealCount',
        'AccountWebsite',
        'Account',
        'Opportunity',
        'Document',
        'Meeting',
        'Contact',
    ];

    public function build(stdClass $data): void
    {
        foreach (self::ENTITY_TYPES as $entityType) {
            $this->applyListView($data, $entityType);
            $this->mergeViewSetupHandler($data, $entityType, self::LIST_HANDLER_TYPE, self::LIST_HANDLER);
            $this->mergeViewSetupHandler($data, $entityType, 'record/kanban', self::KANBAN_HANDLER);
        }
    }

    private function applyListView(stdClass $data, string $entityType): void
    {
        $data->clientDefs ??= (object) [];
        $data->clientDefs->$entityType ??= (object) [];
        $data->clientDefs->$entityType->recordViews ??= (object) [];

        if (isset($data->clientDefs->$entityType->recordViews->list)) {
            return;
        }

        $listView = $entityType === 'Document'
            ? 'safehouse-crm:views/document/list'
            : 'custom:views/record/list-inline-edit';

        $data->clientDefs->$entityType->recordViews->list = $listView;
    }

    private function mergeViewSetupHandler(
        stdClass $data,
        string $entityType,
        string $handlerType,
        string $handler
    ): void {
        $data->clientDefs ??= (object) [];
        $data->clientDefs->$entityType ??= (object) [];
        $data->clientDefs->$entityType->viewSetupHandlers ??= (object) [];

        $handlers = $data->clientDefs->$entityType->viewSetupHandlers->$handlerType ?? [];

        if (!is_array($handlers)) {
            $handlers = [];
        }

        if (!in_array($handler, $handlers, true)) {
            $handlers[] = $handler;
        }

        $data->clientDefs->$entityType->viewSetupHandlers->$handlerType = $handlers;
    }
}
