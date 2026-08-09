<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

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
use DateTimeImmutable;
use DateTimeZone;

/**
 * Weekly shift planning lifecycle:
 *
 *   Draft -> CollectingAvailability -> Planned -> Confirmed -> Updated -> Completed|Closed
 *
 * Completed = week finished naturally (slots done by time). Closed = annulled or manual close.
 * Updated is a transient organizer-facing status while a soft/hard notify is pending
 * after editing a Confirmed plan (soft returns to Confirmed; hard → CollectingAvailability).
 *
 * - requestAvailability: notify the volunteer cohort to fill the availability grid.
 * - availabilityGrid / saveAvailability: volunteer-facing checkbox grid (per slot).
 * - coverage: organizer table (required / available / assigned per shift).
 * - autoAssign: fair greedy assignment respecting availability + competences.
 * - confirm: mark assigned invites Confirmed and notify volunteers (no auto Task).
 * - sendPendingUpdate: flush debounced soft/hard notify immediately.
 */
class ShiftPlanningService
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
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user,
        private Language $language,
        private ShiftEmailService $shiftEmailService,
        private ShiftChangeNotifyService $shiftChangeNotifyService,
        private ShiftCoverageSyncService $shiftCoverageSyncService,
    ) {}

    /**
     * Materialise WhatsApp-style weekly generator rows into ActivityOfferSlot
     * records. Allowed while the plan is Draft (full replace) so organizers
     * can iterate before opening availability.
     *
     * Each row:
     *   dayOfWeek, timeStart (HH:MM), timeEnd (HH:MM), requiredCount,
     *   placeStreet?, conditions? (string[] max 5), id? (existing slot)
     *
     * @param array<int, mixed> $rows
     * @return array{slotCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */
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
        return $this->syncWeekSlots($offerId, $rows, true, $batchOptions);
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
        $offer = $this->getOfferForEdit($offerId);
        $status = (string) ($offer->get('status') ?? '');

        $allowedStatuses = $append
            ? [self::STATUS_DRAFT, self::STATUS_COLLECTING]
            : [self::STATUS_DRAFT];

        if (!in_array($status, $allowedStatuses, true)) {
            throw new BadRequest(
                $append
                    ? "Shifts can only be added while the plan is Draft or collecting availability."
                    : "Weekly shifts can only be regenerated while the plan is Draft."
            );
        }

        if ($rows === []) {
            throw new BadRequest("Add at least one shift day (Monday–Sunday).");
        }

        if (count($rows) > 7) {
            throw new BadRequest("Add at most 7 shifts per batch.");
        }

        $weekStart = (string) ($offer->get('weekStart') ?? '');

        if ($weekStart === '') {
            throw new BadRequest("Week start is required.");
        }

        $uniqueAddress = !empty($batchOptions['uniqueAddress']);
        $batchPlace = [
            'placeStreet' => trim((string) ($batchOptions['placeStreet'] ?? '')),
            'placeCity' => trim((string) ($batchOptions['placeCity'] ?? '')),
            'placeState' => trim((string) ($batchOptions['placeState'] ?? '')),
            'placeCountry' => trim((string) ($batchOptions['placeCountry'] ?? '')),
            'placePostalCode' => trim((string) ($batchOptions['placePostalCode'] ?? '')),
        ];

        if ($uniqueAddress && $batchPlace['placeStreet'] === '' && $batchPlace['placeCity'] === '') {
            throw new BadRequest("Single address requires at least street or city for the batch.");
        }

        $existingSlots = $this->getSlots($offerId);
        $sortOrder = 0;

        foreach ($existingSlots as $existing) {
            $sortOrder = max($sortOrder, (int) ($existing->get('sortOrder') ?? 0) + 1);
        }

        $keptIds = [];
        $createdCount = 0;

        foreach ($rows as $index => $row) {
            if (is_object($row)) {
                $row = json_decode(json_encode($row), true);
            }

            if (!is_array($row)) {
                throw new BadRequest("Invalid week slot row at index {$index}.");
            }

            $dayOfWeek = (string) ($row['dayOfWeek'] ?? '');

            if (!isset(self::DAY_OFFSET[$dayOfWeek])) {
                throw new BadRequest("Invalid day of week at index {$index}.");
            }

            $category = trim((string) ($row['category'] ?? ''));

            if ($category === '') {
                throw new BadRequest("Category is required for each shift.");
            }

            $categoryLabel = $this->language->translateOption($category, 'category', 'ActivityOfferSlot');

            if ($categoryLabel === $category) {
                $categoryLabel = $this->language->translateOption($category, 'category', 'ActivityOffer');
            }

            $timeStart = $this->normalizeTime((string) ($row['timeStart'] ?? ''));
            $timeEnd = $this->normalizeTime((string) ($row['timeEnd'] ?? ''));
            $isAllDay = !empty($row['isAllDay']);

            if ($isAllDay) {
                $timeStart = '00:00';
                $timeEnd = '23:59';
            }

            if ($timeStart === null || $timeEnd === null) {
                throw new BadRequest("Start and end time are required for each shift.");
            }

            $requiredCount = max(0, min(99, (int) ($row['requiredCount'] ?? 1)));
            $conditions = $this->normalizeConditions($row['conditions'] ?? []);

            $place = $uniqueAddress
                ? $batchPlace
                : [
                    'placeStreet' => trim((string) ($row['placeStreet'] ?? $row['place'] ?? '')),
                    'placeCity' => trim((string) ($row['placeCity'] ?? '')),
                    'placeState' => trim((string) ($row['placeState'] ?? '')),
                    'placeCountry' => trim((string) ($row['placeCountry'] ?? '')),
                    'placePostalCode' => trim((string) ($row['placePostalCode'] ?? '')),
                ];

            $date = $this->dateForWeekday($weekStart, $dayOfWeek);
            $dateStart = $date . ' ' . $timeStart . ':00';
            $dateEnd = $date . ' ' . $timeEnd . ':00';

            if ($dateEnd <= $dateStart) {
                throw new BadRequest("End time must be after start time for {$dayOfWeek}.");
            }

            $placePart = $place['placeStreet'] !== ''
                ? $place['placeStreet']
                : $place['placeCity'];

            $name = $placePart !== ''
                ? "{$categoryLabel} | {$this->formatDateIt($date)} | {$placePart}"
                : "{$categoryLabel} | {$this->formatDateIt($date)}";

            $slotStatus = trim((string) ($row['status'] ?? 'Published'));

            if (!in_array($slotStatus, ['Published', 'Covered'], true)) {
                $slotStatus = 'Published';
            }

            $payload = array_merge($place, [
                'name' => $name,
                'activityOfferId' => $offerId,
                'category' => $category,
                'dayOfWeek' => $dayOfWeek,
                'dateStart' => $dateStart,
                'dateEnd' => $dateEnd,
                'isAllDay' => $isAllDay,
                'requiredCount' => $requiredCount,
                'conditions' => $conditions,
                'sortOrder' => $sortOrder++,
                'status' => $slotStatus,
            ]);

            $existingId = !$append && isset($row['id']) && is_string($row['id'])
                ? $row['id']
                : null;
            $slot = $existingId
                ? $this->entityManager->getEntityById('ActivityOfferSlot', $existingId)
                : null;

            if ($slot && $slot->get('activityOfferId') !== $offerId) {
                $slot = null;
            }

            if (!$slot) {
                $slot = $this->entityManager->getNewEntity('ActivityOfferSlot');
                $createdCount++;
            }

            $slot->set($payload);
            $this->saveEntityAllowStatus($slot);
            $keptIds[] = $slot->getId();
        }

        if (!$append) {
            foreach ($existingSlots as $existing) {
                if (!in_array($existing->getId(), $keptIds, true)) {
                    $this->entityManager->removeEntity($existing);
                }
            }
        }

        $total = $append
            ? count($this->getSlots($offerId))
            : count($keptIds);

        return [
            'slotCount' => $total,
            'createdCount' => $createdCount,
        ];
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
        $offer = $this->getOfferForEdit($offerId);

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

        // Published and Covered both participate in availability (autoAssign
        // flips fully-staffed shifts to Covered — still editable/withdrawable).
        $slots = $this->getRespondableSlots($offerId);

        if ($slots === []) {
            throw new BadRequest("Publish at least one shift (status Published or Covered) before requesting availability.");
        }

        $changedOnly = $this->shiftChangeNotifyService->getPendingChangedSlotIds($offerId);
        $needsHardRecollect = $changedOnly !== []
            || in_array($status, [self::STATUS_UPDATED, self::STATUS_CONFIRMED], true);

        if ($needsHardRecollect && $this->resolveInvolvedUserIds($offerId) !== []) {
            // Only changed slots when we know them; otherwise all published (full re-request).
            $hard = $this->shiftChangeNotifyService->hardRecollectAvailability(
                $offerId,
                $changedOnly !== [] ? $changedOnly : null
            );

            $involvedIds = $this->resolveInvolvedUserIds($offerId);

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

        $cohortIds = $this->resolveCohortUserIds($offer);

        if ($cohortIds === []) {
            // Fall back to already-involved users when cohort links are empty.
            $cohortIds = $this->resolveInvolvedUserIds($offerId);
        }

        if ($cohortIds === []) {
            throw new BadRequest("Select volunteers and/or teams before requesting availability.");
        }

        if ($status === self::STATUS_DRAFT) {
            $offer->set('status', self::STATUS_COLLECTING);
            $offer->set('publishedAt', $this->nowString());
            $this->saveEntityAllowStatus($offer);
        }

        $message = $this->translateMessage('availabilityRequestNotification', [
            'name' => (string) $offer->get(Field::NAME),
            'weekStart' => (string) ($offer->get('weekStart') ?? ''),
        ]);

        $notifyCount = $this->notifyUsers($offer, $cohortIds, $message);

        $emailResult = $this->shiftEmailService->sendAvailabilityRequest($offer, $cohortIds);

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
     * Volunteer-facing grid data.
     *
     * @return array<string, mixed>
     * @throws Forbidden|NotFound
     */
    public function availabilityGrid(string $offerId): array
    {
        $offer = $this->getOffer($offerId);

        $cohortIds = $this->resolveCohortUserIds($offer);
        $isMember = in_array($this->user->getId(), $cohortIds, true);

        if (!$isMember && !$this->acl->check($offer, Acl\Table::ACTION_READ)) {
            throw new Forbidden("No access to this shift plan.");
        }

        $competences = $this->getUserCompetences($this->user);

        $myInvites = [];

        foreach ($this->getInvites($offerId, $this->user->getId()) as $invite) {
            $slotId = $invite->get('activityOfferSlotId');

            if ($slotId) {
                $myInvites[$slotId] = [
                    'status' => $invite->get('status'),
                    'comment' => (string) ($invite->get('comment') ?? ''),
                ];
            }
        }

        $changedIds = array_fill_keys(
            $this->shiftChangeNotifyService->getPendingChangedSlotIds($offerId),
            true
        );

        $slotList = [];

        foreach ($this->getSlots($offerId) as $slot) {
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
                'categoryLabel' => $this->language
                    ->translateOption($category, 'category', 'ActivityOfferSlot'),
                'dateStart' => $slot->get('dateStart'),
                'dateEnd' => $slot->get('dateEnd'),
                'requiredCount' => (int) ($slot->get('requiredCount') ?? 1),
                'placeStreet' => (string) ($slot->get('placeStreet') ?? ''),
                'placeCity' => (string) ($slot->get('placeCity') ?? ''),
                'placeLabel' => $this->formatPlaceLabel($slot),
                'conditions' => $this->normalizeConditions($slot->get('conditions') ?? []),
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
            'placeLabel' => $this->formatPlaceLabel($offer),
            'canRespond' => in_array(
                $offer->get('status'),
                [self::STATUS_COLLECTING, self::STATUS_PLANNED],
                true
            ),
            'changedSlotIds' => array_keys($changedIds),
            'comment' => $this->getUserPlanComment($offerId, $this->user->getId()),
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
    public function saveAvailability(string $offerId, array $slotIds, ?string $comment = null): array
    {
        $offer = $this->getOffer($offerId);

        if (!in_array($offer->get('status'), [self::STATUS_COLLECTING, self::STATUS_PLANNED], true)) {
            throw new BadRequest("Availability collection is not open for this plan.");
        }

        $cohortIds = $this->resolveCohortUserIds($offer);

        if (!in_array($this->user->getId(), $cohortIds, true) && !$this->user->isAdmin()) {
            throw new Forbidden("You are not part of this shift plan.");
        }

        $competences = $this->getUserCompetences($this->user);
        $checked = array_fill_keys(array_filter($slotIds, 'is_string'), true);
        $commentValue = $comment !== null ? trim($comment) : null;

        if ($commentValue !== null && mb_strlen($commentValue) > 2000) {
            throw new BadRequest("Comment is too long.");
        }

        $invitesBySlot = [];

        foreach ($this->getInvites($offerId, $this->user->getId()) as $invite) {
            $slotId = $invite->get('activityOfferSlotId');

            if ($slotId) {
                $invitesBySlot[$slotId] = $invite;
            }
        }

        $availableCount = 0;
        $withdrawnCount = 0;

        // Must match availabilityGrid: Covered slots stay in the volunteer UI
        // after autoAssign. Iterating Published-only made uncheck a silent no-op
        // (invite stayed Available/Assigned while the modal reported success).
        foreach ($this->getRespondableSlots($offerId) as $slot) {
            $slotId = $slot->getId();
            $category = (string) ($slot->get('category') ?? '');
            $isChecked = isset($checked[$slotId]);
            $invite = $invitesBySlot[$slotId] ?? null;

            if ($isChecked) {
                if ($competences !== [] && !in_array($category, $competences, true)) {
                    continue;
                }

                if (!$invite) {
                    $new = $this->entityManager->getNewEntity('ActivityInvite');
                    $new->set([
                        'name' => trim(($slot->get(Field::NAME) ?? '') . ' · ' . $this->user->getName()),
                        'userId' => $this->user->getId(),
                        'activityOfferId' => $offerId,
                        'activityOfferSlotId' => $slotId,
                        'status' => self::INVITE_AVAILABLE,
                        'respondedAt' => $this->nowString(),
                        'comment' => $commentValue ?? '',
                    ]);

                    $this->saveEntityAllowStatus($new);
                    $availableCount++;

                    continue;
                }

                if (in_array($invite->get('status'), [self::INVITE_DECLINED, self::INVITE_CANCELLED], true)) {
                    $invite->set('status', self::INVITE_AVAILABLE);
                    $invite->set('respondedAt', $this->nowString());
                }

                if ($commentValue !== null) {
                    $invite->set('comment', $commentValue);
                }

                $this->saveEntityAllowStatus($invite);
                $availableCount++;

                continue;
            }

            if (!$invite) {
                continue;
            }

            if ($invite->get('status') === self::INVITE_AVAILABLE) {
                $this->entityManager->removeEntity($invite);

                continue;
            }

            if (in_array($invite->get('status'), [self::INVITE_ASSIGNED, self::INVITE_CONFIRMED], true)) {
                $invite->set('status', self::INVITE_DECLINED);
                $invite->set('respondedAt', $this->nowString());

                if ($commentValue !== null) {
                    $invite->set('comment', $commentValue);
                }

                $this->saveEntityAllowStatus($invite);

                $this->syncTaskCollaborator($invite, false);
                $withdrawnCount++;
            }
        }

        // Always recompute plan-level "enough availability" after the grid save.
        // Relying only on ActivityInvite AfterSave misses unchanged-status saves
        // and removeEntity withdraws.
        $this->syncSlotCoverageStatuses($offerId);

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
    public function coverage(string $offerId): array
    {
        $offer = $this->getOffer($offerId);

        if (!$this->acl->check($offer, Acl\Table::ACTION_READ)) {
            throw new Forbidden("No read access to ActivityOffer.");
        }

        $invitesBySlot = [];

        foreach ($this->getInvites($offerId) as $invite) {
            $slotId = $invite->get('activityOfferSlotId');

            if ($slotId) {
                $invitesBySlot[$slotId][] = $invite;
            }
        }

        $userNames = $this->loadUserNames($offerId);

        $slotList = [];
        $uncoveredCount = 0;

        foreach ($this->getSlots($offerId) as $slot) {
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
                'category' => $slot->get('category'),
                'categoryLabel' => $this->language->translateOption(
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
                'placeLabel' => $this->formatPlaceLabel($slot),
                'conditions' => $this->normalizeConditions($slot->get('conditions') ?? []),
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
    public function slotStaffing(string $slotId): array
    {
        $slot = $this->entityManager->getEntityById('ActivityOfferSlot', $slotId);

        if (!$slot) {
            throw new NotFound('ActivityOfferSlot not found.');
        }

        if (!$this->acl->check($slot, Acl\Table::ACTION_READ)) {
            throw new Forbidden('No read access to ActivityOfferSlot.');
        }

        $offerId = (string) ($slot->get('activityOfferId') ?? '');

        if ($offerId === '') {
            throw new BadRequest('Shift is not linked to a plan.');
        }

        $offer = $this->getOffer($offerId);
        $canResend = $this->acl->check($offer, Acl\Table::ACTION_EDIT)
            || $this->acl->check($slot, Acl\Table::ACTION_EDIT);

        $userNames = $this->loadUserNames($offerId);
        $assigned = [];
        $candidates = [];

        foreach ($this->getInvites($offerId) as $invite) {
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
    public function resendSlotInvite(string $slotId, string $userId): array
    {
        $slot = $this->entityManager->getEntityById('ActivityOfferSlot', $slotId);

        if (!$slot) {
            throw new NotFound('ActivityOfferSlot not found.');
        }

        $offerId = (string) ($slot->get('activityOfferId') ?? '');

        if ($offerId === '') {
            throw new BadRequest('Shift is not linked to a plan.');
        }

        $offer = $this->getOfferForEdit($offerId);

        $invite = null;

        foreach ($this->getInvites($offerId, $userId) as $row) {
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

        $message = $this->translateMessage('availabilityRequestNotification', [
            'name' => (string) $offer->get(Field::NAME),
            'weekStart' => (string) ($offer->get('weekStart') ?? ''),
        ]);

        $notified = false;

        if ($userId !== $this->user->getId()) {
            $this->createNotification($offer, $userId, $message);
            $notified = true;
        }

        $emailResult = $this->shiftEmailService->sendAvailabilityRequest($offer, [$userId]);

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
    public function volunteerStats(string $offerId): array
    {
        $offer = $this->getOffer($offerId);

        if (!$this->acl->check($offer, Acl\Table::ACTION_READ)) {
            throw new Forbidden("No read access to ActivityOffer.");
        }

        $cohortIds = $this->resolveCohortUserIds($offer);
        $slots = $this->getSlots($offerId);
        $userNames = $this->loadUserNames($offerId);

        $invitesByUser = [];

        foreach ($this->getInvites($offerId) as $invite) {
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
                'categoryLabel' => $this->language->translateOption(
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
            $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

            if (!$user) {
                continue;
            }

            $competences = $this->getUserCompetences($user);
            $competenceLabels = array_map(
                fn (string $code): string => $this->language->translateOption(
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
    public function autoAssign(string $offerId): array
    {
        $offer = $this->getOfferForEdit($offerId);

        if (!in_array($offer->get('status'), [self::STATUS_COLLECTING, self::STATUS_PLANNED], true)) {
            throw new BadRequest("Auto-assignment requires an open plan.");
        }

        // Assignment targets: Published (not yet Covered). Busy intervals must
        // still consider Covered slots so re-runs do not double-book volunteers.
        $allSlots = $this->getSlots($offerId);
        $slots = array_values(array_filter(
            $allSlots,
            static fn (Entity $slot): bool => $slot->get('status') === 'Published'
        ));

        $invitesBySlot = [];
        $load = [];
        $busyIntervals = [];

        foreach ($this->getInvites($offerId) as $invite) {
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

                if ($this->overlapsBusy(
                    $busyIntervals[$userId] ?? [],
                    (string) $slot->get('dateStart'),
                    (string) $slot->get('dateEnd')
                )) {
                    continue;
                }

                $invite->set('status', self::INVITE_ASSIGNED);
                $this->saveEntityAllowStatus($invite);

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
        $this->saveEntityAllowStatus($offer);

        $this->syncSlotCoverageStatuses($offerId);

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
    public function extendPendingUpdate(string $offerId, int $minutes = 5): array
    {
        $this->getOfferForEdit($offerId);

        $result = $this->shiftChangeNotifyService->extendPendingUpdate($offerId, $minutes);

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
    public function discardPendingUpdate(string $offerId): array
    {
        $this->getOfferForEdit($offerId);

        $result = $this->shiftChangeNotifyService->discardPendingUpdate($offerId);

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
    public function sendPendingUpdate(string $offerId): array
    {
        $offer = $this->getOfferForEdit($offerId);
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

        $result = $this->shiftChangeNotifyService->sendPendingUpdateNow($offerId);
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
    public function confirm(string $offerId): array
    {
        $offer = $this->getOfferForEdit($offerId);

        if (!in_array($offer->get('status'), [self::STATUS_COLLECTING, self::STATUS_PLANNED], true)) {
            throw new BadRequest("Only an open plan can be confirmed.");
        }

        $invitesBySlot = [];

        foreach ($this->getInvites($offerId) as $invite) {
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

        foreach ($this->getSlots($offerId) as $slot) {
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
                    $this->saveEntityAllowStatus($invite);
                }

                $confirmedCount++;

                $line = $this->shiftEmailService->formatConfirmedShiftLine($slot);
                $shiftsByUser[$userId][] = $line;

                $userEntity = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);
                $assigneeNames[] = $userEntity ? (string) $userEntity->getName() : $userId;
            }

            $adminDigestLines[] = $this->shiftEmailService->formatConfirmedShiftLine($slot)
                . ' → ' . implode(', ', $assigneeNames);
        }

        $offer->set('status', self::STATUS_CONFIRMED);
        $this->saveEntityAllowStatus($offer);
        $this->shiftChangeNotifyService->clearPendingChangedSlots($offerId);

        $this->syncSlotCoverageStatuses($offerId);

        $notifyCount = 0;
        $emailCount = 0;

        foreach ($shiftsByUser as $userId => $shiftLines) {
            if ($userId === $this->user->getId()) {
                continue;
            }

            $message = $this->translateMessage('shiftsConfirmedNotification', [
                'name' => (string) $offer->get(Field::NAME),
                'shifts' => implode('; ', $shiftLines),
            ]);

            $this->createNotification($offer, $userId, $message);
            $notifyCount++;

            $emailResult = $this->shiftEmailService->sendShiftsConfirmed($offer, $userId, $shiftLines);
            $emailCount += (int) ($emailResult['sent'] ?? 0);
        }

        $adminId = (string) ($offer->get('assignedUserId') ?? '');

        if ($adminId !== '' && $adminDigestLines !== []) {
            $emailResult = $this->shiftEmailService->sendAdminDigest($offer, $adminId, $adminDigestLines);
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
    private function getOffer(string $offerId): Entity
    {
        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer) {
            throw new NotFound("ActivityOffer not found.");
        }

        return $offer;
    }

    /**
     * @throws Forbidden|NotFound
     */
    private function getOfferForEdit(string $offerId): Entity
    {
        $offer = $this->getOffer($offerId);

        if (!$this->acl->check($offer, Acl\Table::ACTION_EDIT)) {
            throw new Forbidden("No edit access to ActivityOffer.");
        }

        return $offer;
    }

    /**
     * @return Entity[]
     */
    private function getSlots(string $offerId): array
    {
        $collection = $this->entityManager
            ->getRDBRepository('ActivityOfferSlot')
            ->where(['activityOfferId' => $offerId])
            ->order('dateStart')
            ->find();

        return iterator_to_array($collection);
    }

    /**
     * Keep slot status in sync with staffing:
     * Published → Covered when assignedCount >= requiredCount,
     * Covered → Published when staffing drops below required.
     */

    /**
     * Shifts that can receive availability invites / auto-assignment (Published only).
     *
     * @return Entity[]
     */

    /**
     * Keep slot status in sync with staffing:
     * Published → Covered when assignedCount >= requiredCount,
     * Covered → Published when staffing drops below required.
     * Then refresh plan-level isFullyStaffed and notify the week creator once.
     */
    public function syncSlotCoverageStatuses(string $offerId): void
    {
        $this->shiftCoverageSyncService->sync($offerId);
    }

    /**
     * Shifts volunteers can mark / withdraw availability on (and managers can
     * re-request). Matches availabilityGrid: Published + Covered.
     *
     * @return Entity[]
     */
    private function getRespondableSlots(string $offerId): array
    {
        return array_values(array_filter(
            $this->getSlots($offerId),
            static fn (Entity $slot): bool => in_array(
                (string) ($slot->get('status') ?? ''),
                ['Published', 'Covered'],
                true
            )
        ));
    }

    /**
     * @return Entity[]
     */
    private function getInvites(string $offerId, ?string $userId = null): array
    {
        $slotIds = array_map(
            fn (Entity $slot) => $slot->getId(),
            $this->getSlots($offerId)
        );

        // Match by offer id OR by slot membership: a slot may have been
        // re-parented to another plan after invites were created, leaving
        // invite.activityOfferId stale (would cause UNIQ_SLOT_USER duplicates).
        $where = [
            'OR' => array_filter([
                ['activityOfferId' => $offerId],
                $slotIds !== [] ? ['activityOfferSlotId' => $slotIds] : null,
            ]),
        ];

        if ($userId !== null) {
            $where['userId'] = $userId;
        }

        $collection = $this->entityManager
            ->getRDBRepository('ActivityInvite')
            ->where($where)
            ->find();

        $slotIdMap = array_fill_keys($slotIds, true);
        $invites = [];

        foreach ($collection as $invite) {
            $slotId = $invite->get('activityOfferSlotId');

            // Skip invites whose slot moved away to another plan.
            if ($slotId && !isset($slotIdMap[$slotId])) {
                continue;
            }

            // Heal a stale offer link (slot belongs to this plan).
            if ($slotId && $invite->get('activityOfferId') !== $offerId) {
                $invite->set('activityOfferId', $offerId);

                $this->saveEntityAllowStatus($invite, [SaveOption::SILENT => true]);
            }

            $invites[] = $invite;
        }

        return $invites;
    }

    /**
     * @return string[]
     */
    private function resolveCohortUserIds(Entity $offer): array
    {
        $ids = $offer->getLinkMultipleIdList('inviteeUsers');

        foreach ($offer->getLinkMultipleIdList('inviteTeams') as $teamId) {
            /** @var ?Team $team */
            $team = $this->entityManager->getEntityById(Team::ENTITY_TYPE, $teamId);

            if (!$team) {
                continue;
            }

            $users = $this->entityManager
                ->getRDBRepository(Team::ENTITY_TYPE)
                ->getRelation($team, 'users')
                ->where(['isActive' => true, 'type' => ['admin', 'regular']])
                ->find();

            foreach ($users as $user) {
                $ids[] = $user->getId();
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Users with Available / Assigned / Confirmed invites on this plan.
     *
     * @return list<string>
     */
    private function resolveInvolvedUserIds(string $offerId): array
    {
        $ids = [];

        foreach ($this->getInvites($offerId) as $invite) {
            $status = (string) ($invite->get('status') ?? '');

            if (!in_array($status, [
                self::INVITE_AVAILABLE,
                self::INVITE_ASSIGNED,
                self::INVITE_CONFIRMED,
            ], true)) {
                continue;
            }

            $userId = (string) ($invite->get('userId') ?? '');

            if ($userId !== '') {
                $ids[$userId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @return string[]
     */
    private function getUserCompetences(User $user): array
    {
        $value = $user->get('activityCompetences');

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    /**
     * @param array<int, array{0: string, 1: string}> $intervals
     */
    private function overlapsBusy(array $intervals, string $start, string $end): bool
    {
        if ($start === '' || $end === '') {
            return false;
        }

        foreach ($intervals as [$busyStart, $busyEnd]) {
            if ($busyStart === '' || $busyEnd === '') {
                continue;
            }

            if ($start < $busyEnd && $busyStart < $end) {
                return true;
            }
        }

        return false;
    }

    private function syncTaskCollaborator(Entity $invite, bool $add): void
    {
        $taskId = $invite->get('taskId');
        $userId = $invite->get('userId');

        if (!$taskId || !$userId) {
            return;
        }

        $task = $this->entityManager->getEntityById('Task', $taskId);

        if (!$task) {
            return;
        }

        $ids = $task->getLinkMultipleIdList(Field::COLLABORATORS);

        if ($add && !in_array($userId, $ids, true)) {
            $ids[] = $userId;
        } elseif (!$add) {
            $ids = array_values(array_filter($ids, static fn (string $id): bool => $id !== $userId));
        } else {
            return;
        }

        $task->set(Field::COLLABORATORS . 'Ids', $ids);
        $this->entityManager->saveEntity($task);
    }

    /**
     * @return array<string, string>
     */
    private function loadUserNames(string $offerId): array
    {
        $userIds = [];

        foreach ($this->getInvites($offerId) as $invite) {
            $userId = $invite->get('userId');

            if ($userId) {
                $userIds[$userId] = true;
            }
        }

        if ($userIds === []) {
            return [];
        }

        $names = [];

        $users = $this->entityManager
            ->getRDBRepository(User::ENTITY_TYPE)
            ->where(['id' => array_keys($userIds)])
            ->find();

        foreach ($users as $user) {
            $names[$user->getId()] = (string) $user->get(Field::NAME);
        }

        return $names;
    }

    /**
     * @param string[] $userIds
     */
    private function notifyUsers(Entity $offer, array $userIds, string $message): int
    {
        $count = 0;

        foreach ($userIds as $userId) {
            if ($userId === $this->user->getId()) {
                continue;
            }

            $this->createNotification($offer, $userId, $message);
            $count++;
        }

        return $count;
    }

    private function createNotification(Entity $offer, string $userId, string $message): void
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
    private function translateMessage(string $key, array $params): string
    {
        $message = $this->language->translateLabel($key, 'messages', 'ActivityOffer');

        foreach ($params as $name => $value) {
            $message = str_replace('{' . $name . '}', $value, $message);
        }

        return $message;
    }

    private function formatPlaceAddress(Entity $slot): string
    {
        $parts = array_filter([
            trim((string) ($slot->get('placeStreet') ?? '')),
            trim((string) ($slot->get('placeCity') ?? '')),
            trim((string) ($slot->get('placeState') ?? '')),
            trim((string) ($slot->get('placePostalCode') ?? '')),
            trim((string) ($slot->get('placeCountry') ?? '')),
        ], static fn (string $v): bool => $v !== '');

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return trim((string) ($slot->get('place') ?? ''));
    }

    private function formatPlaceLabel(Entity $entity): string
    {
        return $this->formatPlaceAddress($entity);
    }

    /**
     * @param mixed $raw
     * @return string[]
     */
    private function normalizeConditions(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [$raw];
        }

        if (!is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $item) {
            if (is_object($item)) {
                $item = (string) ($item->value ?? $item->name ?? '');
            }

            $value = trim((string) $item);

            if ($value === '') {
                continue;
            }

            $out[] = mb_substr($value, 0, 200);

            if (count($out) >= 5) {
                break;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Persist entity allowing system-managed status writes.
     *
     * @param array<string, mixed> $options
     */
    private function saveEntityAllowStatus(Entity $entity, array $options = []): void
    {
        $options[StatusGuard::SKIP_OPTION] = true;
        $this->entityManager->saveEntity($entity, $options);
    }

    /**
     * Close a shift plan (system status transition; not editable via form).
     *
     * @return array{status: string}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function closePlan(string $offerId): array
    {
        $offer = $this->getOfferForEdit($offerId);
        $status = (string) ($offer->get('status') ?? '');

        if ($this->isPlanTerminal($status) || $status === self::STATUS_DRAFT) {
            throw new BadRequest("Plan can only be closed after availability collection has started.");
        }

        $offer->set('status', self::STATUS_CLOSED);
        $this->saveEntityAllowStatus($offer);

        return ['status' => self::STATUS_CLOSED];
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
        $slot = $this->entityManager->getEntityById('ActivityOfferSlot', $slotId);

        if (!$slot) {
            throw new NotFound("ActivityOfferSlot not found.");
        }

        $offerId = (string) ($slot->get('activityOfferId') ?? '');
        $offer = $this->getOfferForEdit($offerId);

        $status = (string) ($slot->get('status') ?? '');

        if (in_array($status, self::TERMINAL_SLOT_STATUSES, true)) {
            throw new BadRequest("Shift is already completed or cancelled.");
        }

        $staffedUserIds = $this->getStaffedInviteUserIds($slot);

        $slot->set('status', self::SLOT_CANCELLED);
        $this->saveEntityAllowStatus($slot);

        $this->cancelInvitesOnSlot($slot);

        $notify = $this->notifyUsersSlotCancellation(
            $offer,
            $staffedUserIds,
            [$this->shiftEmailService->formatConfirmedShiftLine($slot)]
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
    public function cancelAll(string $offerId): array
    {
        $offer = $this->getOfferForEdit($offerId);
        $status = (string) ($offer->get('status') ?? '');

        if ($status === self::STATUS_DRAFT || $this->isPlanTerminal($status)) {
            throw new BadRequest("Plan can only be cancelled after availability collection has started.");
        }

        /** @var array<string, string[]> $linesByUser */
        $linesByUser = [];
        $cancelledCount = 0;

        foreach ($this->getSlots($offerId) as $slot) {
            $slotStatus = (string) ($slot->get('status') ?? '');

            // Completed stays Completed — never reclassified as Cancelled.
            if (in_array($slotStatus, self::TERMINAL_SLOT_STATUSES, true)) {
                continue;
            }

            $staffedUserIds = $this->getStaffedInviteUserIds($slot);
            $line = $this->shiftEmailService->formatConfirmedShiftLine($slot);

            $slot->set('status', self::SLOT_CANCELLED);
            $this->saveEntityAllowStatus($slot);
            $this->cancelInvitesOnSlot($slot);
            $cancelledCount++;

            foreach ($staffedUserIds as $userId) {
                $linesByUser[$userId][] = $line;
            }
        }

        $notifyCount = 0;
        $emailCount = 0;

        foreach ($linesByUser as $userId => $lines) {
            $result = $this->notifyUsersSlotCancellation($offer, [$userId], $lines);
            $notifyCount += $result['notifyCount'];
            $emailCount += $result['emailCount'];
        }

        // Explicit annul → Closed (even if some shifts were already Completed).
        $offer->set('status', self::STATUS_CLOSED);
        $this->saveEntityAllowStatus($offer);

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
    public function notifyOnSlotDelete(Entity $slot): void
    {
        $status = (string) ($slot->get('status') ?? '');

        if ($status === self::SLOT_CANCELLED) {
            return;
        }

        $offerId = (string) ($slot->get('activityOfferId') ?? '');
        $offer = $offerId !== ''
            ? $this->entityManager->getEntityById('ActivityOffer', $offerId)
            : null;

        if (!$offer) {
            return;
        }

        $staffedUserIds = $this->getStaffedInviteUserIds($slot);

        if ($staffedUserIds === []) {
            return;
        }

        $this->notifyUsersSlotCancellation(
            $offer,
            $staffedUserIds,
            [$this->shiftEmailService->formatConfirmedShiftLine($slot)]
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
    public function completePastSlots(?string $now = null): int
    {
        $now = $now ?? (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');

        $collection = $this->entityManager
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
            $this->saveEntityAllowStatus($slot, [
                SaveOption::SILENT => true,
            ]);
            $updated++;
        }

        return $updated;
    }

    public function tryAutoCloseOffer(string $offerId): ?string
    {
        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer) {
            return null;
        }

        $offerStatus = (string) ($offer->get('status') ?? '');

        if ($offerStatus === self::STATUS_DRAFT || $this->isPlanTerminal($offerStatus)) {
            return null;
        }

        $slots = $this->getSlots($offerId);

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
        $this->saveEntityAllowStatus($offer, [
            SaveOption::SILENT => true,
        ]);

        return $newStatus;
    }

    private function isPlanTerminal(string $status): bool
    {
        return in_array($status, [self::STATUS_COMPLETED, self::STATUS_CLOSED], true);
    }

    /**
     * @return string[]
     */
    private function getStaffedInviteUserIds(Entity $slot): array
    {
        $ids = [];

        foreach ($this->getInvitesForSlot($slot->getId()) as $invite) {
            $inviteStatus = (string) ($invite->get('status') ?? '');

            if (!in_array($inviteStatus, [self::INVITE_ASSIGNED, self::INVITE_CONFIRMED], true)) {
                continue;
            }

            $userId = (string) ($invite->get('userId') ?? '');

            if ($userId !== '') {
                $ids[$userId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @return Entity[]
     */
    private function getInvitesForSlot(string $slotId): array
    {
        $collection = $this->entityManager
            ->getRDBRepository('ActivityInvite')
            ->where(['activityOfferSlotId' => $slotId])
            ->find();

        return iterator_to_array($collection);
    }

    private function cancelInvitesOnSlot(Entity $slot): void
    {
        foreach ($this->getInvitesForSlot($slot->getId()) as $invite) {
            if ((string) ($invite->get('status') ?? '') === self::INVITE_CANCELLED) {
                continue;
            }

            $invite->set('status', self::INVITE_CANCELLED);
            $this->entityManager->saveEntity($invite, [
                SaveOption::SKIP_ALL => true,
                SaveOption::SILENT => true,
            ]);
        }
    }

    /**
     * @param string[] $userIds
     * @param string[] $shiftLines
     * @return array{notifyCount: int, emailCount: int}
     */
    private function notifyUsersSlotCancellation(Entity $offer, array $userIds, array $shiftLines): array
    {
        $notifyCount = 0;
        $emailCount = 0;
        $message = $this->translateMessage('shiftCancelledNotification', [
            'name' => (string) ($offer->get(Field::NAME) ?? ''),
            'shifts' => implode('; ', $shiftLines),
        ]);

        foreach ($userIds as $userId) {
            if ($userId === $this->user->getId()) {
                continue;
            }

            $this->createNotification($offer, $userId, $message);
            $notifyCount++;

            $result = $this->shiftEmailService->sendShiftCancelled($offer, $userId, $shiftLines);
            $emailCount += (int) ($result['sent'] ?? 0);
        }

        return [
            'notifyCount' => $notifyCount,
            'emailCount' => $emailCount,
        ];
    }

    /**
     * Move slot dateStart/dateEnd onto the calendar day implied by dayOfWeek
     * within the parent plan weekStart, keeping clock times (or all-day bounds).
     */

    /**
     * Derive English dayOfWeek enum from dateStart (keeps Giorno in sync when Inizio is edited).
     */

    /**
     * Move slot dateStart/dateEnd onto the calendar day implied by dayOfWeek
     * within the parent plan weekStart, keeping clock times (or all-day bounds).
     */
    public function syncSlotDatesFromDayOfWeek(Entity $slot): void
    {
        $dayOfWeek = (string) ($slot->get('dayOfWeek') ?? '');

        if (!isset(self::DAY_OFFSET[$dayOfWeek])) {
            return;
        }

        $offerId = (string) ($slot->get('activityOfferId') ?? '');

        if ($offerId === '') {
            return;
        }

        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer) {
            return;
        }

        $weekStart = (string) ($offer->get('weekStart') ?? '');

        if ($weekStart === '') {
            return;
        }

        $date = $this->dateForWeekday($weekStart, $dayOfWeek);

        $startTime = $this->extractClockTime((string) ($slot->get('dateStart') ?? ''));
        $endTime = $this->extractClockTime((string) ($slot->get('dateEnd') ?? ''));

        if ($startTime === null) {
            $startTime = '10:00:00';
        }

        if ($endTime === null) {
            $endTime = '12:00:00';
        }

        if (!empty($slot->get('isAllDay'))) {
            $startTime = '00:00:00';
            $endTime = '23:59:00';
        }

        $dateStart = $date . ' ' . $startTime;
        $dateEnd = $date . ' ' . $endTime;

        if ($dateEnd <= $dateStart) {
            $dateEnd = $date . ' 23:59:00';
        }

        $slot->set('dateStart', $dateStart);
        $slot->set('dateEnd', $dateEnd);
    }

    /**
     * Derive English dayOfWeek enum from dateStart (keeps Giorno in sync when Inizio is edited).
     */
    public function syncSlotDayOfWeekFromDateStart(Entity $slot): void
    {
        $dateStart = (string) ($slot->get('dateStart') ?? '');

        if ($dateStart === '') {
            return;
        }

        try {
            $dt = new DateTimeImmutable($dateStart);
        } catch (\Throwable) {
            return;
        }

        $map = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        $n = (int) $dt->format('N');

        if (!isset($map[$n])) {
            return;
        }

        $slot->set('dayOfWeek', $map[$n]);
    }

    private function extractClockTime(string $datetime): ?string
    {
        $datetime = trim($datetime);

        if ($datetime === '') {
            return null;
        }

        if (preg_match('/\b(\d{2}):(\d{2})(?::(\d{2}))?\b/', $datetime, $m)) {
            $sec = $m[3] ?? '00';

            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) $sec);
        }

        return null;
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];

            if ($h < 0 || $h > 23 || $min < 0 || $min > 59) {
                return null;
            }

            return sprintf('%02d:%02d', $h, $min);
        }

        return null;
    }

    private function dateForWeekday(string $weekStart, string $dayOfWeek): string
    {
        $base = new DateTimeImmutable($weekStart . ' 00:00:00');
        $offset = self::DAY_OFFSET[$dayOfWeek];

        return $base->modify("+{$offset} days")->format('Y-m-d');
    }

    private function formatDateIt(string $ymd): string
    {
        try {
            return (new DateTimeImmutable($ymd))->format('d.m.Y');
        } catch (\Throwable) {
            return $ymd;
        }
    }

    private function getUserPlanComment(string $offerId, string $userId): string
    {
        foreach ($this->getInvites($offerId, $userId) as $invite) {
            $comment = trim((string) ($invite->get('comment') ?? ''));

            if ($comment !== '') {
                return $comment;
            }
        }

        return '';
    }

    private function nowString(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
