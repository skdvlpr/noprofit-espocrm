<?php

namespace Espo\Modules\NonprofitEspocrm\Core\Utils\Metadata\AdditionalBuilder;

use Espo\Core\Utils\Metadata\AdditionalBuilder;
use stdClass;

/**
 * Registers list views and relationship-panel quick-view handlers.
 *
 * Main navbar lists open full detail (Espo default). Quick view is enabled only
 * for relationship panel lists (listSmall + buttonsDisabled) via quick-view-list handler.
 *
 * Entity discovery:
 * - all scopes with module = NonprofitEspocrm and entity = true;
 * - any scope with quickViewDefaultNavigation = true (core CRM entities customized here);
 * - opt-out via quickViewDefaultNavigation = false on a scope.
 */
class QuickViewDefaultNavigation implements AdditionalBuilder
{
    private const MODULE_NAME = 'NonprofitEspocrm';

    private const LIST_HANDLER = 'nonprofit-espocrm:handlers/quick-view-list';

    /** Espo record/list uses setupHandlerType `record/list`, not `list`. */
    private const LIST_HANDLER_TYPE = 'record/list';

    public function build(stdClass $data): void
    {
        foreach ($this->resolveEntityTypes($data) as $entityType) {
            $this->applyListView($data, $entityType);
            $this->mergeViewSetupHandler($data, $entityType, self::LIST_HANDLER_TYPE, self::LIST_HANDLER);
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
            ? 'nonprofit-espocrm:views/document/list'
            : 'nonprofit-espocrm:views/record/list-inline-edit';

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
