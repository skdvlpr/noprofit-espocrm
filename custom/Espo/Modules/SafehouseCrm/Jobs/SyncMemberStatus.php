<?php

namespace Espo\Modules\SafehouseCrm\Jobs;

/**
 * Daily reconciliation of Member.status against [joinDate, leaveDate].
 */
class SyncMemberStatus extends AbstractStatusWindowSync
{
    protected function entityType(): string
    {
        return 'Member';
    }

    protected function startField(): string
    {
        return 'joinDate';
    }

    protected function endField(): string
    {
        return 'leaveDate';
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
