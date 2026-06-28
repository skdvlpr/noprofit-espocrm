<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\FoodParcelRegistration;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\FoodParcelTextFormat;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class SyncNotesPdf implements BeforeSave
{
    public static int $order = 10;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $entity->set([
            'notesPdf' => FoodParcelTextFormat::formatNotesPdf($entity->get('notes')),
        ]);
    }
}
