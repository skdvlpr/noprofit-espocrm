<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Field\DateTime as FieldDateTime;
use Espo\Core\Field\LinkParent;
use Espo\Core\Job\Job\Data;
use Espo\Core\Job\Job\Status as JobStatus;
use Espo\Core\Job\JobSchedulerFactory;
use Espo\Core\Name\Field;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Log;
use Espo\Entities\Job;
use Espo\Entities\Notification;
use Espo\Modules\NonprofitEspocrm\Jobs\NotifyPlanUpdated;
use Espo\Modules\NonprofitEspocrm\Jobs\NotifySlotScheduleChange;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Throwable;

/**
 * Debounced shift-plan change notifications (schedule re-request + soft planUpdated).
 *
 * Confirmed plans flip to Updated while a notify is pending; after send:
 * soft → Confirmed, hard (place/time) → CollectingAvailability.
 *
 * Invite statuses: there is no Pending enum — Available is the affirmative /
 * re-openable state. Declined / Cancelled are never touched.
 */
class ShiftChangeNotifyService
{
    public const SKIP_SCHEDULE_NOTIFY = 'safehouseSkipSlotScheduleNotify';
    public const SKIP_PLAN_UPDATE_NOTIFY = 'safehouseSkipPlanUpdateNotify';

    public const KIND_SOFT = 'soft';
    public const KIND_HARD = 'hard';

    private const OFFER_NOTIFY_STATUSES = [
        'CollectingAvailability',
        'Planned',
        'Confirmed',
        'Updated',
    ];

    /** Statuses that enter the Updated / pending-notify UI flow. */
    private const OFFER_PENDING_UI_STATUSES = ['Confirmed', 'Updated'];

    private const PLACE_TIME_FIELDS = [
        'dateStart',
        'dateEnd',
        'placeStreet',
        'placeCity',
        'placeState',
        'placeCountry',
        'placePostalCode',
    ];

    private const IMPORTANT_SLOT_FIELDS = [
        'conditions',
        'category',
        'requiredCount',
    ];

    private const IMPORTANT_OFFER_FIELDS = [
        'description',
    ];

    private const INVITE_RESET_STATUSES = ['Available', 'Assigned', 'Confirmed'];

    private const DEBOUNCE_INTERVAL = 'PT10M';
    private const EXTEND_MINUTES = 5;

    public function __construct(
        private EntityManager $entityManager,
        private JobSchedulerFactory $jobSchedulerFactory,
        private ShiftEmailService $shiftEmailService,
        private ShiftCoverageSyncService $shiftCoverageSyncService,
        private Language $language,
        private Log $log,
    ) {}

    /**
     * @return list<string>
     */
    public static function placeTimeFields(): array
    {
        return self::PLACE_TIME_FIELDS;
    }

    /**
     * @return list<string>
     */
    public static function importantSlotFields(): array
    {
        return self::IMPORTANT_SLOT_FIELDS;
    }

    /**
     * @return list<string>
     */
    public static function importantOfferFields(): array
    {
        return self::IMPORTANT_OFFER_FIELDS;
    }

    /**
     * True when a schedule-change job is already pending for this slot.
     * Used so soft planUpdated notify does not double-fire after place/time queue.
     */
    public function wasScheduleChangeQueuedForSlot(string $slotId): bool
    {
        if ($slotId === '') {
            return false;
        }

        return $this->hasPendingJob(NotifySlotScheduleChange::class, $slotId, null);
    }

    /**
     * Queue a 10-minute-debounced schedule re-request for one slot (idempotent pending job).
     */
    public function queueScheduleChangeNotify(string $slotId): bool
    {
        if ($slotId === '') {
            return false;
        }

        $slot = $this->entityManager->getEntityById('ActivityOfferSlot', $slotId);
        $offerId = $slot ? (string) ($slot->get('activityOfferId') ?? '') : '';

        if ($offerId !== '') {
            $this->markPendingUpdate($offerId, self::KIND_HARD);
        }

        if ($this->hasPendingJob(NotifySlotScheduleChange::class, $slotId, null)) {
            return false;
        }

        try {
            $this->jobSchedulerFactory
                ->create()
                ->setClassName(NotifySlotScheduleChange::class)
                ->setGroup('slot-schedule-' . $slotId)
                ->setDelay(new DateInterval(self::DEBOUNCE_INTERVAL))
                ->setData(
                    Data::create(['slotId' => $slotId])
                        ->withTargetId($slotId)
                        ->withTargetType('ActivityOfferSlot')
                )
                ->schedule();

            return true;
        } catch (Throwable $e) {
            $this->log->warning(
                'Shift schedule-change job schedule failed for slot {slotId}: {message}',
                ['slotId' => $slotId, 'message' => $e->getMessage()]
            );

            return false;
        }
    }

    /**
     * @param list<string> $changedFields English field names
     */
    public function queuePlanUpdatedNotify(string $offerId, array $changedFields, ?string $slotId = null): int
    {
        if ($offerId === '' || $changedFields === []) {
            return 0;
        }

        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer || !$this->offerAllowsNotify($offer)) {
            return 0;
        }

        // Hard pending already supersedes soft notify for Confirmed/Updated.
        if ((string) ($offer->get('pendingNotifyKind') ?? '') === self::KIND_HARD) {
            return 0;
        }

        $userIds = $this->resolveRecipientUserIds($offerId, $slotId);

        if ($userIds === []) {
            return 0;
        }

        $this->markPendingUpdate($offerId, self::KIND_SOFT);

        $labels = $this->fieldLabels($changedFields, $slotId ? 'ActivityOfferSlot' : 'ActivityOffer');
        $queued = 0;

        foreach ($userIds as $userId) {
            if ($this->wasPlanUpdatedSentRecently($offerId, $userId)) {
                continue;
            }

            $group = 'plan-upd-' . $offerId . '-' . $userId;

            if ($this->hasPendingJob(NotifyPlanUpdated::class, $offerId, $userId)) {
                $this->mergePendingPlanUpdatedFields($offerId, $userId, $labels);
                continue;
            }

            try {
                $this->jobSchedulerFactory
                    ->create()
                    ->setClassName(NotifyPlanUpdated::class)
                    ->setGroup($group)
                    ->setDelay(new DateInterval(self::DEBOUNCE_INTERVAL))
                    ->setData(
                        Data::create([
                            'offerId' => $offerId,
                            'userId' => $userId,
                            'changedLabels' => $labels,
                        ])
                            ->withTargetId($offerId)
                            ->withTargetType('ActivityOffer')
                    )
                    ->schedule();
                $queued++;
            } catch (Throwable $e) {
                $this->log->warning(
                    'Shift plan-updated job schedule failed offer={offerId} user={userId}: {message}',
                    [
                        'offerId' => $offerId,
                        'userId' => $userId,
                        'message' => $e->getMessage(),
                    ]
                );
            }
        }

        return $queued;
    }

    /**
     * Flip Confirmed → Updated and store pending notify metadata for the planner banner.
     */
    public function markPendingUpdate(string $offerId, string $kind): void
    {
        if ($offerId === '' || !in_array($kind, [self::KIND_SOFT, self::KIND_HARD], true)) {
            return;
        }

        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer) {
            return;
        }

        $status = (string) ($offer->get('status') ?? '');

        if (!in_array($status, self::OFFER_PENDING_UI_STATUSES, true)) {
            return;
        }

        $existing = (string) ($offer->get('pendingNotifyKind') ?? '');
        $newKind = ($kind === self::KIND_HARD || $existing === self::KIND_HARD)
            ? self::KIND_HARD
            : self::KIND_SOFT;

        if ($newKind === self::KIND_HARD && $existing !== self::KIND_HARD) {
            $this->cancelPendingJobs(NotifyPlanUpdated::class, $offerId);
        }

        $at = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->add(new DateInterval(self::DEBOUNCE_INTERVAL))
            ->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);

        $offer->set('pendingNotifyKind', $newKind);
        $offer->set('pendingNotifyAt', $at);

        if ($status === 'Confirmed') {
            $offer->set('status', 'Updated');
        }

        $this->saveOfferSilent($offer);
        $this->bumpPendingJobsExecuteTime($offerId, $at);
    }

    /**
     * Push pending auto-send further (default +5 minutes). UI countdown + job executeTime.
     *
     * @return array{status: string, pendingNotifyAt: ?string, pendingNotifyKind: ?string, extendedMinutes: int}
     */
    public function extendPendingUpdate(string $offerId, int $minutes = self::EXTEND_MINUTES): array
    {
        $empty = [
            'status' => '',
            'pendingNotifyAt' => null,
            'pendingNotifyKind' => null,
            'extendedMinutes' => 0,
        ];

        if ($offerId === '' || $minutes < 1 || $minutes > 60) {
            return $empty;
        }

        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer) {
            return $empty;
        }

        $status = (string) ($offer->get('status') ?? '');
        $kind = (string) ($offer->get('pendingNotifyKind') ?? '');

        if ($status !== 'Updated' || !in_array($kind, [self::KIND_SOFT, self::KIND_HARD], true)) {
            return array_merge($empty, [
                'status' => $status,
                'pendingNotifyKind' => $kind !== '' ? $kind : null,
            ]);
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $currentAtRaw = (string) ($offer->get('pendingNotifyAt') ?? '');
        $base = $now;

        if ($currentAtRaw !== '') {
            try {
                $parsed = new DateTimeImmutable($currentAtRaw, new DateTimeZone('UTC'));

                if ($parsed > $now) {
                    $base = $parsed;
                }
            } catch (Throwable) {
                $base = $now;
            }
        }

        $at = $base
            ->add(new DateInterval('PT' . $minutes . 'M'))
            ->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);

        $offer->set('pendingNotifyAt', $at);
        $this->saveOfferSilent($offer);
        $this->bumpPendingJobsExecuteTime($offerId, $at);

        return [
            'status' => 'Updated',
            'pendingNotifyAt' => $at,
            'pendingNotifyKind' => $kind,
            'extendedMinutes' => $minutes,
        ];
    }

    /**
     * Immediate flush of pending soft/hard notify (UI "Send update now").
     *
     * @return array{
     *     kind: string,
     *     status: string,
     *     resetCount: int,
     *     emailSent: int,
     *     notifyCount: int
     * }
     */
    public function sendPendingUpdateNow(string $offerId): array
    {
        $empty = [
            'kind' => '',
            'status' => '',
            'resetCount' => 0,
            'emailSent' => 0,
            'notifyCount' => 0,
        ];

        if ($offerId === '') {
            return $empty;
        }

        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer) {
            return $empty;
        }

        $kind = (string) ($offer->get('pendingNotifyKind') ?? '');
        $status = (string) ($offer->get('status') ?? '');

        if ($status !== 'Updated' || !in_array($kind, [self::KIND_SOFT, self::KIND_HARD], true)) {
            return array_merge($empty, ['status' => $status, 'kind' => $kind]);
        }

        $resetCount = 0;
        $emailSent = 0;
        $notifyCount = 0;

        if ($kind === self::KIND_HARD) {
            $this->cancelPendingJobs(NotifyPlanUpdated::class, $offerId);

            foreach ($this->getOfferSlots($offerId) as $slot) {
                $this->cancelPendingJobs(NotifySlotScheduleChange::class, (string) $slot->getId());
                $result = $this->processScheduleChange((string) $slot->getId(), true);
                $resetCount += (int) ($result['resetCount'] ?? 0);
                $emailSent += (int) ($result['emailSent'] ?? 0);
                $notifyCount += (int) ($result['notifyCount'] ?? 0);
            }

            $this->applyPostNotifyStatus($offerId, 'CollectingAvailability');
        } else {
            $jobs = $this->entityManager
                ->getRDBRepository(Job::ENTITY_TYPE)
                ->where([
                    'className' => NotifyPlanUpdated::class,
                    'status' => [JobStatus::PENDING, JobStatus::READY],
                    'targetId' => $offerId,
                ])
                ->limit(80)
                ->find();

            foreach ($jobs as $job) {
                $raw = $job->get('data');
                $data = $raw ? json_decode(json_encode($raw), true) : [];

                if (!is_array($data)) {
                    $data = [];
                }

                $userId = (string) ($data['userId'] ?? '');
                $labels = $data['changedLabels'] ?? [];

                if (!is_array($labels)) {
                    $labels = [];
                }

                $labels = array_values(array_filter(array_map(
                    static fn ($v): string => trim((string) $v),
                    $labels
                ), static fn (string $v): bool => $v !== ''));

                if ($userId !== '' && $labels !== []) {
                    $result = $this->processPlanUpdated($offerId, $userId, $labels, true);
                    $emailSent += (int) ($result['emailSent'] ?? 0);

                    if (!empty($result['notified'])) {
                        $notifyCount++;
                    }
                }

                $job->set('status', JobStatus::SUCCESS);
                $job->set('executedAt', date('Y-m-d H:i:s'));
                $this->entityManager->saveEntity($job, ['skipAll' => true, 'silent' => true]);
            }

            $this->applyPostNotifyStatus($offerId, 'Confirmed');
        }

        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        return [
            'kind' => $kind,
            'status' => $offer ? (string) ($offer->get('status') ?? '') : '',
            'resetCount' => $resetCount,
            'emailSent' => $emailSent,
            'notifyCount' => $notifyCount,
        ];
    }

    /**
     * Process place/time change: reset invites + availability email/notification.
     *
     * @return array{resetCount: int, emailSent: int, notifyCount: int, skipped: bool}
     */
    public function processScheduleChange(string $slotId, bool $skipFinalize = false): array
    {
        $empty = ['resetCount' => 0, 'emailSent' => 0, 'notifyCount' => 0, 'skipped' => true];

        $slot = $this->entityManager->getEntityById('ActivityOfferSlot', $slotId);

        if (!$slot) {
            return $empty;
        }

        $offerId = (string) ($slot->get('activityOfferId') ?? '');
        $offer = $offerId !== ''
            ? $this->entityManager->getEntityById('ActivityOffer', $offerId)
            : null;

        if (!$offer || !$this->offerAllowsNotify($offer)) {
            return $empty;
        }

        $userIds = [];
        $resetCount = 0;

        foreach ($this->getSlotInvites($slotId) as $invite) {
            $status = (string) ($invite->get('status') ?? '');

            if (!in_array($status, self::INVITE_RESET_STATUSES, true)) {
                continue;
            }

            $userId = (string) ($invite->get('userId') ?? '');

            if ($userId === '') {
                continue;
            }

            $userIds[] = $userId;

            if (in_array($status, ['Assigned', 'Confirmed'], true)) {
                $this->removeTaskCollaborator($invite);
                $invite->set('status', 'Available');
                $invite->set('respondedAt', null);
                $invite->set('taskId', null);
                $this->saveInviteAllowStatus($invite);
                $resetCount++;
            } elseif ($status === 'Available') {
                // Force re-answer: drop affirmative response so the grid is unchecked.
                $this->entityManager->removeEntity($invite, [
                    StatusGuard::SKIP_OPTION => true,
                    self::SKIP_SCHEDULE_NOTIFY => true,
                    self::SKIP_PLAN_UPDATE_NOTIFY => true,
                ]);
                $resetCount++;
            }
        }

        $userIds = array_values(array_unique($userIds));

        if ($userIds === []) {
            if (!$skipFinalize) {
                $this->maybeFinalizeAfterScheduleChange($offerId);
            }

            return [
                'resetCount' => $resetCount,
                'emailSent' => 0,
                'notifyCount' => 0,
                'skipped' => false,
            ];
        }

        $message = $this->translateOfferMessage('scheduleChangeNotification', [
            'name' => (string) ($offer->get(Field::NAME) ?? ''),
            'weekStart' => (string) ($offer->get('weekStart') ?? ''),
            'slot' => (string) ($slot->get(Field::NAME) ?? ''),
        ]);

        $notifyCount = 0;

        foreach ($userIds as $userId) {
            $this->createOfferNotification($offer, $userId, $message);
            $notifyCount++;
        }

        $emailResult = $this->shiftEmailService->sendAvailabilityRequest($offer, $userIds);

        $this->syncSlotCoverageStatusesInline($offerId);

        if (!$skipFinalize) {
            $this->maybeFinalizeAfterScheduleChange($offerId);
        }

        return [
            'resetCount' => $resetCount,
            'emailSent' => (int) ($emailResult['sent'] ?? 0),
            'notifyCount' => $notifyCount,
            'skipped' => false,
        ];
    }

    /**
     * @param list<string> $changedLabels
     * @return array{emailSent: int, notified: bool, skipped: bool}
     */
    public function processPlanUpdated(
        string $offerId,
        string $userId,
        array $changedLabels,
        bool $force = false
    ): array {
        $empty = ['emailSent' => 0, 'notified' => false, 'skipped' => true];

        if ($offerId === '' || $userId === '' || $changedLabels === []) {
            return $empty;
        }

        if (!$force && $this->wasPlanUpdatedSentRecently($offerId, $userId)) {
            return $empty;
        }

        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer || !$this->offerAllowsNotify($offer)) {
            return $empty;
        }

        $message = $this->translateOfferMessage('planUpdatedNotification', [
            'name' => (string) ($offer->get(Field::NAME) ?? ''),
            'changes' => implode(', ', $changedLabels),
        ]);

        $this->createOfferNotification($offer, $userId, $message);

        $emailResult = $this->shiftEmailService->sendPlanUpdated($offer, [$userId], $changedLabels);

        if (!$force) {
            $this->maybeFinalizeAfterPlanUpdated($offerId);
        }

        return [
            'emailSent' => (int) ($emailResult['sent'] ?? 0),
            'notified' => true,
            'skipped' => false,
        ];
    }

    public function offerAllowsNotify(Entity $offer): bool
    {
        $status = (string) ($offer->get('status') ?? '');

        return in_array($status, self::OFFER_NOTIFY_STATUSES, true);
    }

    private function maybeFinalizeAfterScheduleChange(string $offerId): void
    {
        if ($offerId === '' || $this->hasPendingScheduleJobsForOffer($offerId)) {
            return;
        }

        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer) {
            return;
        }

        if ((string) ($offer->get('status') ?? '') !== 'Updated') {
            return;
        }

        if ((string) ($offer->get('pendingNotifyKind') ?? '') !== self::KIND_HARD) {
            return;
        }

        $this->applyPostNotifyStatus($offerId, 'CollectingAvailability');
    }

    private function maybeFinalizeAfterPlanUpdated(string $offerId): void
    {
        if ($offerId === '') {
            return;
        }

        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer) {
            return;
        }

        if ((string) ($offer->get('status') ?? '') !== 'Updated') {
            return;
        }

        if ((string) ($offer->get('pendingNotifyKind') ?? '') !== self::KIND_SOFT) {
            return;
        }

        if ($this->hasPendingJob(NotifyPlanUpdated::class, $offerId, null)) {
            return;
        }

        $this->applyPostNotifyStatus($offerId, 'Confirmed');
    }

    private function applyPostNotifyStatus(string $offerId, string $status): void
    {
        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer) {
            return;
        }

        $offer->set('status', $status);
        $offer->set('pendingNotifyKind', null);
        $offer->set('pendingNotifyAt', null);
        $this->saveOfferSilent($offer);
    }

    private function saveOfferSilent(Entity $offer): void
    {
        $this->entityManager->saveEntity($offer, [
            StatusGuard::SKIP_OPTION => true,
            self::SKIP_SCHEDULE_NOTIFY => true,
            self::SKIP_PLAN_UPDATE_NOTIFY => true,
            'silent' => true,
        ]);
    }

    /**
     * @return Entity[]
     */
    private function getOfferSlots(string $offerId): array
    {
        $collection = $this->entityManager
            ->getRDBRepository('ActivityOfferSlot')
            ->where(['activityOfferId' => $offerId])
            ->find();

        return iterator_to_array($collection);
    }

    private function hasPendingScheduleJobsForOffer(string $offerId): bool
    {
        foreach ($this->getOfferSlots($offerId) as $slot) {
            if ($this->hasPendingJob(NotifySlotScheduleChange::class, (string) $slot->getId(), null)) {
                return true;
            }
        }

        return false;
    }

    private function cancelPendingJobs(string $className, string $targetId): void
    {
        if ($targetId === '') {
            return;
        }

        $jobs = $this->entityManager
            ->getRDBRepository(Job::ENTITY_TYPE)
            ->where([
                'className' => $className,
                'status' => [JobStatus::PENDING, JobStatus::READY],
                'targetId' => $targetId,
            ])
            ->limit(80)
            ->find();

        foreach ($jobs as $job) {
            $job->set('status', JobStatus::FAILED);
            $job->set('message', 'Cancelled by shift pending-update flush');
            $this->entityManager->saveEntity($job, ['skipAll' => true, 'silent' => true]);
        }
    }

    /**
     * Keep pending Job.executeTime aligned with ActivityOffer.pendingNotifyAt.
     */
    private function bumpPendingJobsExecuteTime(string $offerId, string $executeAt): void
    {
        if ($offerId === '' || $executeAt === '') {
            return;
        }

        try {
            $fieldTime = FieldDateTime::fromString($executeAt);
        } catch (Throwable) {
            return;
        }

        $this->bumpJobsExecuteTime(NotifyPlanUpdated::class, $offerId, $fieldTime);

        foreach ($this->getOfferSlots($offerId) as $slot) {
            $this->bumpJobsExecuteTime(
                NotifySlotScheduleChange::class,
                (string) $slot->getId(),
                $fieldTime
            );
        }
    }

    private function bumpJobsExecuteTime(string $className, string $targetId, FieldDateTime $executeAt): void
    {
        if ($targetId === '') {
            return;
        }

        $jobs = $this->entityManager
            ->getRDBRepository(Job::ENTITY_TYPE)
            ->where([
                'className' => $className,
                'status' => [JobStatus::PENDING, JobStatus::READY],
                'targetId' => $targetId,
            ])
            ->limit(80)
            ->find();

        foreach ($jobs as $job) {
            /** @var Job $job */
            $job->setExecuteTime($executeAt);
            $this->entityManager->saveEntity($job, ['skipAll' => true, 'silent' => true]);
        }
    }

    /**
     * @return Entity[]
     */
    private function getSlotInvites(string $slotId): array
    {
        $collection = $this->entityManager
            ->getRDBRepository('ActivityInvite')
            ->where(['activityOfferSlotId' => $slotId])
            ->find();

        return iterator_to_array($collection);
    }

    /**
     * Recipients: Available / Assigned / Confirmed on the slot (or all such on the offer).
     *
     * @return list<string>
     */
    private function resolveRecipientUserIds(string $offerId, ?string $slotId): array
    {
        $where = [
            'activityOfferId' => $offerId,
            'status' => self::INVITE_RESET_STATUSES,
        ];

        if ($slotId) {
            $where['activityOfferSlotId'] = $slotId;
        }

        $ids = [];

        foreach ($this->entityManager->getRDBRepository('ActivityInvite')->where($where)->find() as $invite) {
            $userId = (string) ($invite->get('userId') ?? '');

            if ($userId !== '') {
                $ids[$userId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param list<string> $fields
     * @return list<string>
     */
    private function fieldLabels(array $fields, string $scope): array
    {
        $labels = [];

        foreach ($fields as $field) {
            $label = $this->language->translate($field, 'fields', $scope);

            if (!is_string($label) || $label === '' || $label === $field) {
                $label = $field;
            }

            $labels[] = $label;
        }

        return array_values(array_unique($labels));
    }

    private function hasPendingJob(string $className, string $targetId, ?string $userId): bool
    {
        $jobs = $this->entityManager
            ->getRDBRepository(Job::ENTITY_TYPE)
            ->where([
                'className' => $className,
                'status' => [JobStatus::PENDING, JobStatus::READY],
                'targetId' => $targetId,
            ])
            ->limit(40)
            ->find();

        foreach ($jobs as $job) {
            if ($userId === null) {
                return true;
            }

            $data = $job->get('data');
            $data = $data ? json_decode(json_encode($data), true) : [];

            if (is_array($data) && ($data['userId'] ?? null) === $userId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $labels
     */
    private function mergePendingPlanUpdatedFields(string $offerId, string $userId, array $labels): void
    {
        $jobs = $this->entityManager
            ->getRDBRepository(Job::ENTITY_TYPE)
            ->where([
                'className' => NotifyPlanUpdated::class,
                'status' => [JobStatus::PENDING, JobStatus::READY],
                'targetId' => $offerId,
            ])
            ->limit(40)
            ->find();

        foreach ($jobs as $job) {
            $raw = $job->get('data');
            $data = $raw ? json_decode(json_encode($raw), true) : [];

            if (!is_array($data) || ($data['userId'] ?? null) !== $userId) {
                continue;
            }

            $existing = $data['changedLabels'] ?? [];

            if (!is_array($existing)) {
                $existing = [];
            }

            $merged = array_values(array_unique(array_merge(
                array_map('strval', $existing),
                $labels
            )));

            $data['changedLabels'] = $merged;
            $job->set('data', $data);
            $this->entityManager->saveEntity($job, ['skipAll' => true, 'silent' => true]);

            return;
        }
    }

    private function wasPlanUpdatedSentRecently(string $offerId, string $userId): bool
    {
        $since = (new DateTimeImmutable('now'))->sub(new DateInterval('PT15M'))
            ->format('Y-m-d H:i:s');

        $jobs = $this->entityManager
            ->getRDBRepository(Job::ENTITY_TYPE)
            ->where([
                'className' => NotifyPlanUpdated::class,
                'status' => JobStatus::SUCCESS,
                'targetId' => $offerId,
                'executedAt>=' => $since,
            ])
            ->limit(40)
            ->find();

        foreach ($jobs as $job) {
            $raw = $job->get('data');
            $data = $raw ? json_decode(json_encode($raw), true) : [];

            if (is_array($data) && ($data['userId'] ?? null) === $userId) {
                return true;
            }
        }

        return false;
    }

    private function saveInviteAllowStatus(Entity $invite): void
    {
        $this->entityManager->saveEntity($invite, [
            StatusGuard::SKIP_OPTION => true,
            self::SKIP_SCHEDULE_NOTIFY => true,
            self::SKIP_PLAN_UPDATE_NOTIFY => true,
        ]);
    }

    private function removeTaskCollaborator(Entity $invite): void
    {
        $taskId = (string) ($invite->get('taskId') ?? '');
        $userId = (string) ($invite->get('userId') ?? '');

        if ($taskId === '' || $userId === '') {
            $slotId = (string) ($invite->get('activityOfferSlotId') ?? '');
            $slot = $slotId !== ''
                ? $this->entityManager->getEntityById('ActivityOfferSlot', $slotId)
                : null;
            $taskId = $slot ? (string) ($slot->get('taskId') ?? '') : '';
        }

        if ($taskId === '' || $userId === '') {
            return;
        }

        $task = $this->entityManager->getEntityById('Task', $taskId);

        if (!$task) {
            return;
        }

        $task->loadLinkMultipleField(Field::COLLABORATORS);
        $ids = $task->getLinkMultipleIdList(Field::COLLABORATORS);
        $filtered = array_values(array_filter($ids, static fn (string $id): bool => $id !== $userId));

        if ($filtered === $ids) {
            return;
        }

        $task->set(Field::COLLABORATORS . 'Ids', $filtered);
        $this->entityManager->saveEntity($task, [
            self::SKIP_SCHEDULE_NOTIFY => true,
            self::SKIP_PLAN_UPDATE_NOTIFY => true,
        ]);
    }

    private function createOfferNotification(Entity $offer, string $userId, string $message): void
    {
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

    /**
     * @param array<string, string> $params
     */
    private function translateOfferMessage(string $key, array $params): string
    {
        $message = $this->language->translateLabel($key, 'messages', 'ActivityOffer');

        foreach ($params as $name => $value) {
            $message = str_replace('{' . $name . '}', $value, $message);
        }

        return $message;
    }

    private function syncSlotCoverageStatusesInline(string $offerId): void
    {
        try {
            $this->shiftCoverageSyncService->sync($offerId);
        } catch (Throwable $e) {
            $this->log->warning(
                'Shift coverage sync after schedule change failed: {message}',
                ['message' => $e->getMessage()]
            );
        }
    }
}
