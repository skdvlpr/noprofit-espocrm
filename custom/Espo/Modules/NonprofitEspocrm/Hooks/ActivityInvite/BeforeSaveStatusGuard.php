<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\ActivityInvite;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Hooks\Support\StatusFieldGuard;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class BeforeSaveStatusGuard implements BeforeSave
{
    use StatusFieldGuard;

    public static int $order = 8;

    /**
     * @throws Forbidden
     */
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        // Invites are created by service with an explicit status + skip flag.
        // On create without skip, force Available.
        $this->guardStatusField($entity, $options, 'Available');
    }
}
