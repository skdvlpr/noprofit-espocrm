<?php

namespace Espo\Modules\GoogleIntegration\Core\Utils\Metadata\AdditionalBuilder;

use Espo\Core\Utils\Metadata\AdditionalBuilder;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceEntityTypesReader;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarCapableEntities;
use stdClass;

class GoogleCalendarCapableFields implements AdditionalBuilder
{
    private const HOOK_BEFORE_SAVE =
        'Espo\\Modules\\GoogleIntegration\\Hooks\\Common\\PerDateGoogleCalendarBeforeSave';

    private const HOOK_AFTER_SAVE =
        'Espo\\Modules\\GoogleIntegration\\Hooks\\Common\\GoogleCalendarAfterSave';

    private const HOOK_AFTER_REMOVE =
        'Espo\\Modules\\GoogleIntegration\\Hooks\\Common\\GoogleCalendarAfterRemove';

    private const DELETED_RESTORER =
        'Espo\\Modules\\GoogleIntegration\\Classes\\Record\\GoogleCalendarDeletedRestorer';

    public function build(stdClass $data): void
    {
        foreach ((new DateSourceEntityTypesReader())->readActiveTargetEntityTypes() as $entityType) {
            if (!$this->isLiveEntityScope($data, $entityType)) {
                continue;
            }

            $this->applyEntityDefs($data, $entityType);
            $this->applyLayoutModuleOverride($data, $entityType);
            $this->applyRecordDefsHooks($data, $entityType);
            $this->applyClientDefsHandlers($data, $entityType);
        }
    }

    private function isLiveEntityScope(stdClass $data, string $entityType): bool
    {
        $scope = $data->scopes->$entityType ?? null;

        if (!is_object($scope)) {
            return false;
        }

        return ($scope->entity ?? false) === true;
    }

    private function applyEntityDefs(stdClass $data, string $entityType): void
    {
        $data->entityDefs ??= (object) [];

        if (!isset($data->entityDefs->$entityType)) {
            $data->entityDefs->$entityType = (object) [];
        }

        $entityDefsItem = $data->entityDefs->$entityType;
        $entityDefsItem->fields ??= (object) [];
        $entityDefsItem->links ??= (object) [];

        foreach (GoogleCalendarCapableEntities::perDateFieldDefs() as $field => $fieldDefs) {
            $entityDefsItem->fields->$field = $fieldDefs;
        }

        foreach (GoogleCalendarCapableEntities::perDateLinkDefs() as $link => $linkDefs) {
            $entityDefsItem->links->$link = $linkDefs;
        }
    }

    private function applyLayoutModuleOverride(stdClass $data, string $entityType): void
    {
        $data->app ??= (object) [];
        $data->app->layouts ??= (object) [];

        if (!isset($data->app->layouts->$entityType)) {
            $data->app->layouts->$entityType = (object) [];
        }

        $entityLayouts = $data->app->layouts->$entityType;

        // Prefer the vertical CRM module when it owns the detail layout (suite install).
        // Fall back to GoogleIntegration for standalone GI on vanilla Espo.
        $layoutModule = $this->resolveLayoutOwnerModule($entityType);

        if (!isset($entityLayouts->detail)) {
            $entityLayouts->detail = (object) [];
        }

        $entityLayouts->detail->module = $layoutModule;

        if (!isset($entityLayouts->detailSmall)) {
            $entityLayouts->detailSmall = (object) [];
        }

        $entityLayouts->detailSmall->module = $layoutModule;
    }

    private function resolveLayoutOwnerModule(string $entityType): string
    {
        foreach (['NonprofitEspocrm'] as $module) {
            $path = 'custom/Espo/Modules/' . $module
                . '/Resources/layouts/' . $entityType . '/detail.json';

            if (is_readable($path)) {
                return $module;
            }
        }

        return 'GoogleIntegration';
    }

    private function applyRecordDefsHooks(stdClass $data, string $entityType): void
    {
        $data->recordDefs ??= (object) [];
        $data->recordDefs->$entityType ??= (object) [];

        $rd = $data->recordDefs->$entityType;

        $rd->beforeSaveHookClassNameList = $this->mergeHookList(
            $rd->beforeSaveHookClassNameList ?? [], self::HOOK_BEFORE_SAVE
        );
        $rd->afterSaveHookClassNameList = $this->mergeHookList(
            $rd->afterSaveHookClassNameList ?? [], self::HOOK_AFTER_SAVE
        );
        $rd->afterRemoveHookClassNameList = $this->mergeHookList(
            $rd->afterRemoveHookClassNameList ?? [], self::HOOK_AFTER_REMOVE
        );
        $rd->deletedRestorerClassName = self::DELETED_RESTORER;
    }

    /**
     * @param list<string>|mixed $existing
     * @return list<string>
     */
    private function mergeHookList(mixed $existing, string $hook): array
    {
        $list = is_array($existing) ? $existing : [];

        if (!in_array($hook, $list, true)) {
            $list[] = $hook;
        }

        return $list;
    }

    private function applyClientDefsHandlers(stdClass $data, string $entityType): void
    {
        $handler = 'google-integration:handlers/google-calendar/save-to-google-handler';

        $data->clientDefs ??= (object) [];
        $data->clientDefs->$entityType ??= (object) [];
        $data->clientDefs->$entityType->viewSetupHandlers ??= (object) [];

        $detail = $data->clientDefs->$entityType->viewSetupHandlers->{'record/detail'} ?? [];
        $edit = $data->clientDefs->$entityType->viewSetupHandlers->{'record/edit'} ?? [];

        if (!is_array($detail)) {
            $detail = [];
        }

        if (!is_array($edit)) {
            $edit = [];
        }

        if (!in_array($handler, $detail, true)) {
            $detail[] = $handler;
        }

        if (!in_array($handler, $edit, true)) {
            $edit[] = $handler;
        }

        $data->clientDefs->$entityType->viewSetupHandlers->{'record/detail'} = $detail;
        $data->clientDefs->$entityType->viewSetupHandlers->{'record/edit'} = $edit;
    }
}
