<?php

namespace Espo\Modules\VolunteerActivityDispatch\Tools;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Field\LinkParent;
use Espo\Core\Name\Field;
use Espo\Entities\Notification;
use Espo\Entities\Team;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Publish ActivityOffer: slots → Tasks + ActivityInvite per invitee + in-app notifications.
 */
class PublishService
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user,
    ) {}

    /**
     * @return array{taskCount: int, inviteCount: int, notifyCount: int}
     *
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     * @throws Error
     */
    public function publish(string $offerId): array
    {
        $offer = $this->entityManager->getEntityById('ActivityOffer', $offerId);

        if (!$offer) {
            throw new NotFound("ActivityOffer not found.");
        }

        if (!$this->acl->check($offer, Acl\Table::ACTION_EDIT)) {
            throw new Forbidden("No edit access to ActivityOffer.");
        }

        if ($offer->get('status') !== 'Draft') {
            throw new BadRequest("Only Draft offers can be published.");
        }

        $slots = $this->entityManager
            ->getRDBRepository('ActivityOfferSlot')
            ->where(['activityOfferId' => $offerId])
            ->order('dateStart')
            ->find();

        if ($slots->count() === 0) {
            throw new BadRequest("Add at least one slot before publishing.");
        }

        $inviteeIds = $this->resolveInviteeUserIds($offer);

        if ($inviteeIds === []) {
            throw new BadRequest("Select invitee users and/or teams before publishing.");
        }

        $taskCount = 0;
        $inviteCount = 0;
        $tasks = [];

        foreach ($slots as $slot) {
            $task = $this->ensureTaskForSlot($offer, $slot);
            $tasks[] = $task;
            $taskCount++;

            foreach ($inviteeIds as $userId) {
                if ($this->ensureInvite($offer, $task, $userId)) {
                    $inviteCount++;
                }
            }
        }

        $offer->set('status', 'Published');
        $offer->set('publishedAt', (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s'));
        $this->entityManager->saveEntity($offer);

        $notifyCount = $this->notifyInvitees($offer, $inviteeIds, $tasks);

        return [
            'taskCount' => $taskCount,
            'inviteCount' => $inviteCount,
            'notifyCount' => $notifyCount,
        ];
    }

    /**
     * @return string[]
     */
    private function resolveInviteeUserIds(Entity $offer): array
    {
        $ids = $offer->getLinkMultipleIdList('inviteeUsers');

        $teamIds = $offer->getLinkMultipleIdList('inviteTeams');

        foreach ($teamIds as $teamId) {
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

        $ids = array_values(array_unique(array_filter($ids)));

        // Never invite the publisher unless they explicitly listed themselves.
        return $ids;
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

        $name = $slot->get('name');

        if (!$name) {
            $name = trim(($slot->get('category') ?? 'Activity') . ' ' . ($slot->get('dateStart') ?? ''));
        }

        $descriptionParts = [];

        if ($slot->get('place')) {
            $descriptionParts[] = 'Place: ' . $slot->get('place');
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

    private function ensureInvite(Entity $offer, Entity $task, string $userId): bool
    {
        $existing = $this->entityManager
            ->getRDBRepository('ActivityInvite')
            ->where([
                'taskId' => $task->getId(),
                'userId' => $userId,
            ])
            ->findOne();

        if ($existing) {
            return false;
        }

        /** @var ?User $invitee */
        $invitee = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);
        $userName = $invitee?->get('name') ?? $userId;

        $invite = $this->entityManager->getNewEntity('ActivityInvite');
        $invite->set([
            'name' => $task->get('name') . ' · ' . $userName,
            'taskId' => $task->getId(),
            'userId' => $userId,
            'activityOfferId' => $offer->getId(),
            'status' => 'Pending',
        ]);

        $this->entityManager->saveEntity($invite);

        return true;
    }

    /**
     * @param string[] $inviteeIds
     * @param Entity[] $tasks
     */
    private function notifyInvitees(Entity $offer, array $inviteeIds, array $tasks): int
    {
        $count = 0;
        $weekStart = $offer->get('weekStart') ?? '';
        $taskNames = array_map(
            static fn (Entity $t): string => (string) ($t->get(Field::NAME) ?? ''),
            $tasks
        );
        $preview = implode(', ', array_slice(array_filter($taskNames), 0, 5));

        $message = sprintf(
            'New volunteer week offer "%s" (%s): %d activities. Open Tasks to Accept or Decline. %s',
            $offer->get(Field::NAME) ?? '',
            $weekStart,
            count($tasks),
            $preview
        );

        foreach ($inviteeIds as $userId) {
            if ($userId === $this->user->getId()) {
                continue;
            }

            $notification = $this->entityManager->getRDBRepositoryByClass(Notification::class)->getNew();
            $notification
                ->setType(Notification::TYPE_MESSAGE)
                ->setUserId($userId)
                ->setMessage($message)
                ->setRelated(LinkParent::create('ActivityOffer', $offer->getId()));

            $this->entityManager->saveEntity($notification);
            $count++;
        }

        return $count;
    }
}
