<?php

namespace Espo\Modules\NonprofitEspocrm\Jobs;

use Espo\Core\Job\Job as JobContract;
use Espo\Core\Job\Job\Data;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftChangeNotifyService;
use Throwable;

/**
 * Debounced: place/time change on ActivityOfferSlot → reset invites + availability email.
 */
class NotifySlotScheduleChange implements JobContract
{
    public function __construct(
        private ShiftChangeNotifyService $shiftChangeNotifyService,
    ) {}

    public function run(Data $data): void
    {
        $slotId = $data->get('slotId');

        if (!is_string($slotId) || $slotId === '') {
            return;
        }

        try {
            $this->shiftChangeNotifyService->processScheduleChange($slotId);
        } catch (Throwable) {
            // Do not crash the queue worker on mail/template failures.
        }
    }
}
