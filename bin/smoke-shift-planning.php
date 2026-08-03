<?php

require __DIR__ . '/lib/refuse-production.php';

/**
 * Smoke test of the VolunteerActivityDispatch shift planning lifecycle.
 *
 * Verifies:
 *   - clientDefs: lifecycle actions exposed as visible header buttons
 *     (menu.detail.buttons) with existing handler file
 *   - i18n: it_IT labels for the lifecycle actions
 *   - requestAvailability: status -> CollectingAvailability, cohort notified
 *   - availabilityGrid + saveAvailability: competence filtering
 *   - coverage: required/available/assigned counts
 *   - autoAssign: fair distribution, no double-booking on overlapping slots
 *   - confirm: tasks + collaborators + notifications, invites -> Confirmed
 *   - post-confirm decline: invite -> Declined, collaborator removed
 *
 * Creates temporary users/offer/slots and deletes them at the end.
 *
 * Usage:
 *   ddev exec php bin/smoke-shift-planning.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

$em = $container->getByClass(\Espo\ORM\EntityManager::class);
$injectableFactory = $container->getByClass(\Espo\Core\InjectableFactory::class);
$metadata = $container->getByClass(\Espo\Core\Utils\Metadata::class);

$fail = 0;

function ok(bool $cond, string $label): void
{
    global $fail;
    echo ($cond ? 'OK  ' : 'FAIL') . ' ' . $label . "\n";

    if (!$cond) {
        $fail++;
    }
}

// --- metadata: visible lifecycle buttons -------------------------------------

$buttons = $metadata->get(['clientDefs', 'ActivityOffer', 'menu', 'detail', 'buttons']) ?? [];
$buttonNames = array_map(fn ($item) => is_object($item) ? ($item->name ?? '') : ($item['name'] ?? ''), $buttons);

foreach (['fillAvailability', 'requestAvailability', 'autoAssign', 'confirmPlan'] as $name) {
    ok(in_array($name, $buttonNames, true), "clientDefs menu.detail.buttons has $name");
}

$handlerFile = __DIR__
    . '/../client/custom/modules/volunteer-activity-dispatch/src/handlers/activity-offer/shift-actions.js';
ok(is_file($handlerFile), 'shift-actions.js handler file exists');

$stale = $metadata->get(['clientDefs', 'ActivityOffer', 'detailActionList']);
ok(empty($stale), 'stale detailActionList removed from clientDefs');

// --- User activityCompetences layout (prod rebuild must inject without Layout\Service) ---

(new \Espo\Modules\VolunteerActivityDispatch\Tools\Installer())
    ->ensureUserCompetencesLayout($container, $injectableFactory);

$userDetailLayout = $injectableFactory
    ->create(\Espo\Tools\LayoutManager\LayoutManager::class)
    ->get('User', 'detail') ?? '';
ok(
    str_contains((string) $userDetailLayout, 'activityCompetences'),
    'User detail layout includes activityCompetences'
);
ok(
    (bool) $metadata->get(['entityDefs', 'User', 'fields', 'activityCompetences']),
    'User.activityCompetences field exists in metadata'
);

$itLabels = json_decode((string) file_get_contents(__DIR__
    . '/../custom/Espo/Modules/VolunteerActivityDispatch/Resources/i18n/it_IT/ActivityOffer.json'), true);

foreach (['Fill availability', 'Request availability', 'Auto assign', 'Confirm plan'] as $label) {
    ok(isset($itLabels['labels'][$label]), "it_IT label present: $label");
}

// --- role access (bug #2: volunteer access to plans) --------------------------

(new \Espo\Modules\VolunteerActivityDispatch\Tools\Installer())->ensureRoleAccess($container);

$volunteerRole = $em->getRDBRepository('Role')->where(['name' => 'Volunteer'])->findOne();
ok($volunteerRole !== null, 'canonical Volunteer role exists');

if ($volunteerRole) {
    $roleData = json_decode(json_encode($volunteerRole->get('data')), true) ?: [];
    ok(($roleData['ActivityOffer']['read'] ?? null) === 'all', 'Volunteer role: ActivityOffer read=all');
    ok(($roleData['ActivityOffer']['edit'] ?? null) === 'no', 'Volunteer role: ActivityOffer edit=no');
    ok(($roleData['ActivityOfferSlot']['read'] ?? null) === 'all', 'Volunteer role: ActivityOfferSlot read=all');
    ok(($roleData['ActivityInvite']['read'] ?? null) === 'own', 'Volunteer role: ActivityInvite read=own');
}

$tabList = $container->getByClass(\Espo\Core\Utils\Config::class)->get('tabList') ?? [];
ok(in_array('ActivityOffer', $tabList, true), 'ActivityOffer present in tabList');

// --- email templates (bug #3: volunteer emails) --------------------------------

(new \Espo\Modules\VolunteerActivityDispatch\Tools\Installer())->ensureEmailTemplates($container);

// Config object was cached before the write — reload it for assertions.
$freshConfig = $injectableFactory->create(\Espo\Core\Utils\Config::class);
$templateIds = $freshConfig->get(\Espo\Modules\VolunteerActivityDispatch\Tools\ShiftEmailService::CONFIG_KEY);
$templateIds = $templateIds ? json_decode(json_encode($templateIds), true) : [];

foreach (['availabilityRequest', 'shiftsConfirmed'] as $kind) {
    $tplId = $templateIds[$kind] ?? null;
    $tpl = $tplId ? $em->getEntityById('EmailTemplate', $tplId) : null;
    ok($tpl !== null, "email template provisioned: $kind");

    if ($tpl) {
        $body = (string) $tpl->get('body');
        ok(str_contains($body, '{recordUrl}'), "email template $kind uses {recordUrl}");
        ok(!str_contains($body, '{planUrl}'), "email template $kind no longer uses {planUrl}");
    }
}

$recordUrlMeta = $metadata->get(['app', 'emailTemplate', 'placeholders', 'recordUrl', 'className']);
ok(is_string($recordUrlMeta) && str_contains($recordUrlMeta, 'RecordUrl'), 'app.emailTemplate.placeholders.recordUrl registered');

$helper = $injectableFactory->create(
    \Espo\Modules\NonprofitEspocrm\Tools\EmailTemplate\TemplatePlaceholderHelper::class
);
$dummy = $em->getNewEntity('ActivityOffer');
$dummy->set('id', 'smokeRecordUrlOffer');
$url = $helper->urlFor($dummy);
ok(str_contains($url, '#ActivityOffer/view/smokeRecordUrlOffer'), 'recordUrl helper builds deep link');
$applied = $helper->applyRecordUrls(
    'A {recordUrl} B {ActivityOffer.recordUrl} C {Missing.recordUrl} D {ActivityOffer.noSuchField}',
    $dummy,
    ['ActivityOffer' => $dummy]
);
ok(str_contains($applied, $url) && !str_contains($applied, '{recordUrl}'), 'applyRecordUrls fills {recordUrl}');
ok(!str_contains($applied, '{Missing.recordUrl}'), 'missing related recordUrl becomes empty');
$cleaned = $helper->clearUnresolvedEntityPlaceholders($applied);
ok(!str_contains($cleaned, '{ActivityOffer.noSuchField}'), 'null/unresolved entity placeholders cleared');

// --- fixtures -----------------------------------------------------------------

// Purge leftovers from previous runs.
foreach ($em->getRDBRepository('ActivityOffer')->where(['name' => ['Smoke Shift Week', 'Smoke Shift Week OLD']])->find() as $old) {
    foreach (['ActivityInvite', 'Task', 'ActivityOfferSlot'] as $type) {
        foreach ($em->getRDBRepository($type)->where(['activityOfferId' => $old->getId()])->find() as $e) {
            $em->removeEntity($e, ['skipAll' => true, 'silent' => true]);
        }
    }
    foreach ($em->getRDBRepository('Notification')->where(['relatedType' => 'ActivityOffer', 'relatedId' => $old->getId()])->find() as $e) {
        $em->removeEntity($e, ['skipAll' => true, 'silent' => true]);
    }
    $em->removeEntity($old, ['skipAll' => true, 'silent' => true]);
}

$volunteers = [];

for ($i = 1; $i <= 3; $i++) {
    $userName = 'smoke_shift_vol' . $i;

    $existing = $em->getRDBRepository('User')->where(['userName' => $userName])->findOne();

    if ($existing) {
        $volunteers[] = $existing;

        continue;
    }

    $u = $em->getNewEntity('User');
    $u->set([
        'userName' => $userName,
        'firstName' => 'Smoke',
        'lastName' => 'Vol' . $i,
        'type' => 'regular',
        'isActive' => true,
        'emailAddress' => $userName . '@smoke.example.com',
    ]);

    // Volunteer 3 only qualified for MealPreparation.
    if ($i === 3) {
        $u->set('activityCompetences', ['MealPreparation']);
    }

    // Normal save (no skipAll): field processing must persist the email
    // address relation so ShiftEmailService can resolve recipient addresses.
    $em->saveEntity($u, ['silent' => true]);

    $volunteers[] = $u;
}

$offer = $em->getNewEntity('ActivityOffer');
$offer->set([
    'name' => 'Smoke Shift Week',
    'weekStart' => '2026-09-07',
    'status' => 'Draft',
]);
$em->saveEntity($offer, ['skipAll' => true, 'silent' => true]);

// linkMultiple is processed by the record service, not the ORM — relate directly.
foreach ($volunteers as $u) {
    $em->getRDBRepository('ActivityOffer')->getRelation($offer, 'inviteeUsers')->relate($u);
}

$slotDefs = [
    ['MealPreparation', '2026-09-07 10:00:00', '2026-09-07 13:00:00', 2],
    ['MealDistribution', '2026-09-07 12:00:00', '2026-09-07 14:00:00', 1],
    ['Cleaning', '2026-09-08 09:00:00', '2026-09-08 11:00:00', 2],
];

$slots = [];

foreach ($slotDefs as [$cat, $start, $end, $req]) {
    $s = $em->getNewEntity('ActivityOfferSlot');
    $s->set([
        'activityOfferId' => $offer->getId(),
        'category' => $cat,
        'dateStart' => $start,
        'dateEnd' => $end,
        'requiredCount' => $req,
        'name' => $cat . ' smoke',
    ]);
    $em->saveEntity($s, ['skipAll' => true, 'silent' => true]);
    $slots[$cat] = $s;
}

// --- live ACL check: volunteer with Volunteer role can read plans --------------

if ($volunteerRole) {
    $em->getRDBRepository('User')->getRelation($volunteers[0], 'roles')->relate($volunteerRole);

    $aclManager = $container->getByClass(\Espo\Core\AclManager::class);
    $volUser = $em->getEntityById('User', $volunteers[0]->getId());

    ok($aclManager->checkScope($volUser, 'ActivityOffer'), 'volunteer user: ActivityOffer scope accessible (navbar tab visible)');
    ok($aclManager->checkEntityRead($volUser, $em->getEntityById('ActivityOffer', $offer->getId())), 'volunteer user: can read a plan record');
    ok($aclManager->checkScope($volUser, 'ActivityOfferSlot'), 'volunteer user: ActivityOfferSlot scope accessible');
    ok(!$aclManager->checkEntityEdit($volUser, $em->getEntityById('ActivityOffer', $offer->getId())), 'volunteer user: cannot edit a plan record');
}

// --- lifecycle ----------------------------------------------------------------

$adminService = $injectableFactory->create(
    \Espo\Modules\VolunteerActivityDispatch\Tools\ShiftPlanningService::class
);

$res = $adminService->requestAvailability($offer->getId());
ok($res['slotCount'] === 3, 'requestAvailability slotCount=3');
ok($res['notifyCount'] === 3, 'requestAvailability notified 3 volunteers');
ok(array_key_exists('emailCount', $res), 'requestAvailability returns emailCount');
echo "  availability emails sent: " . ($res['emailCount'] ?? 0) . "\n";

$offer = $em->getEntityById('ActivityOffer', $offer->getId());
ok($offer->get('status') === 'CollectingAvailability', 'offer -> CollectingAvailability');

$declare = function ($volunteer, array $slotIds) use ($injectableFactory, $offer) {
    $service = $injectableFactory->createWith(
        \Espo\Modules\VolunteerActivityDispatch\Tools\ShiftPlanningService::class,
        ['user' => $volunteer]
    );

    return $service->saveAvailability($offer->getId(), $slotIds);
};

// --- regression: slot re-parented to another plan (stale invite offer link) ----
// Simulate: vol1 declared availability while the slot belonged to an old plan,
// then the slot was moved to this plan. saveAvailability must reuse the
// existing invite (no UNIQ_SLOT_USER duplicate-key 500) and heal the link.

$oldOffer = $em->getNewEntity('ActivityOffer');
$oldOffer->set(['name' => 'Smoke Shift Week OLD', 'weekStart' => '2026-08-31', 'status' => 'CollectingAvailability']);
$em->saveEntity($oldOffer, ['skipAll' => true, 'silent' => true]);

$staleInvite = $em->getNewEntity('ActivityInvite');
$staleInvite->set([
    'name' => 'stale smoke invite',
    'userId' => $volunteers[0]->getId(),
    'activityOfferId' => $oldOffer->getId(),
    'activityOfferSlotId' => $slots['MealPreparation']->getId(),
    'status' => 'Available',
]);
$em->saveEntity($staleInvite, ['skipAll' => true, 'silent' => true]);

try {
    $declare($volunteers[0], [
        $slots['MealPreparation']->getId(),
        $slots['MealDistribution']->getId(),
        $slots['Cleaning']->getId(),
    ]);
    ok(true, 'saveAvailability survives stale invite from re-parented slot');
} catch (\Throwable $e) {
    ok(false, 'saveAvailability survives stale invite from re-parented slot (' . $e->getMessage() . ')');
}

$pairInvites = $em->getRDBRepository('ActivityInvite')->where([
    'activityOfferSlotId' => $slots['MealPreparation']->getId(),
    'userId' => $volunteers[0]->getId(),
])->find();
$pairInvites = iterator_to_array($pairInvites);
ok(count($pairInvites) === 1, 'exactly one invite per slot+user after save');
ok(
    $pairInvites !== [] && $pairInvites[0]->get('activityOfferId') === $offer->getId(),
    'stale invite offer link healed to current plan'
);
$declare($volunteers[1], [
    $slots['MealPreparation']->getId(),
    $slots['Cleaning']->getId(),
]);
$r3 = $declare($volunteers[2], [
    $slots['MealPreparation']->getId(),
    $slots['MealDistribution']->getId(),
    $slots['Cleaning']->getId(),
]);
ok($r3['availableCount'] === 1, 'competence filter: vol3 only MealPreparation accepted');

$grid = $injectableFactory->createWith(
    \Espo\Modules\VolunteerActivityDispatch\Tools\ShiftPlanningService::class,
    ['user' => $volunteers[2]]
)->availabilityGrid($offer->getId());

$gridBySlot = [];
foreach ($grid['slots'] as $row) {
    $gridBySlot[$row['category']] = $row;
}
ok($gridBySlot['MealPreparation']['myStatus'] === 'Available', 'grid shows vol3 available on MealPreparation');
ok($gridBySlot['Cleaning']['allowed'] === false, 'grid marks Cleaning as not allowed for vol3');

$cov = $adminService->coverage($offer->getId());
$covBySlot = [];
foreach ($cov['slots'] as $row) {
    $covBySlot[$row['category']] = $row;
}
ok($covBySlot['MealPreparation']['availableCount'] === 3, 'coverage: 3 available on MealPreparation');
ok($covBySlot['MealDistribution']['availableCount'] === 1, 'coverage: 1 available on MealDistribution');
ok($cov['uncoveredCount'] === 3, 'coverage: all uncovered before assignment');

$assignRes = $adminService->autoAssign($offer->getId());
ok($assignRes['assignedCount'] >= 4, 'autoAssign made >=4 assignments');
ok($assignRes['uncovered'] === [], 'autoAssign covered all slots');

$offer = $em->getEntityById('ActivityOffer', $offer->getId());
ok($offer->get('status') === 'Planned', 'offer -> Planned');

$cov2 = $adminService->coverage($offer->getId());
$assignedNames = [];
foreach ($cov2['slots'] as $row) {
    $assignedNames[$row['category']] = array_map(fn ($u) => $u['name'], $row['assigned']);
}
$prep = $assignedNames['MealPreparation'] ?? [];
$dist = $assignedNames['MealDistribution'] ?? [];
ok(count(array_intersect($prep, $dist)) === 0, 'no volunteer double-booked on overlapping slots');

$confirmRes = $adminService->confirm($offer->getId());
ok($confirmRes['taskCount'] === 3, 'confirm created 3 tasks');
ok($confirmRes['confirmedCount'] === $assignRes['assignedCount'], 'all assigned got confirmed');
echo "  confirmation emails sent: " . ($confirmRes['emailCount'] ?? 0) . "\n";

// Local SMTP may be a real relay that rejects test domains — assert
// consistency (every reported send is stored), not actual delivery.
$sentEmails = $em->getRDBRepository('Email')->where([
    'parentType' => 'ActivityOffer',
    'parentId' => $offer->getId(),
])->count();
$reported = ($res['emailCount'] ?? 0) + ($confirmRes['emailCount'] ?? 0);
ok($sentEmails >= $reported, "stored emails ($sentEmails) cover reported sends ($reported)");

$offer = $em->getEntityById('ActivityOffer', $offer->getId());
ok($offer->get('status') === 'Confirmed', 'offer -> Confirmed');

$slotReloaded = $em->getEntityById('ActivityOfferSlot', $slots['MealPreparation']->getId());
$task = $em->getEntityById('Task', (string) $slotReloaded->get('taskId'));
ok($task !== null, 'task exists for MealPreparation slot');

if ($task) {
    $task->loadLinkMultipleField('collaborators');
    $collabIds = $task->getLinkMultipleIdList('collaborators');

    $prepAssigned = [];
    foreach ($em->getRDBRepository('ActivityInvite')->where([
        'activityOfferSlotId' => $slots['MealPreparation']->getId(),
        'status' => 'Confirmed',
    ])->find() as $inv) {
        $prepAssigned[] = (string) $inv->get('userId');
    }

    ok(count($prepAssigned) === 2 && count(array_diff($prepAssigned, $collabIds)) === 0,
        'both assigned volunteers are task collaborators');
}

// Post-confirm decline.
$confirmedInvite = $em->getRDBRepository('ActivityInvite')->where([
    'activityOfferSlotId' => $slots['MealPreparation']->getId(),
    'status' => 'Confirmed',
])->findOne();

if ($confirmedInvite) {
    $declineUserId = (string) $confirmedInvite->get('userId');
    $declineUser = $em->getEntityById('User', $declineUserId);

    $respondService = $injectableFactory->createWith(
        \Espo\Modules\VolunteerActivityDispatch\Tools\InviteResponseService::class,
        ['user' => $declineUser]
    );
    $respondService->decline($confirmedInvite->getId());

    $confirmedInvite = $em->getEntityById('ActivityInvite', $confirmedInvite->getId());
    ok($confirmedInvite->get('status') === 'Declined', 'volunteer decline -> Declined');

    $task = $em->getEntityById('Task', (string) $slotReloaded->get('taskId'));
    $task->loadLinkMultipleField('collaborators');
    ok(!in_array($declineUserId, $task->getLinkMultipleIdList('collaborators'), true),
        'declined volunteer removed from task collaborators');
} else {
    ok(false, 'a confirmed invite exists on MealPreparation for decline check');
}

// --- cleanup ------------------------------------------------------------------

foreach (['ActivityInvite', 'Task', 'ActivityOfferSlot'] as $type) {
    foreach ($em->getRDBRepository($type)->where(['activityOfferId' => $offer->getId()])->find() as $e) {
        $em->removeEntity($e, ['skipAll' => true, 'silent' => true]);
    }
}
foreach ($em->getRDBRepository('Notification')->where(['relatedType' => 'ActivityOffer', 'relatedId' => $offer->getId()])->find() as $e) {
    $em->removeEntity($e, ['skipAll' => true, 'silent' => true]);
}
foreach ($em->getRDBRepository('Email')->where(['parentType' => 'ActivityOffer', 'parentId' => $offer->getId()])->find() as $e) {
    $em->removeEntity($e, ['skipAll' => true, 'silent' => true]);
}
$em->removeEntity($em->getEntityById('ActivityOffer', $offer->getId()), ['skipAll' => true, 'silent' => true]);

$oldOfferReloaded = $em->getEntityById('ActivityOffer', $oldOffer->getId());
if ($oldOfferReloaded) {
    $em->removeEntity($oldOfferReloaded, ['skipAll' => true, 'silent' => true]);
}

if ($volunteerRole) {
    $em->getRDBRepository('User')->getRelation($volunteers[0], 'roles')->unrelate($volunteerRole);
}

foreach ($volunteers as $u) {
    $fresh = $em->getEntityById('User', $u->getId());
    if ($fresh) {
        $em->removeEntity($fresh, ['skipAll' => true, 'silent' => true]);
    }
}

echo $fail === 0 ? "ALL OK\n" : "FAILURES: $fail\n";
exit($fail === 0 ? 0 : 1);
