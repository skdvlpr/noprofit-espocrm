<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Core\Acl;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanning\AvailabilityWorkflow;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanning\PlanLifecycleManager;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanning\WeekSlotSynchronizer;

/**
 * Weekly shift planning lifecycle facade.
 *
 * Implementation lives in Tools\ShiftPlanning\* collaborators.
 * Public method signatures stay stable for Controllers / Hooks / Jobs.
 */
class ShiftPlanningService
{
    private WeekSlotSynchronizer $weekSlots;
    private AvailabilityWorkflow $availability;
    private PlanLifecycleManager $planLifecycle;

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user,
        private Language $language,
        private ShiftEmailService $shiftEmailService,
        private ShiftChangeNotifyService $shiftChangeNotifyService,
        private ShiftCoverageSyncService $shiftCoverageSyncService,
    ) {
        // Collaborators must share this instance's User/Acl so createWith(['user' => …])
        // (volunteer-as-actor smokes/controllers) reaches saveAvailability / grid filters.
        $support = new \Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanning\ShiftPlanningSupport(
            $entityManager,
            $acl,
            $user,
            $language,
            $shiftEmailService,
            $shiftChangeNotifyService,
            $shiftCoverageSyncService,
        );

        $this->weekSlots = new WeekSlotSynchronizer($support);
        $this->planLifecycle = new PlanLifecycleManager($support);
        $this->availability = new AvailabilityWorkflow($support, $this->planLifecycle);
    }

    /**
     * Append a batch of shifts (1–7) without removing existing ones.
     * Used by the Turni relationship “+” mass-create modal.
     *
     * @param array<int, mixed> $rows
     * @return array{slotCount: int, createdCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function addWeekSlots(string $offerId, array $rows, array $batchOptions = []): array
    {
        return $this->weekSlots->addWeekSlots($offerId, $rows, $batchOptions);
    }
    /**
     * @param array<int, mixed> $rows
     * @param array<string, mixed> $batchOptions uniqueAddress + place* for the batch
     * @return array{slotCount: int, createdCount?: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function syncWeekSlots(
        string $offerId,
        array $rows,
        bool $append = false,
        array $batchOptions = []
    ): array
    {
        return $this->weekSlots->syncWeekSlots($offerId, $rows, $append, $batchOptions);
    }
    /**
     * Move slot dateStart/dateEnd onto the calendar day implied by dayOfWeek
     * within the parent plan weekStart, keeping clock times (or all-day bounds).
     */
    public function syncSlotDatesFromDayOfWeek(Entity $slot): void
    {
        $this->weekSlots->syncSlotDatesFromDayOfWeek($slot);
    }
    /**
     * Derive English dayOfWeek enum from dateStart (keeps Giorno in sync when Inizio is edited).
     */
    public function syncSlotDayOfWeekFromDateStart(Entity $slot): void
    {
        $this->weekSlots->syncSlotDayOfWeekFromDateStart($slot);
    }
    /**
     * Open / re-open availability collection and notify involved volunteers.
     *
     * Draft → first open (cohort).
     * With pending changed slots / Updated / Confirmed re-request → hard re-collect
     * (clears Available+Assigned/Confirmed on those slots only) → CollectingAvailability.
     * Collecting/Planned without pending edits → re-send emails only (no invite wipe).
     *
     * @return array{notifyCount: int, slotCount: int, cohortCount: int, emailCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function requestAvailability(string $offerId): array
    {
        return $this->availability->requestAvailability($offerId);
    }
    /**
     * Re-send availability pack to selected users for selected shifts only.
     * Does not wipe invites, does not change plan status, does not notify the rest of the cohort.
     *
     * @param string[] $userIds
     * @param string[]|null $slotIds null/empty = all resendable shifts; otherwise intersection with plan
     * @return array{
     *     userCount: int,
     *     notifyCount: int,
     *     slotCount: int,
     *     emailCount: int,
     *     emailSkipped: string[],
     *     emailFailed: string[]
     * }
     * @throws BadRequest|Forbidden|NotFound
     */
    public function requestAvailabilityForUsers(
        string $offerId,
        array $userIds,
        ?array $slotIds = null
    ): array {
        return $this->availability->requestAvailabilityForUsers($offerId, $userIds, $slotIds);
    }
    /**
     * Volunteer-facing grid data.
     *
     * @return array<string, mixed>
     * @throws Forbidden|NotFound
     */
    public function availabilityGrid(string $offerId): array
    {
        return $this->availability->availabilityGrid($offerId);
    }
    /**
     * Upsert the current user's availability (checked slot ids) + optional comment.
     *
     * @param string[] $slotIds
     * @return array{availableCount: int, withdrawnCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function saveAvailability(string $offerId, array $slotIds, ?string $comment = null): array
    {
        return $this->availability->saveAvailability($offerId, $slotIds, $comment);
    }
    /**
     * Organizer coverage table.
     *
     * @return array<string, mixed>
     * @throws Forbidden|NotFound
     */
    public function coverage(string $offerId): array
    {
        return $this->availability->coverage($offerId);
    }
    /**
     * Staffing for one shift: assigned (Assigned+Confirmed) and candidates (Available).
     *
     * @return array{
     *     slotId: string,
     *     activityOfferId: string,
     *     requiredCount: int,
     *     assigned: list<array{id: string, name: string, inviteId: string, status: string}>,
     *     candidates: list<array{id: string, name: string, inviteId: string, status: string}>,
     *     canResend: bool
     * }
     * @throws Forbidden|NotFound
     */
    public function slotStaffing(string $slotId): array
    {
        return $this->availability->slotStaffing($slotId);
    }
    /**
     * Re-send availability request (email + in-app) to one Available candidate.
     *
     * @return array{emailSent: int, notified: bool, userId: string}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function resendSlotInvite(string $slotId, string $userId): array
    {
        return $this->availability->resendSlotInvite($slotId, $userId);
    }
    /**
     * Cohort volunteer analysis for organizers: competenze match, responses,
     * assignments, and uncovered shifts each person could still fill.
     *
     * @return array<string, mixed>
     * @throws Forbidden|NotFound
     */
    public function volunteerStats(string $offerId): array
    {
        return $this->availability->volunteerStats($offerId);
    }
    /**
     * Greedy fair auto-assignment.
     *
     * @return array{assignedCount: int, uncovered: string[]}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function autoAssign(string $offerId): array
    {
        return $this->availability->autoAssign($offerId);
    }
    /**
     * Extend pending auto-send window (+5 minutes by default).
     *
     * @return array{status: string, pendingNotifyAt: ?string, pendingNotifyKind: ?string, extendedMinutes: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function extendPendingUpdate(string $offerId, int $minutes = 5): array
    {
        return $this->planLifecycle->extendPendingUpdate($offerId, $minutes);
    }
    /**
     * Discard pending soft/hard update (do not send). Updated → Confirmed, no emails.
     *
     * @return array{status: string, discarded: bool}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function discardPendingUpdate(string $offerId): array
    {
        return $this->planLifecycle->discardPendingUpdate($offerId);
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
    public function sendPendingUpdate(string $offerId): array
    {
        return $this->planLifecycle->sendPendingUpdate($offerId);
    }
    /**
     * Confirm the plan: mark assigned invites Confirmed, notify volunteers.
     * Does NOT auto-create personal Tasks (those are manual / Workflow optional).
     *
     * @return array{taskCount: int, confirmedCount: int, notifyCount: int, emailCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function confirm(string $offerId): array
    {
        return $this->planLifecycle->confirm($offerId);
    }
    /**
     * Keep slot status in sync with staffing:
     * Published → Covered when assignedCount >= requiredCount,
     * Covered → Published when staffing drops below required.
     * Then refresh plan-level isFullyStaffed and notify the week creator once.
     */
    public function syncSlotCoverageStatuses(string $offerId): void
    {
        $this->planLifecycle->syncSlotCoverageStatuses($offerId);
    }
    /**
     * Close a shift plan (system status transition; not editable via form).
     *
     * @return array{status: string}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function closePlan(string $offerId): array
    {
        return $this->planLifecycle->closePlan($offerId);
    }
    /**
     * Annul one shift. Notifies Assigned/Confirmed only; then tries auto-close week.
     * Never changes Completed slots.
     *
     * @return array{status: string, notifyCount: int, emailCount: int, planClosed: bool, planStatus: ?string}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function cancelSlot(string $slotId): array
    {
        return $this->planLifecycle->cancelSlot($slotId);
    }
    /**
     * Annul remaining open shifts and close the plan.
     * Personal Tasks linked to the week/slots are intentionally left untouched.
     *
     * @return array{status: string, cancelledCount: int, notifyCount: int, emailCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function cancelAll(string $offerId): array
    {
        return $this->planLifecycle->cancelAll($offerId);
    }
    /**
     * Before hard-delete: notify Assigned/Confirmed (no-op if none / already Cancelled).
     */
    public function notifyOnSlotDelete(Entity $slot): void
    {
        $this->planLifecycle->notifyOnSlotDelete($slot);
    }
    /**
     * Flip shifts whose dateEnd is in the past to Completed.
     * Idempotent; does not notify volunteers.
     *
     * @return int Number of slots updated
     */
    public function completePastSlots(?string $now = null): int
    {
        return $this->planLifecycle->completePastSlots($now);
    }
    public function tryAutoCloseOffer(string $offerId): ?string
    {
        return $this->planLifecycle->tryAutoCloseOffer($offerId);
    }
}

