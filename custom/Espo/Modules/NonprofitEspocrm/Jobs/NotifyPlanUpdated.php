<?php

namespace Espo\Modules\NonprofitEspocrm\Jobs;

use Espo\Core\Job\Job as JobContract;
use Espo\Core\Job\Job\Data;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftChangeNotifyService;
use Throwable;

/**
 * Debounced soft plan-update email (description / category / requiredCount).
 */
class NotifyPlanUpdated implements JobContract
{
    public function __construct(
        private ShiftChangeNotifyService $shiftChangeNotifyService,
    ) {}

    public function run(Data $data): void
    {
        $offerId = $data->get('offerId');
        $userId = $data->get('userId');
        $labels = $data->get('changedLabels');

        if (!is_string($offerId) || $offerId === '' || !is_string($userId) || $userId === '') {
            return;
        }

        if (!is_array($labels)) {
            $labels = [];
        }

        $labels = array_values(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            $labels
        ), static fn (string $v): bool => $v !== ''));

        if ($labels === []) {
            return;
        }

        try {
            $this->shiftChangeNotifyService->processPlanUpdated($offerId, $userId, $labels);
        } catch (Throwable) {
            // Do not crash the queue worker.
        }
    }
}
