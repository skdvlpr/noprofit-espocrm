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


class WeekSlotSynchronizer
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
        $offer = $this->support->getOfferForEdit($offerId);
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

        $existingSlots = $this->support->getSlots($offerId);
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

            $categoryLabel = $this->support->language()->translateOption($category, 'category', 'ActivityOfferSlot');

            if ($categoryLabel === $category) {
                $categoryLabel = $this->support->language()->translateOption($category, 'category', 'ActivityOffer');
            }

            $timeStart = $this->support->normalizeTime((string) ($row['timeStart'] ?? ''));
            $timeEnd = $this->support->normalizeTime((string) ($row['timeEnd'] ?? ''));
            $isAllDay = !empty($row['isAllDay']);

            if ($isAllDay) {
                $timeStart = '00:00';
                $timeEnd = '23:59';
            }

            if ($timeStart === null || $timeEnd === null) {
                throw new BadRequest("Start and end time are required for each shift.");
            }

            $requiredCount = max(0, min(99, (int) ($row['requiredCount'] ?? 1)));
            $conditions = $this->support->normalizeConditions($row['conditions'] ?? []);

            $place = $uniqueAddress
                ? $batchPlace
                : [
                    'placeStreet' => trim((string) ($row['placeStreet'] ?? $row['place'] ?? '')),
                    'placeCity' => trim((string) ($row['placeCity'] ?? '')),
                    'placeState' => trim((string) ($row['placeState'] ?? '')),
                    'placeCountry' => trim((string) ($row['placeCountry'] ?? '')),
                    'placePostalCode' => trim((string) ($row['placePostalCode'] ?? '')),
                ];

            $date = $this->support->dateForWeekday($weekStart, $dayOfWeek);
            $dateStart = $date . ' ' . $timeStart . ':00';
            $dateEnd = $date . ' ' . $timeEnd . ':00';

            if ($dateEnd <= $dateStart) {
                throw new BadRequest("End time must be after start time for {$dayOfWeek}.");
            }

            $placePart = $place['placeStreet'] !== ''
                ? $place['placeStreet']
                : $place['placeCity'];

            $name = $placePart !== ''
                ? "{$categoryLabel} | {$this->support->formatDateIt($date)} | {$placePart}"
                : "{$categoryLabel} | {$this->support->formatDateIt($date)}";

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
                ? $this->support->entityManager()->getEntityById('ActivityOfferSlot', $existingId)
                : null;

            if ($slot && $slot->get('activityOfferId') !== $offerId) {
                $slot = null;
            }

            if (!$slot) {
                $slot = $this->support->entityManager()->getNewEntity('ActivityOfferSlot');
                $createdCount++;
            }

            $slot->set($payload);
            $this->support->saveEntityAllowStatus($slot);
            $keptIds[] = $slot->getId();
        }

        if (!$append) {
            foreach ($existingSlots as $existing) {
                if (!in_array($existing->getId(), $keptIds, true)) {
                    $this->support->entityManager()->removeEntity($existing);
                }
            }
        }

        $total = $append
            ? count($this->support->getSlots($offerId))
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

        $offer = $this->support->entityManager()->getEntityById('ActivityOffer', $offerId);

        if (!$offer) {
            return;
        }

        $weekStart = (string) ($offer->get('weekStart') ?? '');

        if ($weekStart === '') {
            return;
        }

        $date = $this->support->dateForWeekday($weekStart, $dayOfWeek);

        $startTime = $this->support->extractClockTime((string) ($slot->get('dateStart') ?? ''));
        $endTime = $this->support->extractClockTime((string) ($slot->get('dateEnd') ?? ''));

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
}

