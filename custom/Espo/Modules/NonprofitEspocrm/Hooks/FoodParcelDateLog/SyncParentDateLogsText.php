<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\FoodParcelDateLog;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\DateLogsTextSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class SyncParentDateLogsText implements AfterSave
{
    public static int $order = 20;

    public function __construct(private DateLogsTextSync $dateLogsTextSync) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $parentId = $entity->get('foodParcelRegistrationId');

        if (!$parentId) {
            return;
        }

        $this->dateLogsTextSync->syncForRegistrationId($parentId);
    }
}
