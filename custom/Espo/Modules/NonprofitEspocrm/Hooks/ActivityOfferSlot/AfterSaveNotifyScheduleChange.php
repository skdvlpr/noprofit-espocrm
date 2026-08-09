<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\ActivityOfferSlot;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftChangeNotifyService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Place/time/conditions change → debounced hard availability re-request.
 * Soft important fields (category, requiredCount) → planUpdated
 * (skipped when schedule notify already queued).
 */
class AfterSaveNotifyScheduleChange implements AfterSave
{
    public static int $order = 55;

    public function __construct(
        private EntityManager $entityManager,
        private ShiftChangeNotifyService $shiftChangeNotifyService,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SILENT)) {
            return;
        }

        if ($options->get(ShiftChangeNotifyService::SKIP_SCHEDULE_NOTIFY)
            && $options->get(ShiftChangeNotifyService::SKIP_PLAN_UPDATE_NOTIFY)
        ) {
            return;
        }

        if ($entity->isNew()) {
            return;
        }

        $slotId = (string) $entity->getId();
        $offerId = (string) ($entity->get('activityOfferId') ?? '');

        if ($slotId === '' || $offerId === '') {
            return;
        }

        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer || !$this->shiftChangeNotifyService->offerAllowsNotify($offer)) {
            return;
        }

        $scheduleChanged = false;

        if (!$options->get(ShiftChangeNotifyService::SKIP_SCHEDULE_NOTIFY)) {
            foreach (ShiftChangeNotifyService::placeTimeFields() as $field) {
                if ($entity->isAttributeChanged($field)) {
                    $scheduleChanged = true;
                    break;
                }
            }
        }

        if ($scheduleChanged) {
            $this->shiftChangeNotifyService->queueScheduleChangeNotify($slotId);

            return;
        }

        if ($options->get(ShiftChangeNotifyService::SKIP_PLAN_UPDATE_NOTIFY)) {
            return;
        }

        if ($this->shiftChangeNotifyService->wasScheduleChangeQueuedForSlot($slotId)) {
            return;
        }

        $changedImportant = [];

        foreach (ShiftChangeNotifyService::importantSlotFields() as $field) {
            if ($entity->isAttributeChanged($field)) {
                $changedImportant[] = $field;
            }
        }

        if ($changedImportant === []) {
            return;
        }

        $this->shiftChangeNotifyService->queuePlanUpdatedNotify($offerId, $changedImportant, $slotId);
    }
}
