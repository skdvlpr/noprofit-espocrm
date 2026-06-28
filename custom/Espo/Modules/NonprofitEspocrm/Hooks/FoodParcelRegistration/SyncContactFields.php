<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\FoodParcelRegistration;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\FoodParcelContactSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class SyncContactFields implements BeforeSave
{
    public static int $order = 8;

    public function __construct(
        private FoodParcelContactSync $foodParcelContactSync,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->foodParcelContactSync->syncFromContactId($entity);
    }
}
