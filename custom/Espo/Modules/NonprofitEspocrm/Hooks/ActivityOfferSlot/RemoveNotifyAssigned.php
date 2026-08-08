<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\ActivityOfferSlot;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Hook\Hook\BeforeRemove;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;

/**
 * On slot delete: notify Assigned/Confirmed (silent if none), then try auto-close week.
 */
class RemoveNotifyAssigned implements BeforeRemove, AfterRemove
{
    public static int $order = 5;

    public function __construct(
        private ShiftPlanningService $shiftPlanningService,
    ) {}

    public function beforeRemove(Entity $entity, RemoveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SILENT)) {
            return;
        }

        $this->shiftPlanningService->notifyOnSlotDelete($entity);
    }

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SILENT)) {
            return;
        }

        $offerId = (string) ($entity->get('activityOfferId') ?? '');

        if ($offerId === '') {
            return;
        }

        $this->shiftPlanningService->tryAutoCloseOffer($offerId);
    }
}
