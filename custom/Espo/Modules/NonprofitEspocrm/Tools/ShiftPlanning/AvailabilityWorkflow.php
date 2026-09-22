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


class AvailabilityWorkflow
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
        private ShiftPlanningSupport $support,
        private PlanLifecycleManager $planLifecycle
    ) {}

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
        $offer = $this->support->getOfferForEdit($offerId);

        $status = (string) $offer->get('status');

        if (!in_array($status, [
            self::STATUS_DRAFT,
            self::STATUS_COLLECTING,
            self::STATUS_PLANNED,
            self::STATUS_CONFIRMED,
            self::STATUS_UPDATED,
        ], true)) {
            throw new BadRequest(
                "Availability can be requested for open plans (not Closed/Completed)."
            );
        }

        $slots = $this->support->getRespondableSlots($offerId);

        if ($slots === []) {
            throw new BadRequest(
                "Publish at least one open shift (Published or Covered) before requesting availability."
            );
        }

        $changedOnly = $this->support->shiftChangeNotifyService()->getPendingChangedSlotIds($offerId);
        $needsHardRecollect = $changedOnly !== []
            || in_array($status, [self::STATUS_UPDATED, self::STATUS_CONFIRMED], true);

        if ($needsHardRecollect && $this->support->resolveInvolvedUserIds($offerId) !== []) {
            // Only changed slots when we know them; otherwise all published (full re-request).
            $hard = $this->support->shiftChangeNotifyService()->hardRecollectAvailability(
                $offerId,
                $changedOnly !== [] ? $changedOnly : null
            );

            $involvedIds = $this->support->resolveInvolvedUserIds($offerId);

            return [
                'cohortCount' => count($involvedIds),
                'notifyCount' => (int) ($hard['notifyCount'] ?? 0),
                'slotCount' => (int) ($hard['slotCount'] ?? count($slots)),
                'emailCount' => (int) ($hard['emailSent'] ?? 0),
                'emailSkipped' => [],
                'emailFailed' => [],
                'kind' => ShiftChangeNotifyService::KIND_HARD,
            ];
        }

        $cohortIds = $this->support->resolveCohortUserIds($offer);

        if ($cohortIds === []) {
            // Fall back to already-involved users when cohort links are empty.
            $cohortIds = $this->support->resolveInvolvedUserIds($offerId);
        }

        if ($cohortIds === []) {
            throw new BadRequest("Select volunteers and/or teams before requesting availability.");
        }

        if ($status === self::STATUS_DRAFT) {
            $offer->set('status', self::STATUS_COLLECTING);
            $offer->set('publishedAt', $this->support->nowString());
            $this->support->saveEntityAllowStatus($offer);
        }

        $message = $this->support->translateMessage('availabilityRequestNotification', [
            'name' => (string) $offer->get(Field::NAME),
            'weekStart' => (string) ($offer->get('weekStart') ?? ''),
        ]);

        $notifyCount = $this->support->notifyUsers($offer, $cohortIds, $message);

        $emailResult = $this->support->shiftEmailService()->sendAvailabilityRequest($offer, $cohortIds);

        return [
            'cohortCount' => count($cohortIds),
            'notifyCount' => $notifyCount,
            'slotCount' => count($slots),
            'emailCount' => (int) ($emailResult['sent'] ?? 0),
            'emailSkipped' => $emailResult['skipped'] ?? [],
            'emailFailed' => $emailResult['failed'] ?? [],
        ];
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
        $offer = $this->support->getOfferForEdit($offerId);

        $status = (string) $offer->get('status');

        if (!in_array($status, [
            self::STATUS_COLLECTING,
            self::STATUS_PLANNED,
            self::STATUS_CONFIRMED,
            self::STATUS_UPDATED,
        ], true)) {
            throw new BadRequest(
                "Selective resend is available after the plan is open (not Draft/Closed/Completed)."
            );
        }

        $resendable = $this->support->getResendableSlots($offerId);

        if ($resendable === []) {
            throw new BadRequest("No open shifts to include in the availability pack.");
        }

        $resendableMap = [];

        foreach ($resendable as $slot) {
            $resendableMap[$slot->getId()] = $slot;
        }

        $selectedSlotIds = [];

        if (is_array($slotIds) && $slotIds !== []) {
            foreach ($slotIds as $slotId) {
                if (!is_string($slotId) || $slotId === '') {
                    continue;
                }

                if (!isset($resendableMap[$slotId])) {
                    throw new BadRequest("Shift is not part of this open plan: " . $slotId);
                }

                $selectedSlotIds[$slotId] = true;
            }

            $selectedSlotIds = array_keys($selectedSlotIds);
        }
        else {
            $selectedSlotIds = array_keys($resendableMap);
        }

        if ($selectedSlotIds === []) {
            throw new BadRequest("Select at least one shift.");
        }

        $normalized = [];

        foreach ($userIds as $userId) {
            if (!is_string($userId) || $userId === '') {
                continue;
            }

            $normalized[$userId] = true;
        }

        $selectedIds = array_keys($normalized);

        if ($selectedIds === []) {
            throw new BadRequest("Select at least one volunteer.");
        }

        $allowedIds = array_values(array_unique(array_merge(
            $this->support->resolveCohortUserIds($offer),
            $this->support->resolveInvolvedUserIds($offerId)
        )));

        if ($allowedIds === []) {
            throw new BadRequest("No volunteers involved in this plan.");
        }

        $allowedMap = array_fill_keys($allowedIds, true);
        $rejected = [];

        foreach ($selectedIds as $userId) {
            if (!isset($allowedMap[$userId])) {
                $rejected[] = $userId;
            }
        }

        if ($rejected !== []) {
            throw new BadRequest(
                "Selected users are not in this plan's cohort: " . implode(', ', $rejected)
            );
        }

        $message = $this->support->translateMessage('availabilityRequestNotification', [
            'name' => (string) $offer->get(Field::NAME),
            'weekStart' => (string) ($offer->get('weekStart') ?? ''),
        ]);

        $notifyCount = $this->support->notifyUsers($offer, $selectedIds, $message);
        $emailResult = $this->support->shiftEmailService()->sendAvailabilityRequest(
            $offer,
            $selectedIds,
            $selectedSlotIds
        );

        return [
            'userCount' => count($selectedIds),
            'notifyCount' => $notifyCount,
            'slotCount' => count($selectedSlotIds),
            'emailCount' => (int) ($emailResult['sent'] ?? 0),
            'emailSkipped' => $emailResult['skipped'] ?? [],
            'emailFailed' => $emailResult['failed'] ?? [],
        ];
    }

    /**
     * Volunteer-facing grid data.
     *
     * @return array<string, mixed>
     * @throws Forbidden|NotFound
     */

    /**
     * Volunteer-facing grid data.
     *
     * @return array<string, mixed>
     * @throws Forbidden|NotFound
     */
    public function availabilityGrid(string $offerId): array
    {
        $offer = $this->support->getOffer($offerId);

        $cohortIds = $this->support->resolveCohortUserIds($offer);
        $isMember = in_array($this->support->user()->getId(), $cohortIds, true);

        if (!$isMember && !$this->support->acl()->check($offer, Acl\Table::ACTION_READ)) {
            throw new Forbidden("No access to this shift plan.");
        }

        $competences = $this->support->getUserCompetences($this->support->user());

        $myInvites = [];

        foreach ($this->support->getInvites($offerId, $this->support->user()->getId()) as $invite) {
            $slotId = $invite->get('activityOfferSlotId');

            if ($slotId) {
                $myInvites[$slotId] = [
                    'status' => $invite->get('status'),
                    'comment' => (string) ($invite->get('comment') ?? ''),
                ];
            }
        }

        $changedIds = array_fill_keys(
            $this->support->shiftChangeNotifyService()->getPendingChangedSlotIds($offerId),
            true
        );

        $slotList = [];

        foreach ($this->support->getSlots($offerId) as $slot) {
            $slotStatus = (string) ($slot->get('status') ?? 'Published');

            if (!in_array($slotStatus, ['Published', 'Covered'], true)) {
                continue;
            }

            $category = (string) ($slot->get('category') ?? '');
            $slotId = (string) $slot->getId();
            $myInvite = $myInvites[$slotId] ?? null;
            $myStatus = is_array($myInvite) ? ($myInvite['status'] ?? null) : $myInvite;
            $isChanged = isset($changedIds[$slotId]);

            $slotList[] = [
                'id' => $slotId,
                'name' => $slot->get(Field::NAME),
                'category' => $category,
                'categoryLabel' => $this->support->language()
                    ->translateOption($category, 'category', 'ActivityOfferSlot'),
                'dateStart' => $slot->get('dateStart'),
                'dateEnd' => $slot->get('dateEnd'),
                'requiredCount' => (int) ($slot->get('requiredCount') ?? 1),
                'placeStreet' => (string) ($slot->get('placeStreet') ?? ''),
                'placeCity' => (string) ($slot->get('placeCity') ?? ''),
                'placeLabel' => $this->support->formatPlaceLabel($slot),
                'conditions' => $this->support->normalizeConditions($slot->get('conditions') ?? []),
                'myStatus' => $myStatus,
                'myComment' => is_array($myInvite) ? ($myInvite['comment'] ?? '') : '',
                'allowed' => $competences === [] || in_array($category, $competences, true),
                'changed' => $isChanged,
            ];
        }

        usort($slotList, static function (array $a, array $b): int {
            $aChanged = !empty($a['changed']) ? 0 : 1;
            $bChanged = !empty($b['changed']) ? 0 : 1;

            if ($aChanged !== $bChanged) {
                return $aChanged <=> $bChanged;
            }

            return strcmp((string) ($a['dateStart'] ?? ''), (string) ($b['dateStart'] ?? ''));
        });

        return [
            'id' => $offer->getId(),
            'name' => $offer->get(Field::NAME),
            'weekStart' => $offer->get('weekStart'),
            'status' => $offer->get('status'),
            'description' => $offer->get('description'),
            'placeLabel' => $this->support->formatPlaceLabel($offer),
            'canRespond' => in_array(
                $offer->get('status'),
                [self::STATUS_COLLECTING, self::STATUS_PLANNED],
                true
            ),
            'changedSlotIds' => array_keys($changedIds),
            'comment' => $this->support->getUserPlanComment($offerId, $this->support->user()->getId()),
            'slots' => $slotList,
        ];
    }

    /**
     * Upsert the current user's availability (checked slot ids) + optional comment.
     *
     * @param string[] $slotIds
     * @return array{availableCount: int, withdrawnCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */

    /**
     * Upsert the current user's availability (checked slot ids) + optional comment.
     *
     * @param string[] $slotIds
     * @return array{availableCount: int, withdrawnCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function saveAvailability(string $offerId, array $slotIds, ?string $comment = null): array
    {
        $offer = $this->support->getOffer($offerId);

        if (!in_array($offer->get('status'), [self::STATUS_COLLECTING, self::STATUS_PLANNED], true)) {
            throw new BadRequest("Availability collection is not open for this plan.");
        }

        $cohortIds = $this->support->resolveCohortUserIds($offer);

        if (!in_array($this->support->user()->getId(), $cohortIds, true) && !$this->support->user()->isAdmin()) {
            throw new Forbidden("You are not part of this shift plan.");
        }

        $competences = $this->support->getUserCompetences($this->support->user());
        $checked = array_fill_keys(array_filter($slotIds, 'is_string'), true);
        $commentValue = $comment !== null ? trim($comment) : null;

        if ($commentValue !== null && mb_strlen($commentValue) > 2000) {
            throw new BadRequest("Comment is too long.");
        }

        $invitesBySlot = [];

        foreach ($this->support->getInvites($offerId, $this->support->user()->getId()) as $invite) {
            $slotId = $invite->get('activityOfferSlotId');

            if ($slotId) {
                $invitesBySlot[$slotId] = $invite;
            }
        }

        $availableCount = 0;
        $withdrawnCount = 0;

        foreach ($this->support->getRespondableSlots($offerId) as $slot) {
            $slotId = $slot->getId();
            $category = (string) ($slot->get('category') ?? '');
            $isChecked = isset($checked[$slotId]);
            $invite = $invitesBySlot[$slotId] ?? null;

            if ($isChecked) {
                if ($competences !== [] && !in_array($category, $competences, true)) {
                    continue;
                }

                if (!$invite) {
                    $new = $this->support->entityManager()->getNewEntity('ActivityInvite');
                    $new->set([
                        'name' => trim(($slot->get(Field::NAME) ?? '') . ' · ' . $this->support->user()->getName()),
                        'userId' => $this->support->user()->getId(),
                        'activityOfferId' => $offerId,
                        'activityOfferSlotId' => $slotId,
                        'status' => self::INVITE_AVAILABLE,
                        'respondedAt' => $this->support->nowString(),
                        'comment' => $commentValue ?? '',
                    ]);

                    $this->support->saveEntityAllowStatus($new);
                    $availableCount++;

                    continue;
                }

                if (in_array($invite->get('status'), [self::INVITE_DECLINED, self::INVITE_CANCELLED], true)) {
                    $invite->set('status', self::INVITE_AVAILABLE);
                    $invite->set('respondedAt', $this->support->nowString());
                }

                if ($commentValue !== null) {
                    $invite->set('comment', $commentValue);
                }

                $this->support->saveEntityAllowStatus($invite);
                $availableCount++;

                continue;
            }

            if (!$invite) {
                continue;
            }

            if ($invite->get('status') === self::INVITE_AVAILABLE) {
                $this->support->entityManager()->removeEntity($invite);

                continue;
            }

            if (in_array($invite->get('status'), [self::INVITE_ASSIGNED, self::INVITE_CONFIRMED], true)) {
                $invite->set('status', self::INVITE_DECLINED);
                $invite->set('respondedAt', $this->support->nowString());

                if ($commentValue !== null) {
                    $invite->set('comment', $commentValue);
                }

                $this->support->saveEntityAllowStatus($invite);

                $this->support->syncTaskCollaborator($invite, false);
                $withdrawnCount++;
            }
        }

        // Always recompute plan-level "enough availability" after the grid save.
        // Relying only on ActivityInvite AfterSave misses unchanged-status saves
        // and removeEntity withdraws.
        $this->planLifecycle->syncSlotCoverageStatuses($offerId);

        return [
            'availableCount' => $availableCount,
            'withdrawnCount' => $withdrawnCount,
        ];
    }

    /**
     * Organizer coverage table.
     *
     * @return array<string, mixed>
     * @throws Forbidden|NotFound
     */

    /**
     * Organizer coverage table.
     *
     * @return array<string, mixed>
     * @throws Forbidden|NotFound
     */
    public function coverage(string $offerId): array
    {
        $offer = $this->support->getOffer($offerId);

        if (!$this->support->acl()->check($offer, Acl\Table::ACTION_READ)) {
            throw new Forbidden("No read access to ActivityOffer.");
        }

        $invitesBySlot = [];

        foreach ($this->support->getInvites($offerId) as $invite) {
            $slotId = $invite->get('activityOfferSlotId');

            if ($slotId) {
                $invitesBySlot[$slotId][] = $invite;
            }
        }

        $userNames = $this->support->loadUserNames($offerId);

        $slotList = [];
        $uncoveredCount = 0;

        foreach ($this->support->getSlots($offerId) as $slot) {
            $slotId = $slot->getId();
            $byStatus = [
                self::INVITE_AVAILABLE => [],
                self::INVITE_ASSIGNED => [],
                self::INVITE_CONFIRMED => [],
                self::INVITE_DECLINED => [],
            ];

            foreach ($invitesBySlot[$slotId] ?? [] as $invite) {
                $status = (string) $invite->get('status');

                if (!isset($byStatus[$status])) {
                    continue;
                }

                $userId = (string) $invite->get('userId');

                $byStatus[$status][] = [
                    'id' => $userId,
                    'name' => $userNames[$userId] ?? $userId,
                    'inviteId' => $invite->getId(),
                ];
            }

            $required = (int) ($slot->get('requiredCount') ?? 1);
            $assignedTotal = count($byStatus[self::INVITE_ASSIGNED]) +
                count($byStatus[self::INVITE_CONFIRMED]);

            if ($assignedTotal < $required) {
                $uncoveredCount++;
            }

            $slotList[] = [
                'id' => $slotId,
                'name' => $slot->get(Field::NAME),
                'status' => (string) ($slot->get('status') ?? ''),
                'category' => $slot->get('category'),
                'categoryLabel' => $this->support->language()->translateOption(
                    (string) ($slot->get('category') ?? ''),
                    'category',
                    'ActivityOfferSlot'
                ),
                'dateStart' => $slot->get('dateStart'),
                'dateEnd' => $slot->get('dateEnd'),
                'requiredCount' => $required,
                'availableCount' => count($byStatus[self::INVITE_AVAILABLE]),
                'assignedCount' => $assignedTotal,
                'isCovered' => $assignedTotal >= $required,
                'placeLabel' => $this->support->formatPlaceLabel($slot),
                'conditions' => $this->support->normalizeConditions($slot->get('conditions') ?? []),
                'available' => $byStatus[self::INVITE_AVAILABLE],
                'assigned' => array_merge(
                    $byStatus[self::INVITE_ASSIGNED],
                    $byStatus[self::INVITE_CONFIRMED]
                ),
                'declined' => $byStatus[self::INVITE_DECLINED],
            ];
        }

        return [
            'status' => $offer->get('status'),
            'slots' => $slotList,
            'uncoveredCount' => $uncoveredCount,
        ];
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
        $slot = $this->support->entityManager()->getEntityById('ActivityOfferSlot', $slotId);

        if (!$slot) {
            throw new NotFound('ActivityOfferSlot not found.');
        }

        if (!$this->support->acl()->check($slot, Acl\Table::ACTION_READ)) {
            throw new Forbidden('No read access to ActivityOfferSlot.');
        }

        $offerId = (string) ($slot->get('activityOfferId') ?? '');

        if ($offerId === '') {
            throw new BadRequest('Shift is not linked to a plan.');
        }

        $offer = $this->support->getOffer($offerId);
        $canResend = $this->support->acl()->check($offer, Acl\Table::ACTION_EDIT)
            || $this->support->acl()->check($slot, Acl\Table::ACTION_EDIT);

        $userNames = $this->support->loadUserNames($offerId);
        $assigned = [];
        $candidates = [];

        foreach ($this->support->getInvites($offerId) as $invite) {
            if ((string) $invite->get('activityOfferSlotId') !== $slotId) {
                continue;
            }

            $status = (string) $invite->get('status');
            $userId = (string) $invite->get('userId');
            $row = [
                'id' => $userId,
                'name' => $userNames[$userId] ?? $userId,
                'inviteId' => (string) $invite->getId(),
                'status' => $status,
            ];

            if (in_array($status, [self::INVITE_ASSIGNED, self::INVITE_CONFIRMED], true)) {
                $assigned[] = $row;
            } elseif ($status === self::INVITE_AVAILABLE) {
                $candidates[] = $row;
            }
        }

        return [
            'slotId' => $slotId,
            'activityOfferId' => $offerId,
            'requiredCount' => (int) ($slot->get('requiredCount') ?? 1),
            'assigned' => $assigned,
            'candidates' => $candidates,
            'canResend' => $canResend,
        ];
    }

    /**
     * Re-send availability request (email + in-app) to one Available candidate.
     *
     * @return array{emailSent: int, notified: bool, userId: string}
     * @throws BadRequest|Forbidden|NotFound
     */

    /**
     * Re-send availability request (email + in-app) to one Available candidate.
     *
     * @return array{emailSent: int, notified: bool, userId: string}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function resendSlotInvite(string $slotId, string $userId): array
    {
        $slot = $this->support->entityManager()->getEntityById('ActivityOfferSlot', $slotId);

        if (!$slot) {
            throw new NotFound('ActivityOfferSlot not found.');
        }

        $offerId = (string) ($slot->get('activityOfferId') ?? '');

        if ($offerId === '') {
            throw new BadRequest('Shift is not linked to a plan.');
        }

        $offer = $this->support->getOfferForEdit($offerId);

        $invite = null;

        foreach ($this->support->getInvites($offerId, $userId) as $row) {
            if ((string) $row->get('activityOfferSlotId') === $slotId) {
                $invite = $row;
                break;
            }
        }

        if (!$invite) {
            throw new BadRequest('No availability for this volunteer on this shift.');
        }

        if ((string) $invite->get('status') !== self::INVITE_AVAILABLE) {
            throw new BadRequest('Resend is only available for candidates who marked themselves available.');
        }

        $message = $this->support->translateMessage('availabilityRequestNotification', [
            'name' => (string) $offer->get(Field::NAME),
            'weekStart' => (string) ($offer->get('weekStart') ?? ''),
        ]);

        $notified = false;

        if ($userId !== $this->support->user()->getId()) {
            $this->support->createNotification($offer, $userId, $message);
            $notified = true;
        }

        $emailResult = $this->support->shiftEmailService()->sendAvailabilityRequest($offer, [$userId]);

        return [
            'emailSent' => (int) ($emailResult['sent'] ?? 0),
            'notified' => $notified,
            'userId' => $userId,
        ];
    }

    /**
     * Cohort volunteer analysis for organizers: competenze match, responses,
     * assignments, and uncovered shifts each person could still fill.
     *
     * @return array<string, mixed>
     * @throws Forbidden|NotFound
     */

    /**
     * Cohort volunteer analysis for organizers: competenze match, responses,
     * assignments, and uncovered shifts each person could still fill.
     *
     * @return array<string, mixed>
     * @throws Forbidden|NotFound
     */
    public function volunteerStats(string $offerId): array
    {
        $offer = $this->support->getOffer($offerId);

        if (!$this->support->acl()->check($offer, Acl\Table::ACTION_READ)) {
            throw new Forbidden("No read access to ActivityOffer.");
        }

        $cohortIds = $this->support->resolveCohortUserIds($offer);
        $slots = $this->support->getSlots($offerId);
        $userNames = $this->support->loadUserNames($offerId);

        $invitesByUser = [];

        foreach ($this->support->getInvites($offerId) as $invite) {
            $userId = (string) $invite->get('userId');
            $invitesByUser[$userId][] = $invite;
        }

        $slotMeta = [];
        $uncoveredSlotIds = [];

        foreach ($slots as $slot) {
            $slotId = $slot->getId();
            $required = (int) ($slot->get('requiredCount') ?? 1);
            $assigned = 0;

            foreach ($invitesByUser as $userInvites) {
                foreach ($userInvites as $invite) {
                    if ((string) $invite->get('activityOfferSlotId') !== $slotId) {
                        continue;
                    }

                    if (in_array(
                        (string) $invite->get('status'),
                        [self::INVITE_ASSIGNED, self::INVITE_CONFIRMED],
                        true
                    )) {
                        $assigned++;
                    }
                }
            }

            $isCovered = $assigned >= $required;

            if (!$isCovered) {
                $uncoveredSlotIds[$slotId] = true;
            }

            $category = (string) ($slot->get('category') ?? '');

            $slotMeta[$slotId] = [
                'id' => $slotId,
                'name' => (string) ($slot->get(Field::NAME) ?? ''),
                'category' => $category,
                'categoryLabel' => $this->support->language()->translateOption(
                    $category,
                    'category',
                    'ActivityOfferSlot'
                ),
                'isCovered' => $isCovered,
            ];
        }

        $volunteers = [];
        $respondedPeople = 0;
        $assignedPeople = 0;

        foreach ($cohortIds as $userId) {
            /** @var ?User $user */
            $user = $this->support->entityManager()->getEntityById(User::ENTITY_TYPE, $userId);

            if (!$user) {
                continue;
            }

            $competences = $this->support->getUserCompetences($user);
            $competenceLabels = array_map(
                fn (string $code): string => $this->support->language()->translateOption(
                    $code,
                    'category',
                    'ActivityOfferSlot'
                ),
                $competences
            );

            $eligibleSlots = [];

            foreach ($slotMeta as $meta) {
                $allowed = $competences === []
                    || in_array($meta['category'], $competences, true);

                if (!$allowed) {
                    continue;
                }

                $eligibleSlots[] = [
                    'id' => $meta['id'],
                    'name' => $meta['categoryLabel'] !== ''
                        ? $meta['categoryLabel']
                        : $meta['name'],
                    'isCovered' => $meta['isCovered'],
                ];
            }

            $availableCount = 0;
            $assignedCount = 0;
            $declinedCount = 0;
            $responded = false;

            foreach ($invitesByUser[$userId] ?? [] as $invite) {
                $status = (string) $invite->get('status');

                if ($status === self::INVITE_CANCELLED) {
                    continue;
                }

                $responded = true;

                if ($status === self::INVITE_AVAILABLE) {
                    $availableCount++;
                } elseif (in_array($status, [self::INVITE_ASSIGNED, self::INVITE_CONFIRMED], true)) {
                    $assignedCount++;
                } elseif ($status === self::INVITE_DECLINED) {
                    $declinedCount++;
                }
            }

            if ($responded) {
                $respondedPeople++;
            }

            if ($assignedCount > 0) {
                $assignedPeople++;
            }

            $fillableGaps = 0;

            foreach ($eligibleSlots as $eligible) {
                if (!$eligible['isCovered']) {
                    $fillableGaps++;
                }
            }

            $eligibleCount = count($eligibleSlots);
            $matchLabel = $eligibleCount === 0
                ? '0%'
                : (string) (int) round(100 * $assignedCount / max(1, $eligibleCount)) . '%';

            $volunteers[] = [
                'id' => $userId,
                'name' => $userNames[$userId] ?? $user->get(Field::NAME) ?? $userId,
                'competences' => $competences,
                'competenceLabels' => $competenceLabels,
                'eligibleSlots' => $eligibleSlots,
                'responded' => $responded,
                'availableCount' => $availableCount,
                'assignedCount' => $assignedCount,
                'declinedCount' => $declinedCount,
                'fillableGaps' => $fillableGaps,
                'matchLabel' => $matchLabel,
            ];
        }

        usort(
            $volunteers,
            static function (array $a, array $b): int {
                return ($b['fillableGaps'] <=> $a['fillableGaps'])
                    ?: ($b['assignedCount'] <=> $a['assignedCount'])
                    ?: strcasecmp((string) $a['name'], (string) $b['name']);
            }
        );

        $slotCount = count($slotMeta);
        $uncoveredSlots = count($uncoveredSlotIds);

        return [
            'summary' => [
                'cohortSize' => count($volunteers),
                'respondedCount' => $respondedPeople,
                'assignedPeople' => $assignedPeople,
                'slotCount' => $slotCount,
                'uncoveredSlots' => $uncoveredSlots,
                'coveredSlots' => max(0, $slotCount - $uncoveredSlots),
            ],
            'volunteers' => $volunteers,
        ];
    }

    /**
     * Greedy fair auto-assignment.
     *
     * @return array{assignedCount: int, uncovered: string[]}
     * @throws BadRequest|Forbidden|NotFound
     */

    /**
     * Greedy fair auto-assignment.
     *
     * @return array{assignedCount: int, uncovered: string[]}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function autoAssign(string $offerId): array
    {
        $offer = $this->support->getOfferForEdit($offerId);

        if (!in_array($offer->get('status'), [self::STATUS_COLLECTING, self::STATUS_PLANNED], true)) {
            throw new BadRequest("Auto-assignment requires an open plan.");
        }

        // Assignment targets: Published (not yet Covered). Busy intervals must
        // still consider Covered slots so re-runs do not double-book volunteers.
        $allSlots = $this->support->getSlots($offerId);
        $slots = array_values(array_filter(
            $allSlots,
            static fn (Entity $slot): bool => $slot->get('status') === 'Published'
        ));

        $invitesBySlot = [];
        $load = [];
        $busyIntervals = [];

        foreach ($this->support->getInvites($offerId) as $invite) {
            $slotId = $invite->get('activityOfferSlotId');

            if (!$slotId) {
                continue;
            }

            $invitesBySlot[$slotId][] = $invite;

            if (in_array($invite->get('status'), [self::INVITE_ASSIGNED, self::INVITE_CONFIRMED], true)) {
                $userId = (string) $invite->get('userId');
                $load[$userId] = ($load[$userId] ?? 0) + 1;
            }
        }

        $slotById = [];

        foreach ($allSlots as $slot) {
            $slotById[$slot->getId()] = $slot;
        }

        // Pre-fill busy intervals from existing assignments (all slots).
        foreach ($invitesBySlot as $slotId => $invites) {
            foreach ($invites as $invite) {
                if (!in_array($invite->get('status'), [self::INVITE_ASSIGNED, self::INVITE_CONFIRMED], true)) {
                    continue;
                }

                $slot = $slotById[$slotId] ?? null;

                if ($slot) {
                    $busyIntervals[(string) $invite->get('userId')][] = [
                        (string) $slot->get('dateStart'),
                        (string) $slot->get('dateEnd'),
                    ];
                }
            }
        }

        // Scarcity first: slots with the least spare candidates get assigned first.
        $pending = [];

        foreach ($slots as $slot) {
            $slotId = $slot->getId();
            $required = (int) ($slot->get('requiredCount') ?? 1);
            $assigned = 0;
            $candidates = 0;

            foreach ($invitesBySlot[$slotId] ?? [] as $invite) {
                $status = $invite->get('status');

                if (in_array($status, [self::INVITE_ASSIGNED, self::INVITE_CONFIRMED], true)) {
                    $assigned++;
                }

                if ($status === self::INVITE_AVAILABLE) {
                    $candidates++;
                }
            }

            $need = $required - $assigned;

            if ($need <= 0) {
                continue;
            }

            $pending[] = [
                'slot' => $slot,
                'need' => $need,
                'spare' => $candidates - $need,
            ];
        }

        usort($pending, static fn (array $a, array $b): int => $a['spare'] <=> $b['spare']);

        $assignedCount = 0;

        foreach ($pending as $item) {
            /** @var Entity $slot */
            $slot = $item['slot'];
            $slotId = $slot->getId();
            $need = $item['need'];

            $candidates = array_values(array_filter(
                $invitesBySlot[$slotId] ?? [],
                static fn (Entity $i): bool => $i->get('status') === self::INVITE_AVAILABLE
            ));

            // Fewest assignments this week first; earlier responders break ties.
            usort($candidates, static function (Entity $a, Entity $b) use ($load): int {
                $loadA = $load[(string) $a->get('userId')] ?? 0;
                $loadB = $load[(string) $b->get('userId')] ?? 0;

                if ($loadA !== $loadB) {
                    return $loadA <=> $loadB;
                }

                return strcmp(
                    (string) ($a->get('respondedAt') ?? $a->get('createdAt') ?? ''),
                    (string) ($b->get('respondedAt') ?? $b->get('createdAt') ?? '')
                );
            });

            foreach ($candidates as $invite) {
                if ($need <= 0) {
                    break;
                }

                $userId = (string) $invite->get('userId');

                if ($this->support->overlapsBusy(
                    $busyIntervals[$userId] ?? [],
                    (string) $slot->get('dateStart'),
                    (string) $slot->get('dateEnd')
                )) {
                    continue;
                }

                $invite->set('status', self::INVITE_ASSIGNED);
                $this->support->saveEntityAllowStatus($invite);

                $load[$userId] = ($load[$userId] ?? 0) + 1;
                $busyIntervals[$userId][] = [
                    (string) $slot->get('dateStart'),
                    (string) $slot->get('dateEnd'),
                ];

                $assignedCount++;
                $need--;
            }
        }

        $offer->set('status', self::STATUS_PLANNED);
        $this->support->saveEntityAllowStatus($offer);

        $this->planLifecycle->syncSlotCoverageStatuses($offerId);

        $uncovered = [];

        foreach ($this->coverage($offerId)['slots'] as $row) {
            if (!$row['isCovered']) {
                $uncovered[] = (string) $row['name'];
            }
        }

        return [
            'assignedCount' => $assignedCount,
            'uncovered' => $uncovered,
        ];
    }

    /**
     * Extend pending auto-send window (+5 minutes by default).
     *
     * @return array{status: string, pendingNotifyAt: ?string, pendingNotifyKind: ?string, extendedMinutes: int}
     * @throws BadRequest|Forbidden|NotFound
     */

    /**
     * Extend pending auto-send window (+5 minutes by default).
     *
     * @return array{status: string, pendingNotifyAt: ?string, pendingNotifyKind: ?string, extendedMinutes: int}
     * @throws BadRequest|Forbidden|NotFound
     */
}

