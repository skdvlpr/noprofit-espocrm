<?php

namespace Espo\Modules\SafehouseCrm\Core\Utils\Metadata\AdditionalBuilder;

use Espo\Core\Utils\Metadata\AdditionalBuilder;
use stdClass;

/**
 * Registers Quick view as the default record opener for Safehouse CRM entities.
 *
 * Entity discovery (no hardcoded list):
 * - all scopes with module = SafehouseCrm and entity = true;
 * - any scope with quickViewDefaultNavigation = true (core CRM entities customized by Safehouse);
 * - opt-out via quickViewDefaultNavigation = false on a scope.
 */
class QuickViewDefaultNavigation implements AdditionalBuilder
{
    private const MODULE_NAME = 'SafehouseCrm';

    private const LIST_HANDLER = 'safehouse-crm:handlers/quick-view-list';

    private const KANBAN_HANDLER = 'safehouse-crm:handlers/quick-view-kanban';

    /** Espo record/list uses setupHandlerType `record/list`, not `list`. */
    private const LIST_HANDLER_TYPE = 'record/list';

    public function build(stdClass $data): void
    {
        foreach ($this->resolveEntityTypes($data) as $entityType) {
            $this->applyListView($data, $entityType);
            $this->mergeViewSetupHandler($data, $entityType, self::LIST_HANDLER_TYPE, self::LIST_HANDLER);
            $this->mergeViewSetupHandler($data, $entityType, 'record/kanban', self::KANBAN_HANDLER);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveEntityTypes(stdClass $data): array
    {
        if (!isset($data->scopes) || !$data->scopes instanceof stdClass) {
            return [];
        }

        $entityTypes = [];

        foreach ($data->scopes as $entityType => $scope) {
            if (!is_string($entityType) || !$scope instanceof stdClass) {
                continue;
            }

            if ($scope->disabled ?? false) {
                continue;
            }

            if (!($scope->entity ?? false)) {
                continue;
            }

            if (($scope->quickViewDefaultNavigation ?? null) === false) {
                continue;
            }

            $module = $scope->module ?? null;
            $optIn = ($scope->quickViewDefaultNavigation ?? null) === true;

            if ($module !== self::MODULE_NAME && !$optIn) {
                continue;
            }

            $entityTypes[] = $entityType;
        }

        sort($entityTypes);

        return $entityTypes;
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
            : 'safehouse-crm:views/record/list-inline-edit';

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
