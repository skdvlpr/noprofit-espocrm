<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanning;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Field\LinkParent;
use Espo\Core\Name\Field;
use Espo\Core\Utils\Language;
use Espo\Entities\Notification;
use Espo\Entities\Team;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftEmailService;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftChangeNotifyService;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftCoverageSyncService;
use DateTimeImmutable;
use DateTimeZone;


class PlanLifecycleManager
{
    private const STATUS_DRAFT = 'Draft';
    private const STATUS_COLLECTING = 'CollectingAvailability';
    private const STATUS_PLANNED = 'Planned';
    private const STATUS_CONFIRMED = 'Confirmed';
    private const STATUS_UPDATED = 'Updated';
    private const STATUS_COMPLETED = 'Completed';
    private const STATUS_CLOSED = 'Closed';

    private const SLOT_PUBLISHED = 'Published';
    private const SLOT_COVERED = 'Covered';
    private const SLOT_COMPLETED = 'Completed';
    private const SLOT_CANCELLED = 'Cancelled';

    private const INVITE_AVAILABLE = 'Available';
    private const INVITE_ASSIGNED = 'Assigned';
    private const INVITE_CONFIRMED = 'Confirmed';
    private const INVITE_DECLINED = 'Declined';
    private const INVITE_CANCELLED = 'Cancelled';

    /** @var string[] */
    private const TERMINAL_SLOT_STATUSES = [
        self::SLOT_COMPLETED,
        self::SLOT_CANCELLED,
    ];

    private const DAY_OFFSET = [
        'Monday' => 0,
        'Tuesday' => 1,
        'Wednesday' => 2,
        'Thursday' => 3,
        'Friday' => 4,
        'Saturday' => 5,
        'Sunday' => 6,
    ];

    public function __construct(
        private ShiftPlanningSupport $support
    ) {}

    /**
     * Extend pending auto-send window (+5 minutes by default).
     *
     * @return array{status: string, pendingNotifyAt: ?string, pendingNotifyKind: ?string, extendedMinutes: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function extendPendingUpdate(string $offerId, int $minutes = 5): array
    {
        $this->support->getOfferForEdit($offerId);

        $result = $this->support->shiftChangeNotifyService()->extendPendingUpdate($offerId, $minutes);

        if (($result['extendedMinutes'] ?? 0) < 1) {
            throw new BadRequest("Only an Updated plan with a pending notify can be extended.");
        }

        return $result;
    }

    /**
     * Discard pending soft/hard update (do not send). Updated → Confirmed, no emails.
     *
     * @return array{status: string, discarded: bool}
     * @throws BadRequest|Forbidden|NotFound
     */

    /**
     * Discard pending soft/hard update (do not send). Updated → Confirmed, no emails.
     *
     * @return array{status: string, discarded: bool}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function discardPendingUpdate(string $offerId): array
    {
        $this->support->getOfferForEdit($offerId);

        $result = $this->support->shiftChangeNotifyService()->discardPendingUpdate($offerId);

        if (empty($result['discarded'])) {
            throw new BadRequest("Only an Updated plan with a pending notify can be discarded.");
        }

        return $result;
    }

    /**
     * Flush pending soft/hard update notify for an Updated plan (Send update now).
     *
     * @return array{
     *     kind: string,
     *     status: string,
     *     resetCount: int,
     *     emailSent: int,
     *     notifyCount: int
     * }
     * @throws BadRequest|Forbidden|NotFound
     */

    /**
     * Flush pending soft/hard update notify for an Updated plan (Send update now).
     *
     * @return array{
     *     kind: string,
     *     status: string,
     *     resetCount: int,
     *     emailSent: int,
     *     notifyCount: int
     * }
     * @throws BadRequest|Forbidden|NotFound
     */
    public function sendPendingUpdate(string $offerId): array
    {
        $offer = $this->support->getOfferForEdit($offerId);
        $status = (string) ($offer->get('status') ?? '');
        $kind = (string) ($offer->get('pendingNotifyKind') ?? '');

        // Debounce job may already have finalized while the UI was still open.
        if ($status !== self::STATUS_UPDATED || $kind === '') {
            if (in_array($status, [self::STATUS_CONFIRMED, self::STATUS_COLLECTING], true)) {
                return [
                    'kind' => $status === self::STATUS_COLLECTING
                        ? ShiftChangeNotifyService::KIND_HARD
                        : ShiftChangeNotifyService::KIND_SOFT,
                    'status' => $status,
                    'resetCount' => 0,
                    'emailSent' => 0,
                    'notifyCount' => 0,
                    'alreadySent' => true,
                ];
            }

            throw new BadRequest("Only an Updated plan can send a pending update.");
        }

        if (!in_array($kind, [
            ShiftChangeNotifyService::KIND_SOFT,
            ShiftChangeNotifyService::KIND_HARD,
        ], true)) {
            throw new BadRequest("No pending update to send.");
        }

        $result = $this->support->shiftChangeNotifyService()->sendPendingUpdateNow($offerId);
        $result['alreadySent'] = false;

        return $result;
    }

    /**
     * Confirm the plan: mark assigned invites Confirmed, notify volunteers.
     * Does NOT auto-create personal Tasks (those are manual / Workflow optional).
     *
     * @return array{taskCount: int, confirmedCount: int, notifyCount: int, emailCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */

    /**
     * Confirm the plan: mark assigned invites Confirmed, notify volunteers.
     * Does NOT auto-create personal Tasks (those are manual / Workflow optional).
     *
     * @return array{taskCount: int, confirmedCount: int, notifyCount: int, emailCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function confirm(string $offerId): array
    {
        $offer = $this->support->getOfferForEdit($offerId);

        if (!in_array($offer->get('status'), [self::STATUS_COLLECTING, self::STATUS_PLANNED], true)) {
            throw new BadRequest("Only an open plan can be confirmed.");
        }

        $invitesBySlot = [];

        foreach ($this->support->getInvites($offerId) as $invite) {
            $slotId = $invite->get('activityOfferSlotId');

            if ($slotId && in_array(
                $invite->get('status'),
                [self::INVITE_ASSIGNED, self::INVITE_CONFIRMED],
                true
            )) {
                $invitesBySlot[$slotId][] = $invite;
            }
        }

        if ($invitesBySlot === []) {
            throw new BadRequest("No assigned volunteers. Run auto-assignment first.");
        }

        $confirmedCount = 0;
        /** @var array<string, string[]> $shiftsByUser */
        $shiftsByUser = [];
        $adminDigestLines = [];

        foreach ($this->support->getSlots($offerId) as $slot) {
            $slotId = $slot->getId();
            $invites = $invitesBySlot[$slotId] ?? [];

            if ($invites === []) {
                continue;
            }

            $assigneeNames = [];

            foreach ($invites as $invite) {
                $userId = (string) $invite->get('userId');

                if ($invite->get('status') !== self::INVITE_CONFIRMED) {
                    $invite->set('status', self::INVITE_CONFIRMED);
                    $this->support->saveEntityAllowStatus($invite);
                }

                $confirmedCount++;

                $line = $this->support->shiftEmailService()->formatConfirmedShiftLine($slot);
                $shiftsByUser[$userId][] = $line;

                $userEntity = $this->support->entityManager()->getEntityById(User::ENTITY_TYPE, $userId);
                $assigneeNames[] = $userEntity ? (string) $userEntity->getName() : $userId;
            }

            $adminDigestLines[] = $this->support->shiftEmailService()->formatConfirmedShiftLine($slot)
                . ' → ' . implode(', ', $assigneeNames);
        }

        $offer->set('status', self::STATUS_CONFIRMED);
        $this->support->saveEntityAllowStatus($offer);
        $this->support->shiftChangeNotifyService()->clearPendingChangedSlots($offerId);

        $this->syncSlotCoverageStatuses($offerId);

        $notifyCount = 0;
        $emailCount = 0;

        foreach ($shiftsByUser as $userId => $shiftLines) {
            if ($userId === $this->support->user()->getId()) {
                continue;
            }

            $message = $this->support->translateMessage('shiftsConfirmedNotification', [
                'name' => (string) $offer->get(Field::NAME),
                'shifts' => implode('; ', $shiftLines),
            ]);

            $this->support->createNotification($offer, $userId, $message);
            $notifyCount++;

            $emailResult = $this->support->shiftEmailService()->sendShiftsConfirmed($offer, $userId, $shiftLines);
            $emailCount += (int) ($emailResult['sent'] ?? 0);
        }

        $adminId = (string) ($offer->get('assignedUserId') ?? '');

        if ($adminId !== '' && $adminDigestLines !== []) {
            $emailResult = $this->support->shiftEmailService()->sendAdminDigest($offer, $adminId, $adminDigestLines);
            $emailCount += (int) ($emailResult['sent'] ?? 0);
        }

        return [
            'taskCount' => 0,
            'confirmedCount' => $confirmedCount,
            'notifyCount' => $notifyCount,
            'emailCount' => $emailCount,
        ];
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @throws NotFound
     */

    /**
     * Keep slot status in sync with staffing:
     * Published → Covered when assignedCount >= requiredCount,
     * Covered → Published when staffing drops below required.
     * Then refresh plan-level isFullyStaffed and notify the week creator once.
     */
    public function syncSlotCoverageStatuses(string $offerId): void
    {
        $this->support->shiftCoverageSyncService()->sync($offerId);
    }

    /**
     * Shifts that can receive availability invites / auto-assignment (Published only).
     *
     * @return Entity[]
     */

    /**
     * Close a shift plan (system status transition; not editable via form).
     *
     * @return array{status: string}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function closePlan(string $offerId): array
    {
        $offer = $this->support->getOfferForEdit($offerId);
        $status = (string) ($offer->get('status') ?? '');

        if ($this->support->isPlanTerminal($status) || $status === self::STATUS_DRAFT) {
            throw new BadRequest("Plan can only be closed after availability collection has started.");
        }

        $offer->set('status', self::STATUS_CLOSED);
        $this->support->saveEntityAllowStatus($offer);

        return ['status' => self::STATUS_CLOSED];
    }

    /**
     * Annul one shift. Notifies Assigned/Confirmed only; then tries auto-close week.
     * Never changes Completed slots.
     *
     * @return array{status: string, notifyCount: int, emailCount: int, planClosed: bool, planStatus: ?string}
     * @throws BadRequest|Forbidden|NotFound
     */

    /**
     * Annul one shift. Notifies Assigned/Confirmed only; then tries auto-close week.
     * Never changes Completed slots.
     *
     * @return array{status: string, notifyCount: int, emailCount: int, planClosed: bool, planStatus: ?string}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function cancelSlot(string $slotId): array
    {
        $slot = $this->support->entityManager()->getEntityById('ActivityOfferSlot', $slotId);

        if (!$slot) {
            throw new NotFound("ActivityOfferSlot not found.");
        }

        $offerId = (string) ($slot->get('activityOfferId') ?? '');
        $offer = $this->support->getOfferForEdit($offerId);

        $status = (string) ($slot->get('status') ?? '');

        if (in_array($status, self::TERMINAL_SLOT_STATUSES, true)) {
            throw new BadRequest("Shift is already completed or cancelled.");
        }

        $staffedUserIds = $this->support->getStaffedInviteUserIds($slot);

        $slot->set('status', self::SLOT_CANCELLED);
        $this->support->saveEntityAllowStatus($slot);

        $this->support->cancelInvitesOnSlot($slot);

        $notify = $this->support->notifyUsersSlotCancellation(
            $offer,
            $staffedUserIds,
            [$this->support->shiftEmailService()->formatConfirmedShiftLine($slot)]
        );

        $planStatus = $this->tryAutoCloseOffer($offerId);
        $planClosed = $planStatus !== null;

        return [
            'status' => self::SLOT_CANCELLED,
            'notifyCount' => $notify['notifyCount'],
            'emailCount' => $notify['emailCount'],
            'planClosed' => $planClosed,
            'planStatus' => $planStatus,
        ];
    }

    /**
     * Annul every non-terminal shift and mark the week Closed (annulled).
     * Completed slots are never touched. One digest per Assigned/Confirmed user.
     *
     * @return array{status: string, cancelledCount: int, notifyCount: int, emailCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    /**
     * Annul remaining open shifts and close the plan.
     * Personal Tasks linked to the week/slots are intentionally left untouched.
     *
     * @return array{status: string, cancelledCount: int, notifyCount: int, emailCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    /**
     * Annul remaining open shifts and close the plan.
     * Personal Tasks linked to the week/slots are intentionally left untouched.
     *
     * @return array{status: string, cancelledCount: int, notifyCount: int, emailCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */

    /**
     * Annul remaining open shifts and close the plan.
     * Personal Tasks linked to the week/slots are intentionally left untouched.
     *
     * @return array{status: string, cancelledCount: int, notifyCount: int, emailCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function cancelAll(string $offerId): array
    {
        $offer = $this->support->getOfferForEdit($offerId);
        $status = (string) ($offer->get('status') ?? '');

        if ($status === self::STATUS_DRAFT || $this->support->isPlanTerminal($status)) {
            throw new BadRequest("Plan can only be cancelled after availability collection has started.");
        }

        /** @var array<string, string[]> $linesByUser */
        $linesByUser = [];
        $cancelledCount = 0;

        foreach ($this->support->getSlots($offerId) as $slot) {
            $slotStatus = (string) ($slot->get('status') ?? '');

            // Completed stays Completed — never reclassified as Cancelled.
            if (in_array($slotStatus, self::TERMINAL_SLOT_STATUSES, true)) {
                continue;
            }

            $staffedUserIds = $this->support->getStaffedInviteUserIds($slot);
            $line = $this->support->shiftEmailService()->formatConfirmedShiftLine($slot);

            $slot->set('status', self::SLOT_CANCELLED);
            $this->support->saveEntityAllowStatus($slot);
            $this->support->cancelInvitesOnSlot($slot);
            $cancelledCount++;

            foreach ($staffedUserIds as $userId) {
                $linesByUser[$userId][] = $line;
            }
        }

        $notifyCount = 0;
        $emailCount = 0;

        foreach ($linesByUser as $userId => $lines) {
            $result = $this->support->notifyUsersSlotCancellation($offer, [$userId], $lines);
            $notifyCount += $result['notifyCount'];
            $emailCount += $result['emailCount'];
        }

        // Explicit annul → Closed (even if some shifts were already Completed).
        $offer->set('status', self::STATUS_CLOSED);
        $this->support->saveEntityAllowStatus($offer);

        return [
            'status' => self::STATUS_CLOSED,
            'cancelledCount' => $cancelledCount,
            'notifyCount' => $notifyCount,
            'emailCount' => $emailCount,
        ];
    }

    /**
     * Before hard-delete: notify Assigned/Confirmed (no-op if none / already Cancelled).
     */

    /**
     * Before hard-delete: notify Assigned/Confirmed (no-op if none / already Cancelled).
     */
    public function notifyOnSlotDelete(Entity $slot): void
    {
        $status = (string) ($slot->get('status') ?? '');

        if ($status === self::SLOT_CANCELLED) {
            return;
        }

        $offerId = (string) ($slot->get('activityOfferId') ?? '');
        $offer = $offerId !== ''
            ? $this->support->entityManager()->getEntityById('ActivityOffer', $offerId)
            : null;

        if (!$offer) {
            return;
        }

        $staffedUserIds = $this->support->getStaffedInviteUserIds($slot);

        if ($staffedUserIds === []) {
            return;
        }

        $this->support->notifyUsersSlotCancellation(
            $offer,
            $staffedUserIds,
            [$this->support->shiftEmailService()->formatConfirmedShiftLine($slot)]
        );
    }

    /**
     * When every slot is terminal, set plan status:
     * - any Completed → Completed (natural finish; completed work is not “annulled”)
     * - all Cancelled → Closed (fully annulled week)
     *
     * No emails. Returns the new plan status, or null if unchanged.
     */
    /**
     * Flip shifts whose dateEnd is in the past to Completed.
     * Idempotent; does not notify volunteers.
     *
     * @return int Number of slots updated
     */

    /**
     * Flip shifts whose dateEnd is in the past to Completed.
     * Idempotent; does not notify volunteers.
     *
     * @return int Number of slots updated
     */
    public function completePastSlots(?string $now = null): int
    {
        $now = $now ?? (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');

        $collection = $this->support->entityManager()
            ->getRDBRepository('ActivityOfferSlot')
            ->where([
                'status!=' => self::SLOT_COMPLETED,
                'dateEnd!=' => null,
                'dateEnd<' => $now,
            ])
            ->limit(0, 500)
            ->find();

        $updated = 0;

        foreach ($collection as $slot) {
            $status = (string) ($slot->get('status') ?? '');

            if ($status === self::SLOT_COMPLETED) {
                continue;
            }

            // Only active staffing statuses are auto-completed.
            if (!in_array($status, [self::SLOT_PUBLISHED, self::SLOT_COVERED, ''], true)) {
                continue;
            }

            $slot->set('status', self::SLOT_COMPLETED);
            $this->support->saveEntityAllowStatus($slot, [
                SaveOption::SILENT => true,
            ]);
            $updated++;
        }

        return $updated;
    }

    public function tryAutoCloseOffer(string $offerId): ?string
    {
        $offer = $this->support->entityManager()->getEntityById('ActivityOffer', $offerId);

        if (!$offer) {
            return null;
        }

        $offerStatus = (string) ($offer->get('status') ?? '');

        if ($offerStatus === self::STATUS_DRAFT || $this->support->isPlanTerminal($offerStatus)) {
            return null;
        }

        $slots = $this->support->getSlots($offerId);

        if ($slots === []) {
            return null;
        }

        $hasCompleted = false;
        $hasCancelled = false;

        foreach ($slots as $slot) {
            $status = (string) ($slot->get('status') ?? '');

            if (!in_array($status, self::TERMINAL_SLOT_STATUSES, true)) {
                return null;
            }

            if ($status === self::SLOT_COMPLETED) {
                $hasCompleted = true;
            }

            if ($status === self::SLOT_CANCELLED) {
                $hasCancelled = true;
            }
        }

        // Prefer Completed when any shift was actually worked/finished by time.
        $newStatus = $hasCompleted ? self::STATUS_COMPLETED : self::STATUS_CLOSED;

        if (!$hasCompleted && !$hasCancelled) {
            return null;
        }

        $offer->set('status', $newStatus);
        $this->support->saveEntityAllowStatus($offer, [
            SaveOption::SILENT => true,
        ]);

        return $newStatus;
    }
}

