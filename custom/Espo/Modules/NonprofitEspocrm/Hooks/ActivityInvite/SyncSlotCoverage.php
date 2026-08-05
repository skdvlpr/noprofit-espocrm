<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\ActivityInvite;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * When invite assignment changes, refresh slot Published/Covered status.
 */
class SyncSlotCoverage implements AfterSave
{
    public static int $order = 30;

    public function __construct(
        private ShiftPlanningService $shiftPlanningService,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SKIP_HOOKS)) {
            return;
        }

        if (!$entity->isNew() && !$entity->isAttributeChanged('status')) {
            return;
        }

        $offerId = (string) ($entity->get('activityOfferId') ?? '');

        if ($offerId === '') {
            return;
        }

        $this->shiftPlanningService->syncSlotCoverageStatuses($offerId);
    }
}
