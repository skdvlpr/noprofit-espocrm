<?php

namespace Espo\Modules\NonprofitEspocrm\Jobs;

use Espo\Core\Job\Job as JobContract;
use Espo\Core\Job\Job\Data;
use Espo\Core\Utils\Log;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService;
use Espo\ORM\EntityManager;
use Throwable;

/**
 * One-shot queue: when availability is full and autoConfirmWhenFullyStaffed
 * is on → auto-assign THEN confirm (confirm emails to volunteers).
 * Also covered by ReconcileFullyStaffedPlans every 5 minutes.
 */
class AutoConfirmFullyStaffedPlan implements JobContract
{
    public function __construct(
        private EntityManager $entityManager,
        private ShiftPlanningService $shiftPlanningService,
        private Log $log,
    ) {}

    public function run(Data $data): void
    {
        $offerId = $data->get('offerId');

        if (!is_string($offerId) || $offerId === '') {
            return;
        }

        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer || !(bool) $offer->get('autoConfirmWhenFullyStaffed')) {
            return;
        }

        if (!(bool) $offer->get('isFullyStaffed')) {
            return;
        }

        $status = (string) ($offer->get('status') ?? '');

        if (!in_array($status, ['CollectingAvailability', 'Planned'], true)) {
            return;
        }

        try {
            $assign = $this->shiftPlanningService->autoAssign($offerId);

            if (($assign['uncovered'] ?? []) !== []) {
                $this->log->warning(
                    'Auto-confirm skipped for ActivityOffer {id}: uncovered shifts remain after auto-assign.',
                    ['id' => $offerId]
                );

                return;
            }

            $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

            if (
                $offer
                && in_array((string) $offer->get('status'), ['CollectingAvailability', 'Planned'], true)
            ) {
                $this->shiftPlanningService->confirm($offerId);
            }
        } catch (Throwable $e) {
            $this->log->warning(
                'Auto-confirm failed for ActivityOffer {id}: {message}',
                ['id' => $offerId, 'message' => $e->getMessage()]
            );
        }
    }
}
