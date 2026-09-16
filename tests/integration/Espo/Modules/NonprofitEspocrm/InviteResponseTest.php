<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Core\Name\Field;
use Espo\Modules\NonprofitEspocrm\Hooks\ActivityInvite\ProtectInviteMutation;
use Espo\Modules\NonprofitEspocrm\Tools\InviteResponseService;
use Espo\ORM\Repository\Option\SaveOption;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * ActivityInvite accept/decline via InviteResponseService.
 */
class InviteResponseTest extends SafehouseBaseTestCase
{
    public function testAcceptAndDeclineInviteUpdatesStatus(): void
    {
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);

        $invitee = $this->createUser([
            'userName' => 'phpunit_invitee_' . bin2hex(random_bytes(2)),
            'firstName' => 'PHPUnit',
            'lastName' => 'Invitee',
            'type' => 'regular',
            'isActive' => true,
        ]);

        $invite = $this->createInviteFixture($invitee->getId(), 'Assigned');

        $service = $factory->createWith(InviteResponseService::class, [
            'user' => $invitee,
        ]);

        $accepted = $service->accept($invite->getId());
        $this->assertSame(InviteResponseService::STATUS_ACCEPTED, $accepted->get('status'));
        $this->assertNotEmpty($accepted->get('respondedAt'));

        $declined = $service->decline($invite->getId());
        $this->assertSame(InviteResponseService::STATUS_DECLINED, $declined->get('status'));
    }

    public function testDirectInviteCreateAndTaskIdHijackAreForbidden(): void
    {
        $em = $this->getEntityManager();

        $invitee = $this->createUser([
            'userName' => 'phpunit_hijack_' . bin2hex(random_bytes(2)),
            'firstName' => 'PHPUnit',
            'lastName' => 'Hijack',
            'type' => 'regular',
            'isActive' => true,
        ]);

        $blockedCreate = false;
        $rogue = $em->getNewEntity('ActivityInvite');
        $rogue->set([
            'name' => 'Rogue self-invite',
            'userId' => $invitee->getId(),
            'status' => 'Available',
        ]);

        try {
            $em->saveEntity($rogue);
        } catch (Forbidden) {
            $blockedCreate = true;
        }

        $this->assertTrue($blockedCreate, 'direct ActivityInvite create must be forbidden');

        $invite = $this->createInviteFixture($invitee->getId(), 'Available');

        $victimTask = $em->getNewEntity('Task');
        $victimTask->set([
            'name' => 'PHPUnit victim task',
            'status' => 'Not Started',
        ]);
        $em->saveEntity($victimTask, [SaveOption::SKIP_ALL => true, SaveOption::SILENT => true]);

        $blockedHijack = false;

        try {
            $invite->set('taskId', $victimTask->getId());
            $em->saveEntity($invite);
        } catch (Forbidden) {
            $blockedHijack = true;
            $invite->set('taskId', $invite->getFetched('taskId'));
        }

        $this->assertTrue($blockedHijack, 'ActivityInvite.taskId mutation must be forbidden');
    }

    public function testAcceptFromAvailableDoesNotGrantCollaborators(): void
    {
        $em = $this->getEntityManager();
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);

        $invitee = $this->createUser([
            'userName' => 'phpunit_unassigned_' . bin2hex(random_bytes(2)),
            'firstName' => 'PHPUnit',
            'lastName' => 'Unassigned',
            'type' => 'regular',
            'isActive' => true,
        ]);

        $victimTask = $em->getNewEntity('Task');
        $victimTask->set([
            'name' => 'PHPUnit unassigned victim',
            'status' => 'Not Started',
        ]);
        $em->saveEntity($victimTask, [SaveOption::SKIP_ALL => true, SaveOption::SILENT => true]);

        $invite = $this->createInviteFixture($invitee->getId(), 'Available');
        $invite->set('taskId', $victimTask->getId());
        $em->saveEntity($invite, [
            SaveOption::SKIP_ALL => true,
            SaveOption::SILENT => true,
            ProtectInviteMutation::SAVE_OPTION => true,
        ]);

        $service = $factory->createWith(InviteResponseService::class, [
            'user' => $invitee,
        ]);

        $blockedAccept = false;

        try {
            $service->accept($invite->getId());
        } catch (Forbidden) {
            $blockedAccept = true;
        }

        $this->assertTrue($blockedAccept, 'Accept from Available must be forbidden');

        $victimTask = $em->getEntityById('Task', $victimTask->getId());
        $this->assertNotNull($victimTask);
        $victimTask->loadLinkMultipleField(Field::COLLABORATORS);
        $this->assertNotContains(
            $invitee->getId(),
            $victimTask->getLinkMultipleIdList(Field::COLLABORATORS)
        );
    }

    private function createInviteFixture(string $userId, string $status): \Espo\ORM\Entity
    {
        $em = $this->getEntityManager();

        $offer = $em->getNewEntity('ActivityOffer');
        $offer->set([
            'name' => 'PHPUnit Shift Week',
            'weekStart' => date('Y-m-d', strtotime('monday this week')),
            'status' => 'CollectingAvailability',
        ]);
        $em->saveEntity($offer, [SaveOption::SKIP_ALL => true, SaveOption::SILENT => true]);

        $slot = $em->getNewEntity('ActivityOfferSlot');
        $slot->set([
            'name' => 'PHPUnit Slot',
            'activityOfferId' => $offer->getId(),
            'category' => 'MealPreparation',
            'dateStart' => date('Y-m-d 09:00:00'),
            'dateEnd' => date('Y-m-d 12:00:00'),
            'status' => 'Published',
        ]);
        $em->saveEntity($slot, [SaveOption::SKIP_ALL => true, SaveOption::SILENT => true]);

        $invite = $em->getNewEntity('ActivityInvite');
        $invite->set([
            'userId' => $userId,
            'activityOfferId' => $offer->getId(),
            'activityOfferSlotId' => $slot->getId(),
            'status' => $status,
        ]);
        $em->saveEntity($invite, [SaveOption::SKIP_ALL => true, SaveOption::SILENT => true]);

        return $invite;
    }
}
