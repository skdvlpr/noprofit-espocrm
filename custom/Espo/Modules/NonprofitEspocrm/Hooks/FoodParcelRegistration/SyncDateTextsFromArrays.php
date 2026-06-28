<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\FoodParcelRegistration;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\FoodParcelTextFormat;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class SyncDateTextsFromArrays implements BeforeSave
{
    public static int $order = 9;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $entity->set([
            'entryDatesText' => FoodParcelTextFormat::formatDatesList($entity->get('entryDates')),
            'exitDatesText' => FoodParcelTextFormat::formatDatesList($entity->get('exitDates')),
        ]);
    }
}
