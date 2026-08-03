<?php

namespace Espo\Modules\VolunteerActivityDispatch\Tools;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Name\Field;
use Espo\Entities\User;
use Espo\Modules\VolunteerActivityDispatch\Hooks\ActivityInvite\ProtectInviteMutation;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Accept / Decline ActivityInvite and sync Task.collaborators.
 */
class InviteResponseService
{
    public const STATUS_ACCEPTED = 'Confirmed';
    public const STATUS_DECLINED = 'Declined';

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user,
    ) {}

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function accept(string $inviteId): Entity
    {
        return $this->respond($inviteId, self::STATUS_ACCEPTED);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function decline(string $inviteId): Entity
    {
        return $this->respond($inviteId, self::STATUS_DECLINED);
    }

    /**
     * Respond for the current user on a Task invite.
     *
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function respondForTask(string $taskId, string $status): Entity
    {
        $invite = $this->entityManager
            ->getRDBRepository('ActivityInvite')
            ->where([
                'taskId' => $taskId,
                'userId' => $this->user->getId(),
            ])
            ->findOne();

        if (!$invite) {
            throw new NotFound("No invite for this Task.");
        }

        return $this->respond($invite->getId(), $status);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    private function respond(string $inviteId, string $status): Entity
    {
        // Legacy client value from the pre-shift-planning flow.
        if ($status === 'Accepted') {
            $status = self::STATUS_ACCEPTED;
        }

        if (!in_array($status, [self::STATUS_ACCEPTED, self::STATUS_DECLINED], true)) {
            throw new BadRequest("Invalid status.");
        }

        $invite = $this->entityManager->getEntityById('ActivityInvite', $inviteId);

        if (!$invite) {
            throw new NotFound("ActivityInvite not found.");
        }

        $inviteeId = $invite->get('userId');

        $isOwner = $inviteeId === $this->user->getId();
        $canEdit = $this->acl->check($invite, Acl\Table::ACTION_EDIT);

        if (!$isOwner && !$canEdit) {
            throw new Forbidden("Cannot respond to this invite.");
        }

        // Non-admin may only respond to their own invite.
        if (!$this->user->isAdmin() && !$isOwner) {
            throw new Forbidden("You can only respond to your own invite.");
        }

        if ($invite->get('status') === $status) {
            return $invite;
        }

        $previousStatus = (string) ($invite->getFetched('status') ?? $invite->get('status') ?? '');

        // Accept only after organizer assignment (or re-accept after decline).
        // Available→Confirmed with a hijacked taskId must not grant collaborators.
        if (
            $status === self::STATUS_ACCEPTED &&
            !in_array($previousStatus, ['Assigned', self::STATUS_ACCEPTED, self::STATUS_DECLINED], true)
        ) {
            throw new Forbidden("This availability has not been assigned.");
        }

        $invite->set('status', $status);
        $invite->set(
            'respondedAt',
            (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s')
        );
        $this->entityManager->saveEntity($invite, [
            ProtectInviteMutation::SAVE_OPTION => true,
        ]);

        $taskId = $invite->get('taskId');

        if ($taskId && $inviteeId) {
            $this->syncCollaborator($taskId, $inviteeId, $status === self::STATUS_ACCEPTED);
        }

        return $invite;
    }

    private function syncCollaborator(string $taskId, string $userId, bool $add): void
    {
        $task = $this->entityManager->getEntityById('Task', $taskId);

        if (!$task) {
            return;
        }

        $ids = $task->getLinkMultipleIdList(Field::COLLABORATORS);

        if ($add) {
            if (!in_array($userId, $ids, true)) {
                $ids[] = $userId;
                $task->set(Field::COLLABORATORS . 'Ids', $ids);
                $this->entityManager->saveEntity($task);
            }

            return;
        }

        $filtered = array_values(array_filter($ids, static fn (string $id): bool => $id !== $userId));

        if (count($filtered) !== count($ids)) {
            $task->set(Field::COLLABORATORS . 'Ids', $filtered);
            $this->entityManager->saveEntity($task);
        }
    }
}
