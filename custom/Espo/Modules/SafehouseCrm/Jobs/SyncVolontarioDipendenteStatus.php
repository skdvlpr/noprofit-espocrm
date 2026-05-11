<?php

namespace Espo\Modules\SafehouseCrm\Jobs;

/**
 * Daily reconciliation of VolontarioDipendente.status against [dataInizio, dataFine].
 */
class SyncVolontarioDipendenteStatus extends AbstractStatusWindowSync
{
    protected function entityType(): string
    {
        return 'VolontarioDipendente';
    }

    protected function startField(): string
    {
        return 'dataInizio';
    }

    protected function endField(): string
    {
        return 'dataFine';
    }

    protected function statusField(): string
    {
        return 'status';
    }

    protected function activeValue(): string
    {
        return 'Attivo';
    }

    protected function inactiveValue(): string
    {
        return 'Inattivo';
    }
}
