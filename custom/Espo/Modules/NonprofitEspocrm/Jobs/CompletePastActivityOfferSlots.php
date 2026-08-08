<?php

namespace Espo\Modules\NonprofitEspocrm\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService;

/**
 * Every 15 minutes: ActivityOfferSlot with dateEnd in the past → status Completed.
 * No volunteer notifications (status-only system transition).
 */
class CompletePastActivityOfferSlots implements JobDataLess
{
    public function __construct(
        private ShiftPlanningService $shiftPlanningService,
    ) {}

    public function run(): void
    {
        $this->shiftPlanningService->completePastSlots();
    }
}
