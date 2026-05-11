<?php

namespace Espo\Modules\SafehouseCrm\Jobs;

/**
 * Daily reconciliation of Associati.stato against [dataIngresso, dataDimissione].
 */
class SyncAssociatiStatus extends AbstractStatusWindowSync
{
    protected function entityType(): string
    {
        return 'Associati';
    }

    protected function startField(): string
    {
        return 'dataIngresso';
    }

    protected function endField(): string
    {
        return 'dataDimissione';
    }

    protected function statusField(): string
    {
        return 'stato';
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
