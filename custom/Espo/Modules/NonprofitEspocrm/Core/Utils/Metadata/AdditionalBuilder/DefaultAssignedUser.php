<?php

namespace Espo\Modules\NonprofitEspocrm\Core\Utils\Metadata\AdditionalBuilder;

use Espo\Core\Name\Field;
use Espo\Core\Utils\Metadata\AdditionalBuilder;
use stdClass;

/**
 * Registers assigned-user defaults for every entity type that exposes assignedUser.
 *
 * Opt-out per scope: defaultAssignedUser = false
 */
class DefaultAssignedUser implements AdditionalBuilder
{
    private const BEFORE_CREATE_HOOK = 'Espo\\Modules\\NonprofitEspocrm\\Classes\\RecordHooks\\AssignedUser\\BeforeCreate';

    private const DEFAULTS_POPULATOR = 'Espo\\Modules\\NonprofitEspocrm\\Core\\Record\\Defaults\\AssignedUserPopulator';

    public function build(stdClass $data): void
    {
        foreach ($this->resolveEntityTypes($data) as $entityType) {
            $this->mergeBeforeCreateHook($data, $entityType);
            $this->applyDefaultsPopulator($data, $entityType);
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

            if (($scope->defaultAssignedUser ?? null) === false) {
                continue;
            }

            if (!$this->hasAssignedUserField($data, $entityType)) {
                continue;
            }

            $entityTypes[] = $entityType;
        }

        sort($entityTypes);

        return $entityTypes;
    }

    private function hasAssignedUserField(stdClass $data, string $entityType): bool
    {
        $fields = $data->entityDefs->$entityType->fields ?? null;

        if (!$fields instanceof stdClass) {
            return false;
        }

        $assignedUser = $fields->{Field::ASSIGNED_USER} ?? null;

        if (!$assignedUser instanceof stdClass) {
            return false;
        }

        return !($assignedUser->disabled ?? false);
    }

    private function mergeBeforeCreateHook(stdClass $data, string $entityType): void
    {
        $data->recordDefs ??= (object) [];
        $data->recordDefs->$entityType ??= (object) [];

        $hooks = $data->recordDefs->$entityType->beforeCreateHookClassNameList ?? [];

        if (!is_array($hooks)) {
            $hooks = [];
        }

        if (!in_array(self::BEFORE_CREATE_HOOK, $hooks, true)) {
            $hooks[] = self::BEFORE_CREATE_HOOK;
        }

        $data->recordDefs->$entityType->beforeCreateHookClassNameList = $hooks;
    }

    private function applyDefaultsPopulator(stdClass $data, string $entityType): void
    {
        $data->recordDefs ??= (object) [];
        $data->recordDefs->$entityType ??= (object) [];

        if (isset($data->recordDefs->$entityType->defaultsPopulatorClassName)) {
            return;
        }

        $data->recordDefs->$entityType->defaultsPopulatorClassName = self::DEFAULTS_POPULATOR;
    }
}
