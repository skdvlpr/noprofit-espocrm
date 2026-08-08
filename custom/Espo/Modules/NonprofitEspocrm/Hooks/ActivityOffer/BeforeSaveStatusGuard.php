<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\ActivityOffer;

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
        $this->guardStatusField($entity, $options, 'Draft');
    }
}
