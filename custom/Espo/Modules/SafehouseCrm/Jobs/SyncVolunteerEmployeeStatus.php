<?php

namespace Espo\Modules\SafehouseCrm\Jobs;

/**
 * Daily reconciliation of VolunteerEmployee.status against [startDate, endDate].
 */
class SyncVolunteerEmployeeStatus extends AbstractStatusWindowSync
{
    protected function entityType(): string
    {
        return 'VolunteerEmployee';
    }

    protected function startField(): string
    {
        return 'startDate';
    }

    protected function endField(): string
    {
        return 'endDate';
    }

    protected function statusField(): string
    {
        return 'status';
    }

    protected function activeValue(): string
    {
        return 'Active';
    }

    protected function inactiveValue(): string
    {
        return 'Inactive';
    }
}
