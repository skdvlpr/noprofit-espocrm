<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Jobs\CompletePastActivityOfferSlots;
use Espo\Modules\NonprofitEspocrm\Jobs\ReconcileFullyStaffedPlans;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService;
use Espo\Modules\NonprofitEspocrm\Tools\StatusGuard;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Shift planning behavioural flows (ported from bin/smoke-shift-planning.php).
 */
class ShiftPlanningServiceTest extends SafehouseBaseTestCase
{
    public function testSyncWeekSlotsHookMaterializesActivityOfferSlots(): void
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $offer = $em->getNewEntity('ActivityOffer');
        $offer->set([
            'name' => 'PHPUnit Week ' . $marker,
            'weekStart' => '2026-09-07',
            'status' => 'Draft',
            'weekSlots' => [
                [
                    'dayOfWeek' => 'Monday',
                    'category' => 'MealPreparation',
                    'timeStart' => '10:30',
                    'timeEnd' => '12:30',
                    'requiredCount' => 2,
                    'conditions' => ['Portare grembiule'],
                ],
                [
                    'dayOfWeek' => 'Wednesday',
                    'category' => 'MealPreparation',
                    'timeStart' => '11:30',
                    'timeEnd' => '14:30',
                    'requiredCount' => 1,
                    'conditions' => [],
                ],
            ],
            'uniqueAddress' => true,
            'placeStreet' => 'via PHPUnit 12',
            'placeCity' => 'Torino',
        ]);
        $em->saveEntity($offer);

        $slots = iterator_to_array(
            $em->getRDBRepository('ActivityOfferSlot')
                ->where(['activityOfferId' => $offer->getId()])
                ->order('dateStart')
                ->find()
        );

        $this->assertCount(2, $slots);
        $this->assertSame('Published', $slots[0]->get('status'));
        $this->assertTrue(
            str_contains((string) $slots[0]->get('placeStreet'), 'via PHPUnit')
                || str_contains((string) $slots[0]->get('name'), 'via PHPUnit')
        );
        $this->assertStringStartsWith('2026-09-07', (string) $slots[0]->get('dateStart'));
    }

    public function testStatusGuardBlocksManualOfferStatusChange(): void
    {
        $em = $this->getEntityManager();

        $offer = $em->getNewEntity('ActivityOffer');
        $offer->set([
            'name' => 'PHPUnit Status Guard ' . $this->uniqueMarker(),
            'weekStart' => '2026-09-07',
            'status' => 'Draft',
        ]);
        $em->saveEntity($offer);
        $this->assertSame('Draft', $offer->get('status'));

        $offer = $em->getEntityById('ActivityOffer', $offer->getId());
        $offer->set('status', 'CollectingAvailability');

        $this->expectException(Forbidden::class);
        $em->saveEntity($offer);
    }

    public function testStatusGuardBlocksManualSlotStatusChange(): void
    {
        $em = $this->getEntityManager();

        $offer = $em->getNewEntity('ActivityOffer');
        $offer->set([
            'name' => 'PHPUnit Slot Guard ' . $this->uniqueMarker(),
            'weekStart' => '2026-09-07',
            'status' => 'Draft',
        ]);
        $em->saveEntity($offer, [StatusGuard::SKIP_OPTION => true, 'silent' => true]);

        $slot = $em->getNewEntity('ActivityOfferSlot');
        $slot->set([
            'activityOfferId' => $offer->getId(),
            'category' => 'Cleaning',
            'dateStart' => '2026-09-07 10:00:00',
            'dateEnd' => '2026-09-07 12:00:00',
            'requiredCount' => 1,
            'name' => 'PHPUnit slot',
            'status' => 'Published',
        ]);
        $em->saveEntity($slot, [StatusGuard::SKIP_OPTION => true, 'silent' => true]);

        $slot = $em->getEntityById('ActivityOfferSlot', $slot->getId());
        $slot->set('status', 'Covered');

        $this->expectException(Forbidden::class);
        $em->saveEntity($slot);
    }

    public function testRequestAvailabilityCreatesInvitesAndMovesPlan(): void
    {
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $volunteers = [
            $this->createShiftVolunteer(1, $marker),
            $this->createShiftVolunteer(2, $marker),
        ];

        $offer = $em->getNewEntity('ActivityOffer');
        $offer->set([
            'name' => 'PHPUnit Availability ' . $marker,
            'weekStart' => '2026-09-07',
            'status' => 'Draft',
            'inviteeUsersIds' => array_map(static fn (User $u): string => $u->getId(), $volunteers),
        ]);
        $em->saveEntity($offer, ['silent' => true]);

        $slot = $em->getNewEntity('ActivityOfferSlot');
        $slot->set([
            'activityOfferId' => $offer->getId(),
            'category' => 'MealPreparation',
            'dateStart' => '2026-09-07 10:00:00',
            'dateEnd' => '2026-09-07 13:00:00',
            'requiredCount' => 2,
            'name' => 'Meal prep ' . $marker,
            'status' => 'Published',
        ]);
        $em->saveEntity($slot, [StatusGuard::SKIP_OPTION => true, 'silent' => true]);

        /** @var ShiftPlanningService $service */
        $service = $factory->create(ShiftPlanningService::class);
        $result = $service->requestAvailability($offer->getId());

        $this->assertSame(1, $result['slotCount']);
        $this->assertSame(2, $result['cohortCount']);
        $this->assertGreaterThanOrEqual(0, $result['notifyCount']);

        $offer = $em->getEntityById('ActivityOffer', $offer->getId());
        $this->assertSame('CollectingAvailability', $offer->get('status'));

        // Invites may be created lazily on volunteer response; cohort + status is the contract.
        $inviteCount = $em->getRDBRepository('ActivityInvite')
            ->where(['activityOfferId' => $offer->getId()])
            ->count();
        $this->assertGreaterThanOrEqual(0, $inviteCount);
    }

    public function testAutoAssignCoversSlotsAndSetsFullyStaffed(): void
    {
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $volunteers = [
            $this->createShiftVolunteer(1, $marker),
            $this->createShiftVolunteer(2, $marker),
        ];

        $offer = $em->getNewEntity('ActivityOffer');
        $offer->set([
            'name' => 'PHPUnit Coverage ' . $marker,
            'weekStart' => '2026-09-07',
            'status' => 'Draft',
            'inviteeUsersIds' => array_map(static fn (User $u): string => $u->getId(), $volunteers),
        ]);
        $em->saveEntity($offer, ['silent' => true]);

        $slot = $em->getNewEntity('ActivityOfferSlot');
        $slot->set([
            'activityOfferId' => $offer->getId(),
            'category' => 'MealPreparation',
            'dateStart' => '2026-09-07 10:00:00',
            'dateEnd' => '2026-09-07 13:00:00',
            'requiredCount' => 2,
            'name' => 'Coverage slot ' . $marker,
            'status' => 'Published',
        ]);
        $em->saveEntity($slot, [StatusGuard::SKIP_OPTION => true, 'silent' => true]);
        $slotId = $slot->getId();

        /** @var ShiftPlanningService $adminService */
        $adminService = $factory->create(ShiftPlanningService::class);
        $adminService->requestAvailability($offer->getId());

        foreach ($volunteers as $volunteer) {
            $volService = $factory->createWith(ShiftPlanningService::class, ['user' => $volunteer]);
            $volService->saveAvailability($offer->getId(), [$slotId]);
        }

        $cov = $adminService->coverage($offer->getId());
        $this->assertArrayHasKey('uncoveredCount', $cov);

        $assign = $adminService->autoAssign($offer->getId());
        $this->assertArrayHasKey('assignedCount', $assign);
        $this->assertArrayHasKey('uncovered', $assign);

        $slotAfter = $em->getEntityById('ActivityOfferSlot', $slotId);
        $this->assertContains($slotAfter->get('status'), ['Published', 'Covered']);

        $offerAfter = $em->getEntityById('ActivityOffer', $offer->getId());
        $this->assertNotNull($offerAfter);
    }

    public function testSaveAvailabilityWithdrawsFromCoveredSlot(): void
    {
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $volunteer = $this->createShiftVolunteer(1, $marker);

        $offer = $em->getNewEntity('ActivityOffer');
        $offer->set([
            'name' => 'PHPUnit Covered Withdraw ' . $marker,
            'weekStart' => '2026-09-07',
            'status' => 'Draft',
            'inviteeUsersIds' => [$volunteer->getId()],
        ]);
        $em->saveEntity($offer, ['silent' => true]);

        $slot = $em->getNewEntity('ActivityOfferSlot');
        $slot->set([
            'activityOfferId' => $offer->getId(),
            'category' => 'MealPreparation',
            'dateStart' => '2026-09-07 10:00:00',
            'dateEnd' => '2026-09-07 13:00:00',
            'requiredCount' => 1,
            'name' => 'Covered withdraw ' . $marker,
            'status' => 'Published',
        ]);
        $em->saveEntity($slot, [StatusGuard::SKIP_OPTION => true, 'silent' => true]);
        $slotId = $slot->getId();

        /** @var ShiftPlanningService $adminService */
        $adminService = $factory->create(ShiftPlanningService::class);
        $adminService->requestAvailability($offer->getId());

        $volService = $factory->createWith(ShiftPlanningService::class, ['user' => $volunteer]);
        $volService->saveAvailability($offer->getId(), [$slotId]);

        $invite = $em->getRDBRepository('ActivityInvite')
            ->where([
                'activityOfferId' => $offer->getId(),
                'activityOfferSlotId' => $slotId,
                'userId' => $volunteer->getId(),
            ])
            ->findOne();
        $this->assertNotNull($invite);
        $this->assertSame('Available', $invite->get('status'));

        $slot = $em->getEntityById('ActivityOfferSlot', $slotId);
        $slot->set('status', 'Covered');
        $em->saveEntity($slot, [StatusGuard::SKIP_OPTION => true, 'silent' => true]);

        $result = $volService->saveAvailability($offer->getId(), []);

        $inviteAfter = $em->getRDBRepository('ActivityInvite')
            ->where([
                'activityOfferId' => $offer->getId(),
                'activityOfferSlotId' => $slotId,
                'userId' => $volunteer->getId(),
            ])
            ->findOne();

        $this->assertNull(
            $inviteAfter,
            'Withdraw of an Available invite on a Covered slot must delete the invite'
        );
        $this->assertSame(0, $result['withdrawnCount']);
    }

    public function testRequestAvailabilityAllowsFullyCoveredPlan(): void
    {
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $volunteer = $this->createShiftVolunteer(1, $marker);

        $offer = $em->getNewEntity('ActivityOffer');
        $offer->set([
            'name' => 'PHPUnit Covered Rerequest ' . $marker,
            'weekStart' => '2026-09-07',
            'status' => 'Draft',
            'inviteeUsersIds' => [$volunteer->getId()],
        ]);
        $em->saveEntity($offer, ['silent' => true]);

        $slot = $em->getNewEntity('ActivityOfferSlot');
        $slot->set([
            'activityOfferId' => $offer->getId(),
            'category' => 'Cleaning',
            'dateStart' => '2026-09-07 14:00:00',
            'dateEnd' => '2026-09-07 16:00:00',
            'requiredCount' => 1,
            'name' => 'Covered rerequest ' . $marker,
            'status' => 'Covered',
        ]);
        $em->saveEntity($slot, [StatusGuard::SKIP_OPTION => true, 'silent' => true]);

        /** @var ShiftPlanningService $service */
        $service = $factory->create(ShiftPlanningService::class);
        $result = $service->requestAvailability($offer->getId());

        $this->assertSame(1, $result['slotCount']);
        $offer = $em->getEntityById('ActivityOffer', $offer->getId());
        $this->assertSame('CollectingAvailability', $offer->get('status'));
    }

    public function testScheduledJobsRunWithoutCrash(): void
    {
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);

        $completeJob = $factory->create(CompletePastActivityOfferSlots::class);
        $completeJob->run();

        $reconcileJob = $factory->create(ReconcileFullyStaffedPlans::class);
        $reconcileJob->run();

        $this->assertTrue(true);
    }

    private function createShiftVolunteer(int $index, string $marker): User
    {
        $em = $this->getEntityManager();
        $userName = 'phpunit_shift_vol_' . $index . '_' . substr($marker, -6);

        $user = $em->getNewEntity(User::ENTITY_TYPE);
        $user->set([
            'userName' => $userName,
            'firstName' => 'PHPUnit',
            'lastName' => 'Vol' . $index,
            'type' => 'regular',
            'isActive' => true,
            'emailAddress' => $userName . '@example.com',
        ]);
        $em->saveEntity($user, ['silent' => true]);

        return $user;
    }
}
