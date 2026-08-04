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
use DateTimeImmutable;
use DateTimeZone;

/**
 * Weekly shift planning lifecycle:
 *
 *   Draft -> CollectingAvailability -> Planned -> Confirmed -> Closed
 *
 * - requestAvailability: notify the volunteer cohort to fill the availability grid.
 * - availabilityGrid / saveAvailability: volunteer-facing checkbox grid (per slot).
 * - coverage: organizer table (required / available / assigned per shift).
 * - autoAssign: fair greedy assignment respecting availability + competences.
 * - confirm: create Tasks per shift, sync collaborators, notify volunteers.
 */
class ShiftPlanningService
{
    private const STATUS_DRAFT = 'Draft';
    private const STATUS_COLLECTING = 'CollectingAvailability';
    private const STATUS_PLANNED = 'Planned';
    private const STATUS_CONFIRMED = 'Confirmed';

    private const INVITE_AVAILABLE = 'Available';
    private const INVITE_ASSIGNED = 'Assigned';
    private const INVITE_CONFIRMED = 'Confirmed';
    private const INVITE_DECLINED = 'Declined';
    private const INVITE_CANCELLED = 'Cancelled';

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

            $slotStatus = trim((string) ($row['status'] ?? 'Draft'));

            if (!in_array($slotStatus, ['Draft', 'Published', 'Covered', 'Cancelled'], true)) {
                $slotStatus = 'Draft';
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
            $this->entityManager->saveEntity($slot);
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
     * Open availability collection and notify the cohort.
     *
     * @return array{notifyCount: int, slotCount: int}
     * @throws BadRequest|Forbidden|NotFound
     */
    public function requestAvailability(string $offerId): array
    {
        $offer = $this->getOfferForEdit($offerId);

        $status = $offer->get('status');

        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_COLLECTING], true)) {
            throw new BadRequest("Availability can be requested for Draft plans only.");
        }

        $slots = $this->getPublishedSlots($offerId);

        if ($slots === []) {
            throw new BadRequest("Publish at least one shift (status Published) before requesting availability.");
        }

        $cohortIds = $this->resolveCohortUserIds($offer);

        if ($cohortIds === []) {
            throw new BadRequest("Select volunteers and/or teams before requesting availability.");
        }

        $offer->set('status', self::STATUS_COLLECTING);
        $offer->set('publishedAt', $this->nowString());
        $this->entityManager->saveEntity($offer);

        $message = $this->translateMessage('availabilityRequestNotification', [
            'name' => (string) $offer->get(Field::NAME),
            'weekStart' => (string) ($offer->get('weekStart') ?? ''),
        ]);

        $notifyCount = $this->notifyUsers($offer, $cohortIds, $message);

        $emailCount = $this->shiftEmailService->sendAvailabilityRequest($offer, $cohortIds);

        return [
            'notifyCount' => $notifyCount,
            'slotCount' => count($slots),
            'emailCount' => $emailCount,
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

        $slotList = [];

        foreach ($this->getSlots($offerId) as $slot) {
            $slotStatus = (string) ($slot->get('status') ?? 'Draft');

            if (!in_array($slotStatus, ['Published', 'Covered'], true)) {
                continue;
            }

            $category = (string) ($slot->get('category') ?? '');

            $myInvite = $myInvites[$slot->getId()] ?? null;

            $slotList[] = [
                'id' => $slot->getId(),
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
                'myStatus' => is_array($myInvite) ? ($myInvite['status'] ?? null) : $myInvite,
                'myComment' => is_array($myInvite) ? ($myInvite['comment'] ?? '') : '',
                'allowed' => $competences === [] || in_array($category, $competences, true),
            ];
        }

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

        foreach ($this->getPublishedSlots($offerId) as $slot) {
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

                    $this->entityManager->saveEntity($new);
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

                $this->entityManager->saveEntity($invite);
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

                $this->entityManager->saveEntity($invite);

                $this->syncTaskCollaborator($invite, false);
                $withdrawnCount++;
            }
        }

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

        $slots = $this->getPublishedSlots($offerId);

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

        foreach ($slots as $slot) {
            $slotById[$slot->getId()] = $slot;
        }

        // Pre-fill busy intervals from existing assignments.
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
                $this->entityManager->saveEntity($invite);

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
        $this->entityManager->saveEntity($offer);

        // Recompute uncovered after assignment; mark fully covered shifts.
        $uncovered = [];

        foreach ($this->coverage($offerId)['slots'] as $row) {
            if (!$row['isCovered']) {
                $uncovered[] = (string) $row['name'];
                continue;
            }

            $slotEntity = $this->entityManager->getEntityById('ActivityOfferSlot', (string) $row['id']);

            if ($slotEntity && $slotEntity->get('status') === 'Published') {
                $slotEntity->set('status', 'Covered');
                $this->entityManager->saveEntity($slotEntity);
            }
        }

        return [
            'assignedCount' => $assignedCount,
            'uncovered' => $uncovered,
        ];
    }

    /**
     * Confirm the plan: create Tasks, sync collaborators, notify volunteers.
     *
     * @return array{taskCount: int, confirmedCount: int, notifyCount: int}
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

        $taskCount = 0;
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

            $task = $this->ensureTaskForSlot($offer, $slot);
            $taskCount++;

            $task->loadLinkMultipleField(Field::COLLABORATORS);
            $collaboratorIds = $task->getLinkMultipleIdList(Field::COLLABORATORS);
            $assigneeNames = [];

            foreach ($invites as $invite) {
                $userId = (string) $invite->get('userId');

                if ($invite->get('status') !== self::INVITE_CONFIRMED) {
                    $invite->set('status', self::INVITE_CONFIRMED);
                }

                $invite->set('taskId', $task->getId());
                $this->entityManager->saveEntity($invite);
                $confirmedCount++;

                if (!in_array($userId, $collaboratorIds, true)) {
                    $collaboratorIds[] = $userId;
                }

                $line = $this->shiftEmailService->formatConfirmedShiftLine($slot);
                $shiftsByUser[$userId][] = $line;

                $userEntity = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);
                $assigneeNames[] = $userEntity ? (string) $userEntity->getName() : $userId;
            }

            $task->set(Field::COLLABORATORS . 'Ids', $collaboratorIds);
            $this->entityManager->saveEntity($task);

            $adminDigestLines[] = $this->shiftEmailService->formatConfirmedShiftLine($slot)
                . ' → ' . implode(', ', $assigneeNames);
        }

        $offer->set('status', self::STATUS_CONFIRMED);
        $this->entityManager->saveEntity($offer);

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

            $emailCount += $this->shiftEmailService->sendShiftsConfirmed($offer, $userId, $shiftLines);
        }

        $adminId = (string) ($offer->get('assignedUserId') ?? '');

        if ($adminId !== '' && $adminDigestLines !== []) {
            $emailCount += $this->shiftEmailService->sendAdminDigest($offer, $adminId, $adminDigestLines);
        }

        return [
            'taskCount' => $taskCount,
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
     * Shifts that can receive availability invites (Published only).
     *
     * @return Entity[]
     */
    private function getPublishedSlots(string $offerId): array
    {
        return array_values(array_filter(
            $this->getSlots($offerId),
            static fn (Entity $slot): bool => $slot->get('status') === 'Published'
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

                $this->entityManager->saveEntity($invite, [SaveOption::SILENT => true]);
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

    private function ensureTaskForSlot(Entity $offer, Entity $slot): Entity
    {
        $existingTaskId = $slot->get('taskId');

        if ($existingTaskId) {
            $existing = $this->entityManager->getEntityById('Task', $existingTaskId);

            if ($existing) {
                return $existing;
            }
        }

        $name = $slot->get(Field::NAME);

        if (!$name) {
            $name = trim(
                $this->language->translateOption(
                    (string) ($slot->get('category') ?? ''),
                    'category',
                    'ActivityOfferSlot'
                ) . ' ' . ($slot->get('dateStart') ?? '')
            );
        }

        $descriptionParts = [];
        $placeLabel = $this->formatPlaceAddress($slot);

        if ($placeLabel !== '') {
            $descriptionParts[] = $placeLabel;
        }

        $conditions = $this->normalizeConditions($slot->get('conditions') ?? []);

        if ($conditions !== []) {
            $descriptionParts[] = implode("\n", array_map(
                static fn (string $c): string => '• ' . $c,
                $conditions
            ));
        }

        if ($offer->get('description')) {
            $descriptionParts[] = (string) $offer->get('description');
        }

        $task = $this->entityManager->getNewEntity('Task');
        $task->set([
            'name' => $name,
            'status' => 'Not Started',
            'priority' => 'Normal',
            'dateStart' => $slot->get('dateStart'),
            'dateEnd' => $slot->get('dateEnd'),
            'category' => $slot->get('category'),
            'description' => $descriptionParts !== [] ? implode("\n\n", $descriptionParts) : null,
            'activityOfferId' => $offer->getId(),
            'activityOfferSlotId' => $slot->getId(),
            'assignedUserId' => $offer->get('assignedUserId') ?: $this->user->getId(),
            'teamsIds' => $offer->getLinkMultipleIdList('teams'),
        ]);

        $this->entityManager->saveEntity($task);

        $slot->set('taskId', $task->getId());
        $this->entityManager->saveEntity($slot);

        return $task;
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
