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
use Espo\Modules\NonprofitEspocrm\Tools\StatusGuard;
use DateTimeImmutable;
use DateTimeZone;


/**
 * Shared loaders / ACL / notify helpers for shift planning collaborators.
 */
class ShiftPlanningSupport
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

    public function entityManager(): EntityManager
    {
        return $this->entityManager;
    }

    public function acl(): Acl
    {
        return $this->acl;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function language(): Language
    {
        return $this->language;
    }

    public function shiftEmailService(): ShiftEmailService
    {
        return $this->shiftEmailService;
    }

    public function shiftChangeNotifyService(): ShiftChangeNotifyService
    {
        return $this->shiftChangeNotifyService;
    }

    public function shiftCoverageSyncService(): ShiftCoverageSyncService
    {
        return $this->shiftCoverageSyncService;
    }

    /**
     * @throws NotFound
     */
    public function getOffer(string $offerId): Entity
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
    /**
     * @throws Forbidden|NotFound
     */
    public function getOfferForEdit(string $offerId): Entity
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
    /**
     * @return Entity[]
     */
    public function getSlots(string $offerId): array
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
    /**
     * Shifts that can receive availability invites / auto-assignment (Published only).
     *
     * @return Entity[]
     */
    public function getPublishedSlots(string $offerId): array
    {
        return array_values(array_filter(
            $this->getSlots($offerId),
            static fn (Entity $slot): bool => $slot->get('status') === 'Published'
        ));
    }

    /**
     * Shifts volunteers can still answer on (same filter as availabilityGrid).
     * Covered stays respondable so withdraw / re-request are not silent no-ops
     * after autoAssign flips Published → Covered. Auto-assign stays Published-only.
     *
     * @return Entity[]
     */
    public function getRespondableSlots(string $offerId): array
    {
        return array_values(array_filter(
            $this->getSlots($offerId),
            static fn (Entity $slot): bool => in_array(
                (string) $slot->get('status'),
                ['Published', 'Covered'],
                true
            )
        ));
    }

    /**
     * Open shifts for selective pack resend (not Cancelled / Completed).
     *
     * @return Entity[]
     */
    public function getResendableSlots(string $offerId): array
    {
        return $this->getRespondableSlots($offerId);
    }

    /**
     * @return Entity[]
     */
    /**
     * @return Entity[]
     */
    public function getInvites(string $offerId, ?string $userId = null): array
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
    /**
     * @return string[]
     */
    public function resolveCohortUserIds(Entity $offer): array
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
    /**
     * Users with Available / Assigned / Confirmed invites on this plan.
     *
     * @return list<string>
     */
    public function resolveInvolvedUserIds(string $offerId): array
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
    /**
     * @return string[]
     */
    public function getUserCompetences(User $user): array
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
    /**
     * @param array<int, array{0: string, 1: string}> $intervals
     */
    public function overlapsBusy(array $intervals, string $start, string $end): bool
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
    public function syncTaskCollaborator(Entity $invite, bool $add): void
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
    /**
     * @return array<string, string>
     */
    public function loadUserNames(string $offerId): array
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
    /**
     * @param string[] $userIds
     */
    public function notifyUsers(Entity $offer, array $userIds, string $message): int
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
    public function createNotification(Entity $offer, string $userId, string $message): void
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
    /**
     * @param array<string, string> $params
     */
    public function translateMessage(string $key, array $params): string
    {
        $message = $this->language->translateLabel($key, 'messages', 'ActivityOffer');

        foreach ($params as $name => $value) {
            $message = str_replace('{' . $name . '}', $value, $message);
        }

        return $message;
    }
    public function formatPlaceAddress(Entity $slot): string
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
    public function formatPlaceLabel(Entity $entity): string
    {
        return $this->formatPlaceAddress($entity);
    }

    /**
     * @param mixed $raw
     * @return string[]
     */
    /**
     * @param mixed $raw
     * @return string[]
     */
    public function normalizeConditions(mixed $raw): array
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
    /**
     * Persist entity allowing system-managed status writes.
     *
     * @param array<string, mixed> $options
     */
    public function saveEntityAllowStatus(Entity $entity, array $options = []): void
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
    public function isPlanTerminal(string $status): bool
    {
        return in_array($status, [self::STATUS_COMPLETED, self::STATUS_CLOSED], true);
    }

    /**
     * @return string[]
     */
    /**
     * @return string[]
     */
    public function getStaffedInviteUserIds(Entity $slot): array
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
    /**
     * @return Entity[]
     */
    public function getInvitesForSlot(string $slotId): array
    {
        $collection = $this->entityManager
            ->getRDBRepository('ActivityInvite')
            ->where(['activityOfferSlotId' => $slotId])
            ->find();

        return iterator_to_array($collection);
    }
    public function cancelInvitesOnSlot(Entity $slot): void
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
    /**
     * @param string[] $userIds
     * @param string[] $shiftLines
     * @return array{notifyCount: int, emailCount: int}
     */
    public function notifyUsersSlotCancellation(Entity $offer, array $userIds, array $shiftLines): array
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
    public function extractClockTime(string $datetime): ?string
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
    public function normalizeTime(string $value): ?string
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
    public function dateForWeekday(string $weekStart, string $dayOfWeek): string
    {
        $base = new DateTimeImmutable($weekStart . ' 00:00:00');
        $offset = self::DAY_OFFSET[$dayOfWeek];

        return $base->modify("+{$offset} days")->format('Y-m-d');
    }
    public function formatDateIt(string $ymd): string
    {
        try {
            return (new DateTimeImmutable($ymd))->format('d.m.Y');
        } catch (\Throwable) {
            return $ymd;
        }
    }
    public function getUserPlanComment(string $offerId, string $userId): string
    {
        foreach ($this->getInvites($offerId, $userId) as $invite) {
            $comment = trim((string) ($invite->get('comment') ?? ''));

            if ($comment !== '') {
                return $comment;
            }
        }

        return '';
    }
    public function nowString(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
