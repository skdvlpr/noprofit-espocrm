<?php
/**
 * Smoke: ActivityOffer publish → Tasks + ActivityInvite + accept collaborators.
 *
 * Usage: ddev exec php bin/smoke-activity-offer.php
 */

require __DIR__ . '/lib/refuse-production.php';

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Acl;
use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\User;
use Espo\Modules\VolunteerActivityDispatch\Tools\InviteResponseService;
use Espo\Modules\VolunteerActivityDispatch\Tools\PublishService;

$app = new Application();
$app->setupSystemUser();

$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);
/** @var Acl $acl */
$acl = $container->getByClass(Acl::class);
/** @var User $systemUser */
$systemUser = $container->getByClass(User::class);

$fail = static function (string $msg): never {
    fwrite(STDERR, "FAIL: $msg\n");
    exit(1);
};

$ok = static function (string $msg): void {
    echo "OK: $msg\n";
};

$users = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where([
        'isActive' => true,
        'type' => ['admin', 'regular'],
    ])
    ->limit(0, 2)
    ->find();

$userList = iterator_to_array($users);

if (count($userList) < 2) {
    $fail('Need at least 2 active users for invite smoke.');
}

$manager = $userList[0];
$invitee = $userList[1];

$weekStart = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
$slotStart = (new DateTimeImmutable('monday this week 10:00'))->format('Y-m-d H:i:s');
$slotEnd = (new DateTimeImmutable('monday this week 12:00'))->format('Y-m-d H:i:s');

$offer = $em->getNewEntity('ActivityOffer');
$offer->set([
    'name' => 'Smoke week ' . $weekStart,
    'weekStart' => $weekStart,
    'status' => 'Draft',
    'description' => 'smoke-activity-offer',
    'inviteeUsersIds' => [$invitee->getId()],
    'assignedUserId' => $manager->getId(),
]);
$em->saveEntity($offer);
$ok('Created ActivityOffer ' . $offer->getId());

$slot = $em->getNewEntity('ActivityOfferSlot');
$slot->set([
    'activityOfferId' => $offer->getId(),
    'dateStart' => $slotStart,
    'dateEnd' => $slotEnd,
    'category' => 'MealDistribution',
    'place' => 'Smoke kitchen',
]);
$em->saveEntity($slot);
$ok('Created ActivityOfferSlot ' . $slot->getId() . ' name=' . $slot->get('name'));

$publish = new PublishService($em, $acl, $systemUser);
$result = $publish->publish($offer->getId());

if (($result['taskCount'] ?? 0) < 1) {
    $fail('Expected taskCount >= 1, got ' . json_encode($result));
}

if (($result['inviteCount'] ?? 0) < 1) {
    $fail('Expected inviteCount >= 1, got ' . json_encode($result));
}

$ok('Published: ' . json_encode($result));

$offer = $em->getEntityById('ActivityOffer', $offer->getId());

if ($offer->get('status') !== 'Published') {
    $fail('Offer status not Published');
}

$task = $em->getRDBRepository('Task')
    ->where(['activityOfferId' => $offer->getId()])
    ->findOne();

if (!$task) {
    $fail('No Task created for offer');
}

$ok('Task ' . $task->getId() . ' category=' . $task->get('category'));

$invite = $em->getRDBRepository('ActivityInvite')
    ->where([
        'taskId' => $task->getId(),
        'userId' => $invitee->getId(),
    ])
    ->findOne();

if (!$invite) {
    $fail('No ActivityInvite for invitee');
}

$respond = new InviteResponseService($em, $acl, $invitee);
$respond->accept($invite->getId());

$invite = $em->getEntityById('ActivityInvite', $invite->getId());

if ($invite->get('status') !== 'Accepted') {
    $fail('Invite not Accepted');
}

$task = $em->getEntityById('Task', $task->getId());
$collabIds = $task->getLinkMultipleIdList('collaborators');

if (!in_array($invitee->getId(), $collabIds, true)) {
    $fail('Invitee not in Task.collaborators after accept');
}

$ok('Accept synced collaborators');

$respond->decline($invite->getId());
$task = $em->getEntityById('Task', $task->getId());
$collabIds = $task->getLinkMultipleIdList('collaborators');

if (in_array($invitee->getId(), $collabIds, true)) {
    $fail('Invitee still in collaborators after decline');
}

$ok('Decline removed collaborator');

$em->removeEntity($invite);
$em->removeEntity($slot);
$em->removeEntity($task);
$em->removeEntity($offer);

$ok('Cleanup done');
echo "ALL PASSED\n";
