<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\InjectableFactory;
use Espo\Modules\NonprofitEspocrm\Tools\InviteResponseService;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * ActivityInvite accept/decline via InviteResponseService.
 */
class InviteResponseTest extends SafehouseBaseTestCase
{
    public function testAcceptAndDeclineInviteUpdatesStatus(): void
    {
        $em = $this->getEntityManager();
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);

        $invitee = $this->createUser([
            'userName' => 'phpunit_invitee_' . bin2hex(random_bytes(2)),
            'firstName' => 'PHPUnit',
            'lastName' => 'Invitee',
            'type' => 'regular',
            'isActive' => true,
        ]);

        $offer = $em->getNewEntity('ActivityOffer');
        $offer->set([
            'name' => 'PHPUnit Shift Week',
            'weekStart' => date('Y-m-d', strtotime('monday this week')),
            'status' => 'CollectingAvailability',
        ]);
        $em->saveEntity($offer);

        $slot = $em->getNewEntity('ActivityOfferSlot');
        $slot->set([
            'name' => 'PHPUnit Slot',
            'activityOfferId' => $offer->getId(),
            'category' => 'MealPreparation',
            'dateStart' => date('Y-m-d 09:00:00'),
            'dateEnd' => date('Y-m-d 12:00:00'),
            'status' => 'Published',
        ]);
        $em->saveEntity($slot);

        $invite = $em->getNewEntity('ActivityInvite');
        $invite->set([
            'userId' => $invitee->getId(),
            'activityOfferId' => $offer->getId(),
            'activityOfferSlotId' => $slot->getId(),
            'status' => 'Available',
        ]);
        $em->saveEntity($invite);

        $service = $factory->createWith(InviteResponseService::class, [
            'user' => $invitee,
        ]);

        $accepted = $service->accept($invite->getId());
        $this->assertSame(InviteResponseService::STATUS_ACCEPTED, $accepted->get('status'));
        $this->assertNotEmpty($accepted->get('respondedAt'));

        $declined = $service->decline($invite->getId());
        $this->assertSame(InviteResponseService::STATUS_DECLINED, $declined->get('status'));
    }
}
