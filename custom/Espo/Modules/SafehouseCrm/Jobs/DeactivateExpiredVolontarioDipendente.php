<?php

namespace Espo\Modules\SafehouseCrm\Jobs;

/**
 * Back-compat job name for scheduled-job rows created before the canonical
 * {@see SyncVolontarioDipendenteStatus} / metadata key {@see SafehouseCrmSyncVolontarioDipendenteStatus}.
 *
 * Espo resolves unknown scheduled job names via the Jobs class map; without this
 * class, runs fail with "Job … not found".
 */
class DeactivateExpiredVolontarioDipendente extends SyncVolontarioDipendenteStatus
{
}
