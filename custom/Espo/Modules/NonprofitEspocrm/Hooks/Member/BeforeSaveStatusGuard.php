<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Member;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Hooks\Support\StatusFieldGuard;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Strip manual status edits; formula then recalculates Active/Inactive from dates.
 */
class BeforeSaveStatusGuard implements BeforeSave
{
    use StatusFieldGuard;

    public static int $order = 5;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->stripManualStatusChange($entity, $options);
    }
}
