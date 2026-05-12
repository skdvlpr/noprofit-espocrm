<?php

namespace Espo\Modules\SafehouseCrm\Jobs;

/**
 * Legacy alias of {@see SyncVolunteerEmployeeStatus}.
 *
 * Kept as a separately registered scheduled job for backwards compatibility with
 * older deployments and dashboards that referenced the deactivation name. New
 * code should use {@see SyncVolunteerEmployeeStatus} directly — both share the
 * same bidirectional activate/deactivate logic from {@see AbstractStatusWindowSync}.
 */
class DeactivateExpiredVolunteerEmployee extends SyncVolunteerEmployeeStatus
{
}
