<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\ActivityOffer;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftChangeNotifyService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Soft plan update when offer description changes (Collecting / Planned only).
 */
class AfterSaveNotifyPlanUpdate implements AfterSave
{
    public static int $order = 55;

    public function __construct(
        private ShiftChangeNotifyService $shiftChangeNotifyService,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SILENT)) {
            return;
        }

        if ($options->get(ShiftChangeNotifyService::SKIP_PLAN_UPDATE_NOTIFY)) {
            return;
        }

        if ($entity->isNew()) {
            return;
        }

        if (!$this->shiftChangeNotifyService->offerAllowsNotify($entity)) {
            return;
        }

        $changed = [];

        foreach (ShiftChangeNotifyService::importantOfferFields() as $field) {
            if ($entity->isAttributeChanged($field)) {
                $changed[] = $field;
            }
        }

        if ($changed === []) {
            return;
        }

        $this->shiftChangeNotifyService->queuePlanUpdatedNotify(
            (string) $entity->getId(),
            $changed,
            null
        );
    }
}
