<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use DateInterval;
use Espo\Core\Field\LinkParent;
use Espo\Core\Job\Job\Data;
use Espo\Core\Job\Job\Status as JobStatus;
use Espo\Core\Job\JobSchedulerFactory;
use Espo\Core\Name\Field;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Log;
use Espo\Entities\Job;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Jobs\AutoConfirmFullyStaffedPlan;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Throwable;

/**
 * Slot Published↔Covered flips + plan isFullyStaffed (by availability) + creator email.
 * Optional auto-confirm is queued as a system job (ACL-safe).
 */
class ShiftCoverageSyncService
{
    private const SLOT_PUBLISHED = 'Published';
    private const SLOT_COVERED = 'Covered';
    private const SLOT_COMPLETED = 'Completed';
    private const SLOT_CANCELLED = 'Cancelled';

    private const OFFER_CLOSED = 'Closed';
    private const OFFER_COMPLETED = 'Completed';

    /** People who marked availability (or were already staffed). */
    private const AVAILABILITY_STATUSES = ['Available', 'Assigned', 'Confirmed'];

    /** People counted toward Covered slot status. */
    private const STAFFED_STATUSES = ['Assigned', 'Confirmed'];

    public function __construct(
        private EntityManager $entityManager,
        private ShiftEmailService $shiftEmailService,
        private JobSchedulerFactory $jobSchedulerFactory,
        private Language $language,
        private User $user,
        private Log $log,
    ) {}

    public function sync(string $offerId): void
    {
        $staffable = 0;
        $availabilityCovered = 0;

        $coverageSaveOpts = [
            StatusGuard::SKIP_OPTION => true,
            ShiftChangeNotifyService::SKIP_SCHEDULE_NOTIFY => true,
            ShiftChangeNotifyService::SKIP_PLAN_UPDATE_NOTIFY => true,
            SaveOption::SILENT => true,
        ];

        foreach ($this->entityManager
            ->getRDBRepository('ActivityOfferSlot')
            ->where(['activityOfferId' => $offerId])
            ->find() as $slot
        ) {
            $required = (int) ($slot->get('requiredCount') ?? 1);
            // Available column = people who marked Disponibile (not yet assigned).
            $availableOnly = $this->countInvites($slot->getId(), ['Available']);
            $availableLike = $this->countInvites($slot->getId(), self::AVAILABILITY_STATUSES);
            $assignedLike = $this->countInvites($slot->getId(), self::STAFFED_STATUSES);

            $hasEnoughAvailability = $availableLike >= $required && $required > 0;
            $isStaffed = $assignedLike >= $required && $required > 0;
            $status = (string) ($slot->get('status') ?? self::SLOT_PUBLISHED);

            if ($status !== self::SLOT_CANCELLED) {
                $staffable++;

                if ($hasEnoughAvailability || $status === self::SLOT_COMPLETED) {
                    $availabilityCovered++;
                }
            }

            $countsChanged =
                (int) ($slot->get('availableCount') ?? 0) !== $availableOnly
                || (int) ($slot->get('assignedCount') ?? 0) !== $assignedLike;

            if ($countsChanged) {
                $slot->set('availableCount', $availableOnly);
                $slot->set('assignedCount', $assignedLike);
            }

            if ($status === self::SLOT_COMPLETED) {
                if ($countsChanged) {
                    $this->entityManager->saveEntity($slot, $coverageSaveOpts);
                }

                continue;
            }

            // Covered badge still means people were assigned/confirmed (unchanged).
            $statusChanged = false;

            if ($isStaffed && $status === self::SLOT_PUBLISHED) {
                $slot->set('status', self::SLOT_COVERED);
                $statusChanged = true;
            } elseif (!$isStaffed && $status === self::SLOT_COVERED) {
                $slot->set('status', self::SLOT_PUBLISHED);
                $statusChanged = true;
            }

            if ($countsChanged || $statusChanged) {
                $this->entityManager->saveEntity($slot, $coverageSaveOpts);
            }
        }

        $this->syncOfferFullStaffing(
            $offerId,
            $staffable > 0 && $staffable === $availabilityCovered
        );
    }

    /**
     * @param string[] $statuses
     */
    private function countInvites(string $slotId, array $statuses): int
    {
        $count = 0;

        foreach ($this->entityManager
            ->getRDBRepository('ActivityInvite')
            ->where([
                'activityOfferSlotId' => $slotId,
                'status' => $statuses,
            ])
            ->find() as $invite
        ) {
            $count++;
        }

        return $count;
    }

    private function syncOfferFullStaffing(string $offerId, bool $isFullyStaffed): void
    {
        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer) {
            return;
        }

        $saveOpts = [
            StatusGuard::SKIP_OPTION => true,
            ShiftChangeNotifyService::SKIP_SCHEDULE_NOTIFY => true,
            ShiftChangeNotifyService::SKIP_PLAN_UPDATE_NOTIFY => true,
            SaveOption::SILENT => true,
        ];

        $wasFullyStaffed = (bool) $offer->get('isFullyStaffed');
        $notifiedAt = $offer->get('fullyStaffedNotifiedAt');
        $changed = false;

        if ($wasFullyStaffed !== $isFullyStaffed) {
            $offer->set('isFullyStaffed', $isFullyStaffed);
            $changed = true;
        }

        if (!$isFullyStaffed && $notifiedAt !== null) {
            $offer->set('fullyStaffedNotifiedAt', null);
            $changed = true;
        }

        if ($changed) {
            $this->entityManager->saveEntity($offer, $saveOpts);
        }

        if (
            !$isFullyStaffed
            || $offer->get('fullyStaffedNotifiedAt') !== null
            || in_array(
                (string) $offer->get('status'),
                [self::OFFER_CLOSED, self::OFFER_COMPLETED],
                true
            )
        ) {
            return;
        }

        $this->notifyWeekFullyStaffed($offer);

        $offer->set('fullyStaffedNotifiedAt', date(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT));
        $this->entityManager->saveEntity($offer, $saveOpts);

        if ((bool) $offer->get('autoConfirmWhenFullyStaffed')) {
            $this->scheduleAutoConfirm($offerId);
        }
    }

    private function scheduleAutoConfirm(string $offerId): void
    {
        if ($this->hasPendingAutoConfirmJob($offerId)) {
            return;
        }

        try {
            $this->jobSchedulerFactory
                ->create()
                ->setClassName(AutoConfirmFullyStaffedPlan::class)
                ->setGroup('auto-confirm-' . $offerId)
                ->setDelay(new DateInterval('PT0S'))
                ->setData(
                    Data::create(['offerId' => $offerId])
                        ->withTargetId($offerId)
                        ->withTargetType('ActivityOffer')
                )
                ->schedule();
        } catch (Throwable $e) {
            $this->log->warning(
                'Shift auto-confirm job schedule failed offer={offerId}: {message}',
                ['offerId' => $offerId, 'message' => $e->getMessage()]
            );
        }
    }

    private function hasPendingAutoConfirmJob(string $offerId): bool
    {
        $pending = $this->entityManager
            ->getRDBRepository(Job::ENTITY_TYPE)
            ->where([
                'className' => AutoConfirmFullyStaffedPlan::class,
                'targetId' => $offerId,
                'targetType' => 'ActivityOffer',
                'status' => [
                    JobStatus::PENDING,
                    JobStatus::READY,
                    JobStatus::RUNNING,
                ],
            ])
            ->findOne();

        return $pending !== null;
    }

    private function notifyWeekFullyStaffed(Entity $offer): void
    {
        $creatorId = (string) ($offer->get('createdById') ?? '');
        $assignedId = (string) ($offer->get('assignedUserId') ?? '');
        $recipientIds = array_values(array_unique(array_filter([$creatorId, $assignedId])));

        if ($recipientIds === []) {
            return;
        }

        $message = $this->language->translateLabel(
            'weekFullyStaffedNotification',
            'messages',
            'ActivityOffer'
        );
        $message = str_replace(
            ['{name}', '{weekStart}'],
            [
                (string) $offer->get(Field::NAME),
                (string) ($offer->get('weekStart') ?? ''),
            ],
            $message
        );

        $emailed = false;

        foreach ($recipientIds as $userId) {
            if ($userId !== $this->user->getId()) {
                $notification = $this->entityManager
                    ->getRDBRepositoryByClass(Notification::class)
                    ->getNew();

                $notification
                    ->setType(Notification::TYPE_MESSAGE)
                    ->setUserId($userId)
                    ->setMessage($message)
                    ->setRelated(LinkParent::create('ActivityOffer', $offer->getId()));

                $this->entityManager->saveEntity($notification);
            }

            if ($emailed) {
                continue;
            }

            $emailResult = $this->shiftEmailService->sendWeekFullyStaffed($offer, $userId);

            if ((int) ($emailResult['sent'] ?? 0) > 0) {
                $emailed = true;
            }
        }
    }
}
