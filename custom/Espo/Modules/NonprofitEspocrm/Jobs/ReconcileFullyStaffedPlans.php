<?php

namespace Espo\Modules\NonprofitEspocrm\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Core\Utils\Log;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftCoverageSyncService;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService;
use Espo\ORM\EntityManager;
use Throwable;

/**
 * Every 5 minutes: recompute availability coverage for open week plans,
 * notify creators when enough people marked availability, and when
 * autoConfirmWhenFullyStaffed is on run auto-assign + confirm.
 */
class ReconcileFullyStaffedPlans implements JobDataLess
{
    public function __construct(
        private EntityManager $entityManager,
        private ShiftCoverageSyncService $shiftCoverageSyncService,
        private ShiftPlanningService $shiftPlanningService,
        private Log $log,
    ) {}

    public function run(): void
    {
        $offers = $this->entityManager
            ->getRDBRepository('ActivityOffer')
            ->where([
                'status' => ['CollectingAvailability', 'Planned'],
            ])
            ->find();

        foreach ($offers as $offer) {
            $offerId = $offer->getId();

            if (!$offerId) {
                continue;
            }

            try {
                $this->shiftCoverageSyncService->sync($offerId);
            } catch (Throwable $e) {
                $this->log->warning(
                    'Reconcile fully-staffed sync failed offer={id}: {message}',
                    ['id' => $offerId, 'message' => $e->getMessage()]
                );

                continue;
            }

            $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

            if (
                !$offer
                || !(bool) $offer->get('autoConfirmWhenFullyStaffed')
                || !(bool) $offer->get('isFullyStaffed')
            ) {
                continue;
            }

            $status = (string) ($offer->get('status') ?? '');

            if (!in_array($status, ['CollectingAvailability', 'Planned'], true)) {
                continue;
            }

            try {
                $assign = $this->shiftPlanningService->autoAssign($offerId);

                if (($assign['uncovered'] ?? []) !== []) {
                    $this->log->warning(
                        'Reconcile auto-confirm skipped offer={id}: uncovered after auto-assign.',
                        ['id' => $offerId]
                    );

                    continue;
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
                    'Reconcile auto-assign/confirm failed offer={id}: {message}',
                    ['id' => $offerId, 'message' => $e->getMessage()]
                );
            }
        }
    }
}
