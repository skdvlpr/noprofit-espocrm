<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\ActivityOfferSlot;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Keep calendar dates aligned with Giorno (dayOfWeek) using the plan weekStart.
 * - dayOfWeek change → recompute dateStart/dateEnd (preserve clock times)
 * - dateStart change alone → refresh dayOfWeek label
 */
class BeforeSaveSyncDatesFromDayOfWeek implements BeforeSave
{
    public static int $order = 12;

    public function __construct(
        private ShiftPlanningService $shiftPlanningService,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SKIP_HOOKS)) {
            return;
        }

        $dayChanged = $entity->isNew()
            ? ((string) ($entity->get('dayOfWeek') ?? '') !== '')
            : $entity->isAttributeChanged('dayOfWeek');

        if ($dayChanged) {
            $this->shiftPlanningService->syncSlotDatesFromDayOfWeek($entity);

            return;
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('dateStart')) {
            $this->shiftPlanningService->syncSlotDayOfWeekFromDateStart($entity);
        }
    }
}
