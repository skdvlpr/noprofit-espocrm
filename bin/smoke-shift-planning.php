<?php

require __DIR__ . '/lib/refuse-production.php';

/**
 * Smoke test of shift planning lifecycle (merged into NonprofitEspocrm).
 *
 * Verifies:
 *   - clientDefs: lifecycle actions exposed as visible header buttons
 *     (detailButtonList) with existing handler file
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

$buttons = $metadata->get(['clientDefs', 'ActivityOffer', 'detailButtonList']) ?? [];
$buttonNames = array_map(fn ($item) => is_object($item) ? ($item->name ?? '') : ($item['name'] ?? ''), $buttons);

foreach (['fillAvailability', 'requestAvailability', 'requestAvailabilitySelected', 'autoAssign', 'confirmPlan', 'sendPendingUpdate', 'closePlan', 'cancelAll'] as $name) {
    ok(in_array($name, $buttonNames, true), "clientDefs detailButtonList has $name");
}

$handlerFile = __DIR__
    . '/../client/custom/modules/nonprofit-espocrm/src/handlers/activity-offer/shift-actions.js';
ok(is_file($handlerFile), 'shift-actions.js handler file exists');

$detailJs = __DIR__
    . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer/record/detail.js';
ok(is_readable($detailJs), 'ActivityOffer detail view exists');
ok(
    str_contains((string) file_get_contents($detailJs), 'detailButtonList'),
    'detail view loads detailButtonList as header buttons'
);

$legacyMenu = $metadata->get(['clientDefs', 'ActivityOffer', 'menu', 'detail', 'buttons']);
ok(empty($legacyMenu), 'legacy menu.detail.buttons removed (ignored by Espo 10)');

// --- User activityCompetences layout (prod rebuild must inject without Layout\Service) ---

(new \Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningInstaller())
    ->ensureUserCompetencesLayout($container, $injectableFactory);

$userDetailLayout = $injectableFactory
    ->create(\Espo\Tools\LayoutManager\LayoutManager::class)
    ->get('User', 'detail') ?? '';
ok(
    str_contains((string) $userDetailLayout, 'activityCompetences'),
    'User detail layout includes activityCompetences'
);
ok(
    str_contains((string) $userDetailLayout, 'isOccasional'),
    'User detail layout includes isOccasional'
);
ok(
    (bool) $metadata->get(['entityDefs', 'User', 'fields', 'activityCompetences']),
    'User.activityCompetences field exists in metadata'
);

$itLabels = json_decode((string) file_get_contents(__DIR__
    . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/ActivityOffer.json'), true);

foreach (['Fill availability', 'Request availability', 'Auto assign', 'Confirm plan'] as $label) {
    ok(isset($itLabels['labels'][$label]), "it_IT label present: $label");
}

// --- role access (bug #2: volunteer access to plans) --------------------------

(new \Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningInstaller())->ensureRoleAccess($container);

$volunteerRole = $em->getRDBRepository('Role')->where(['name' => 'Volunteer'])->findOne();
ok($volunteerRole !== null, 'canonical Volunteer role exists');

if ($volunteerRole) {
    $roleData = json_decode(json_encode($volunteerRole->get('data')), true) ?: [];
    ok(($roleData['ActivityOffer']['read'] ?? null) === 'all', 'Volunteer role: ActivityOffer read=all');
    ok(($roleData['ActivityOffer']['edit'] ?? null) === 'no', 'Volunteer role: ActivityOffer edit=no');
    ok(($roleData['ActivityOfferSlot']['read'] ?? null) === 'all', 'Volunteer role: ActivityOfferSlot read=all');
    ok(($roleData['ActivityInvite']['read'] ?? null) === 'own', 'Volunteer role: ActivityInvite read=own');
}

$tabConfig = $container->getByClass(\Espo\Core\Utils\Config::class);
$tabWriter = $injectableFactory->create(\Espo\Core\Utils\Config\ConfigWriter::class);
$tabList = (new \Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningInstaller())
    ->ensureActivityOfferTab($tabConfig->get('tabList') ?? []);
$tabWriter->set('tabList', $tabList);
$tabWriter->save();

$tabList = $injectableFactory->create(\Espo\Core\Utils\Config::class)->get('tabList') ?? [];
ok(in_array('ActivityOffer', $tabList, true), 'ActivityOffer present in tabList');
ok(in_array('ActivityOfferSlot', $tabList, true), 'ActivityOfferSlot present in tabList');

// --- email templates (bug #3: volunteer emails) --------------------------------

(new \Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningInstaller())->ensureEmailTemplates($container);

// Config object was cached before the write — reload it for assertions.
$freshConfig = $injectableFactory->create(\Espo\Core\Utils\Config::class);
$templateIds = $freshConfig->get(\Espo\Modules\NonprofitEspocrm\Tools\ShiftEmailService::CONFIG_KEY);
$templateIds = $templateIds ? json_decode(json_encode($templateIds), true) : [];

foreach ([
    'availabilityRequest',
    'shiftsConfirmed',
    'adminDigest',
    'planUpdated',
    'shiftCancelled',
    'weekFullyStaffed',
] as $kind) {
    $tplId = $templateIds[$kind] ?? null;
    $tpl = $tplId ? $em->getEntityById('EmailTemplate', $tplId) : null;
    ok($tpl !== null, "email template provisioned: $kind");

    if ($tpl) {
        $body = (string) $tpl->get('body');
        ok(str_contains($body, '{recordUrl}'), "email template $kind uses {recordUrl}");
        ok(!str_contains($body, '{planUrl}'), "email template $kind no longer uses {planUrl}");
        ok(str_contains($body, 'Safe House') || str_contains($body, '{brandName}') || str_contains($body, '{logoHtml}'),
            "email template $kind brands Safe House");
        ok(!preg_match('/Safehouse|safe house/i', str_replace(['Safe House', '{brandName}'], '', $body)),
            "email template $kind has no forbidden Safehouse spelling");
    }
}

$emailService = $injectableFactory->create(\Espo\Modules\NonprofitEspocrm\Tools\ShiftEmailService::class);
$slotStub = $em->getNewEntity('ActivityOfferSlot');
$slotStub->set([
    'category' => 'MealPreparation',
    'dateStart' => '2026-08-10 10:30:00',
    'dateEnd' => '2026-08-10 12:30:00',
    'requiredCount' => 1,
    'placeStreet' => 'Via Test',
    'placeCity' => 'Torino',
]);
$line1 = $emailService->formatConfirmedShiftLine($slotStub);
ok(
    str_contains($line1, 'persona') || str_contains($line1, 'person'),
    'slot line uses person/persona (not posto)'
);
ok(!str_contains($line1, 'posto') && !str_contains($line1, 'posti'), 'slot line has no posto/posti');
$slotStub->set('requiredCount', 2);
$line2 = $emailService->formatConfirmedShiftLine($slotStub);
ok(
    str_contains($line2, 'persone') || str_contains($line2, 'people'),
    'slot line plural uses people/persone'
);

$ref = new ReflectionClass($emailService);
$logoMethod = $ref->getMethod('logoHtml');
$logoMethod->setAccessible(true);
$logoHtml = (string) $logoMethod->invoke($emailService);
ok(
    str_contains($logoHtml, 'entryPoint=attachment') && str_contains($logoHtml, 'id='),
    'logoHtml uses inline attachment entryPoint (CID on send)'
);
ok(!str_contains($logoHtml, 'safe-house-logo.png'), 'logoHtml does not use remote static PNG URL');

ok(
    (bool) $metadata->get(['entityDefs', 'ActivityOffer', 'fields', 'weekSlots']),
    'ActivityOffer.weekSlots field exists'
);
ok(
    !$metadata->get(['entityDefs', 'ActivityOffer', 'fields', 'weekSlots', 'required']),
    'ActivityOffer.weekSlots is not required on plan create'
);
ok(
    (bool) $metadata->get(['clientDefs', 'ActivityOfferSlot', 'createDisabled']),
    'ActivityOfferSlot list createDisabled'
);
ok(
    (bool) $metadata->get(['scopes', 'ActivityOfferSlot', 'tab']),
    'ActivityOfferSlot navbar tab enabled'
);
ok(
    is_file(__DIR__ . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer/modals/create-week-slots.js'),
    'create-week-slots modal exists'
);
ok(
    is_file(__DIR__ . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer/record/panels/slots.js'),
    'slots relationship panel exists'
);
ok(
    (bool) $metadata->get(['entityDefs', 'ActivityOfferSlot', 'fields', 'conditions']),
    'ActivityOfferSlot.conditions field exists'
);

$slotStatusOpts = $metadata->get(['entityDefs', 'ActivityOfferSlot', 'fields', 'status', 'options']) ?? [];
ok(
    in_array('Published', $slotStatusOpts, true)
        && in_array('Covered', $slotStatusOpts, true)
        && in_array('Completed', $slotStatusOpts, true)
        && in_array('Cancelled', $slotStatusOpts, true),
    'ActivityOfferSlot status options Published/Covered/Completed/Cancelled'
);ok(
    ($metadata->get(['entityDefs', 'ActivityOfferSlot', 'fields', 'status', 'default']) ?? '') === 'Published',
    'ActivityOfferSlot status default Published'
);
ok(
    in_array('Draft', $metadata->get(['entityDefs', 'ActivityOffer', 'fields', 'status', 'options']) ?? [], true),
    'ActivityOffer plan still has Draft status'
);

ok(
    (bool) $metadata->get(['entityDefs', 'ActivityInvite', 'fields', 'comment']),
    'ActivityInvite.comment field exists'
);

$coverageJs = file_get_contents(__DIR__
    . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer/record/panels/coverage.js');
ok(
    is_string($coverageJs) && !str_contains($coverageJs, "action: 'fillAvailability'"),
    'coverage panel no longer duplicates Fill availability button'
);

$planningPanels = $metadata->get(['clientDefs', 'ActivityOffer', 'planningPanels', 'detail']) ?? [];
$planningNames = array_map(static fn ($p) => $p['name'] ?? '', $planningPanels);
ok(in_array('coverage', $planningNames, true), 'planningPanels includes coverage');
ok(in_array('volunteerStats', $planningNames, true), 'planningPanels includes volunteerStats');
ok(!in_array('slots', $planningNames, true), 'slots not duplicated in planningPanels');
ok(!in_array('tasks', $planningNames, true), 'tasks not duplicated in planningPanels');

$bottomPanels = $metadata->get(['clientDefs', 'ActivityOffer', 'bottomPanels', 'detail']) ?? [];
$bottomNames = array_map(static fn ($p) => $p['name'] ?? '', $bottomPanels);
ok(in_array('slots', $bottomNames, true), 'bottomPanels includes slots');
ok(in_array('tasks', $bottomNames, true), 'bottomPanels includes tasks');
ok(!in_array('coverage', $bottomNames, true), 'coverage moved out of bottomPanels');
ok(!in_array('volunteerStats', $bottomNames, true), 'volunteerStats moved to planningPanels');

$detailJs = file_get_contents(__DIR__
    . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer/record/detail.js');
ok(
    is_string($detailJs) && str_contains($detailJs, 'createPlanningView'),
    'ActivityOffer detail creates planning view'
);

$volunteerStatsJs = __DIR__
    . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer/record/panels/volunteer-stats.js';
ok(is_readable($volunteerStatsJs), 'volunteer-stats panel view exists');

$reportingCss = file_get_contents(__DIR__
    . '/../client/custom/modules/nonprofit-espocrm/res/css/reporting-stats.css');
ok(
    is_string($reportingCss)
        && str_contains($reportingCss, 'activity-offer-planning-top')
        && str_contains($reportingCss, 'activity-offer-bottom-full')
        && str_contains($reportingCss, 'font-size: 16px')
        && str_contains($reportingCss, 'actions-btn-group'),
    'reporting-stats has ActivityOffer planning/full-bottom CSS'
);

$auroraLayoutCss = file_get_contents(__DIR__
    . '/../client/custom/css/safehouse-aurora/safehouse-aurora-layout.css');
ok(
    is_string($auroraLayoutCss)
        && str_contains($auroraLayoutCss, 'modal.dialog-record .modal-dialog')
        && str_contains($auroraLayoutCss, 'detail .panel .list'),
    'Aurora layout has phone dialog + detail panel list scroll'
);

ok(
    is_file(__DIR__ . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer/fields/week-slots.js'),
    'week-slots field view exists'
);
ok(
    str_contains(
        (string) file_get_contents(
            __DIR__ . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer/fields/week-slots.js'
        ),
        'SafehouseGooglePlaces'
    )
        && str_contains(
            (string) file_get_contents(
                __DIR__ . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer/fields/week-slots.js'
            ),
            'viewPlaceMap'
        )
        && is_file(
            __DIR__ . '/../client/custom/modules/nonprofit-espocrm/lib/google-places-loader.js'
        )
        && str_contains(
            (string) file_get_contents(
                __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/client.json'
            ),
            'google-places-loader.js'
        ),
    'Aggiungi turni Google Places loader + View on Map'
);
ok(
    is_file(__DIR__ . '/../client/custom/res/templates/site/footer.tpl'),
    'custom site footer template exists'
);
ok(
    str_contains(
        (string) file_get_contents(__DIR__ . '/../client/custom/modules/nonprofit-espocrm/lib/init.js'),
        'gomercato.it'
    ),
    'footer init.js injects GoMercato branding'
);
ok(
    str_contains(
        (string) file_get_contents(__DIR__ . '/../html/main.html'),
        'gomercato.it'
    ),
    'html/main.html includes GoMercato footer'
);

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

ok(
    (bool) $metadata->get(['entityDefs', 'ActivityOffer', 'fields', 'isFullyStaffed']),
    'ActivityOffer.isFullyStaffed field exists'
);
ok(
    (bool) ($metadata->get(['entityDefs', 'ActivityOffer', 'fields', 'isFullyStaffed', 'readOnly']) ?? false),
    'ActivityOffer.isFullyStaffed is readOnly'
);
ok(
    (bool) $metadata->get(['entityDefs', 'ActivityOffer', 'fields', 'autoConfirmWhenFullyStaffed']),
    'ActivityOffer.autoConfirmWhenFullyStaffed field exists'
);
ok(
    !($metadata->get(['entityDefs', 'ActivityOffer', 'fields', 'autoConfirmWhenFullyStaffed', 'readOnly']) ?? false),
    'ActivityOffer.autoConfirmWhenFullyStaffed is editable by creator'
);
ok(
    (bool) $metadata->get(['entityDefs', 'ActivityOffer', 'fields', 'fullyStaffedNotifiedAt']),
    'ActivityOffer.fullyStaffedNotifiedAt field exists'
);
ok(
    ($metadata->get(['app', 'scheduledJobs', 'SafehouseCrmCompletePastActivityOfferSlots', 'scheduling']) ?? '')
        === '*/10 * * * *',
    'CompletePastActivityOfferSlots scheduling is every 10 minutes'
);
ok(
    is_file(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Jobs/AutoConfirmFullyStaffedPlan.php'),
    'AutoConfirmFullyStaffedPlan job exists'
);
ok(
    str_contains(
        (string) file_get_contents(
            __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftCoverageSyncService.php'
        ),
        "'Available'"
    ),
    'isFullyStaffed counts Available invites'
);

// --- fixtures -----------------------------------------------------------------

// Purge leftovers from previous runs.
foreach ($em->getRDBRepository('ActivityOffer')->where(['name' => [
    'Smoke Shift Week',
    'Smoke Shift Week OLD',
    'Smoke Week Generator',
]])->find() as $old) {
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

// --- weekly generator (WhatsApp-style syncWeekSlots) ---------------------------

$weekOffer = $em->getNewEntity('ActivityOffer');
$weekOffer->set([
    'name' => 'Smoke Week Generator',
    'weekStart' => '2026-09-07',
    'status' => 'Draft',
]);
$em->saveEntity($weekOffer, ['skipAll' => true, 'silent' => true]);

$weekSync = $injectableFactory->create(
    \Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService::class
)->addWeekSlots($weekOffer->getId(), [
    [
        'dayOfWeek' => 'Monday',
        'category' => 'MealPreparation',
        'timeStart' => '10:30',
        'timeEnd' => '12:30',
        'requiredCount' => 2,
        'conditions' => ['Portare grembiule', 'Arrivo 10 min prima'],
    ],
    [
        'dayOfWeek' => 'Wednesday',
        'category' => 'MealPreparation',
        'timeStart' => '11:30',
        'timeEnd' => '14:30',
        'requiredCount' => 1,
        'conditions' => [],
    ],
], [
    'uniqueAddress' => true,
    'placeStreet' => 'via Trivero 12',
    'placeCity' => 'Torino',
]);
ok($weekSync['slotCount'] === 2, 'addWeekSlots created 2 slots');
ok(($weekSync['createdCount'] ?? 0) === 2, 'addWeekSlots createdCount=2');

$weekSlots = iterator_to_array(
    $em->getRDBRepository('ActivityOfferSlot')->where(['activityOfferId' => $weekOffer->getId()])->order('dateStart')->find()
);
ok(count($weekSlots) === 2, 'DB has 2 week-generator slots');
ok(($weekSlots[0]->get('status') ?? '') === 'Published', 'new slots default to Published');
ok(
    str_contains((string) $weekSlots[0]->get('name'), 'via Trivero')
        || str_contains((string) $weekSlots[0]->get('placeStreet'), 'via Trivero'),
    'batch unique address copied onto generated slots'
);
ok(
    ($weekSlots[0]->get('conditions') === ['Portare grembiule', 'Arrivo 10 min prima'])
        || (is_array($weekSlots[0]->get('conditions')) && count($weekSlots[0]->get('conditions')) === 2),
    'conditions stored on generated slot'
);
ok($weekSlots[0]->get('dateStart') === '2026-09-07 10:30:00' || str_starts_with((string) $weekSlots[0]->get('dateStart'), '2026-09-07'),
    'Monday maps to weekStart date');

$allDaySync = $injectableFactory->create(
    \Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService::class
)->addWeekSlots($weekOffer->getId(), [
    [
        'dayOfWeek' => 'Friday',
        'category' => 'Cleaning',
        'isAllDay' => true,
        'timeStart' => '10:30',
        'timeEnd' => '12:30',
        'requiredCount' => 1,
        'conditions' => [],
    ],
]);
ok($allDaySync['slotCount'] === 3, 'second addWeekSlots batch appends (total 3)');
ok(($allDaySync['createdCount'] ?? 0) === 1, 'second batch createdCount=1');
$allDaySlot = $em->getRDBRepository('ActivityOfferSlot')
    ->where(['activityOfferId' => $weekOffer->getId(), 'isAllDay' => true])
    ->findOne();
ok($allDaySlot && (bool) $allDaySlot->get('isAllDay'), 'isAllDay flag stored');
ok($allDaySlot && str_contains((string) $allDaySlot->get('dateStart'), '00:00'), 'isAllDay starts at 00:00');
ok($allDaySlot && $allDaySlot->get('category') === 'Cleaning', 'per-row category stored');

// Auto-name formula (via save without skipAll)
$nameOffer = $em->getNewEntity('ActivityOffer');
$nameOffer->set([
    'weekStart' => '2026-09-07',
    'status' => 'Draft',
]);
$em->saveEntity($nameOffer);
ok(
    (string) $nameOffer->get('name') === '07.09.2026 - 13.09.2026',
    'auto name from weekStart–weekEnd'
);
$em->removeEntity($nameOffer, ['skipAll' => true, 'silent' => true]);

$weekSlots = iterator_to_array(
    $em->getRDBRepository('ActivityOfferSlot')->where(['activityOfferId' => $weekOffer->getId()])->find()
);
foreach ($weekSlots as $ws) {
    $em->removeEntity($ws, ['skipAll' => true, 'silent' => true]);
}
$em->removeEntity($weekOffer, ['skipAll' => true, 'silent' => true]);

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
        'status' => 'Published',
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
    \Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService::class
);

$res = $adminService->requestAvailability($offer->getId());
ok($res['slotCount'] === 3, 'requestAvailability slotCount=3');
ok($res['notifyCount'] === 3, 'requestAvailability notified 3 volunteers');
ok(($res['cohortCount'] ?? 0) === 3, 'requestAvailability cohortCount=3');
ok(array_key_exists('emailCount', $res), 'requestAvailability returns emailCount');
ok(array_key_exists('emailFailed', $res), 'requestAvailability returns emailFailed');
echo "  availability emails sent: " . ($res['emailCount'] ?? 0) . "\n";

$selectedRes = $adminService->requestAvailabilityForUsers($offer->getId(), [$volunteers[0]->getId(), $volunteers[1]->getId()]);
ok(($selectedRes['userCount'] ?? 0) === 2, 'requestAvailabilityForUsers userCount=2');
ok(($selectedRes['slotCount'] ?? 0) === 3, 'requestAvailabilityForUsers slotCount=3');
ok(array_key_exists('emailCount', $selectedRes), 'requestAvailabilityForUsers returns emailCount');
ok(array_key_exists('notifyCount', $selectedRes), 'requestAvailabilityForUsers returns notifyCount');

$selectedBad = false;

try {
    $adminService->requestAvailabilityForUsers($offer->getId(), ['not-a-cohort-user']);
} catch (\Espo\Core\Exceptions\BadRequest $e) {
    $selectedBad = true;
}

ok($selectedBad, 'requestAvailabilityForUsers rejects non-cohort user');

$selectedModal = __DIR__
    . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer/modals/request-availability-selected.js';
ok(is_file($selectedModal), 'request-availability-selected modal exists');
ok(
    str_contains((string) file_get_contents($selectedModal), 'label-success'),
    'selective-resend modal uses green responded badge'
);
ok(
    str_contains((string) file_get_contents($selectedModal), 'volunteerRespondedSingular'),
    'selective-resend modal uses singular responded label key'
);

$i18nItOffer = json_decode(
    (string) file_get_contents(__DIR__
        . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/ActivityOffer.json'),
    true
);
ok(
    ($i18nItOffer['labels']['volunteerRespondedSingular'] ?? null) === 'Ha risposto',
    'IT singular responded badge is Ha risposto'
);
ok(
    str_contains((string) file_get_contents($handlerFile), 'requestAvailabilitySelected'),
    'shift-actions handler has requestAvailabilitySelected'
);

$offer = $em->getEntityById('ActivityOffer', $offer->getId());
ok($offer->get('status') === 'CollectingAvailability', 'offer -> CollectingAvailability');

$declare = function ($volunteer, array $slotIds, ?string $comment = null) use ($injectableFactory, $offer) {
    $service = $injectableFactory->createWith(
        \Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService::class,
        ['user' => $volunteer]
    );

    return $service->saveAvailability($offer->getId(), $slotIds, $comment);
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
    \Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService::class,
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

$vStats = $adminService->volunteerStats($offer->getId());
ok(($vStats['summary']['cohortSize'] ?? 0) === 3, 'volunteerStats cohortSize=3');
ok(($vStats['summary']['respondedCount'] ?? 0) === 3, 'volunteerStats all responded');
ok(count($vStats['volunteers'] ?? []) === 3, 'volunteerStats returns 3 volunteer rows');
$vol3Stats = null;
foreach ($vStats['volunteers'] as $row) {
    if (($row['id'] ?? '') === $volunteers[2]->getId()) {
        $vol3Stats = $row;
        break;
    }
}
ok($vol3Stats !== null, 'volunteerStats includes vol3');
ok(
    is_array($vol3Stats['eligibleSlots'] ?? null)
        && count(array_filter(
            $vol3Stats['eligibleSlots'],
            static fn ($s) => ($s['name'] ?? '') === 'Meal preparation'
                || str_contains((string) ($s['name'] ?? ''), 'Meal')
                || ($s['id'] ?? '') === $slots['MealPreparation']->getId()
        )) >= 1,
    'volunteerStats vol3 eligible includes MealPreparation'
);

$assignRes = $adminService->autoAssign($offer->getId());
ok($assignRes['assignedCount'] >= 4, 'autoAssign made >=4 assignments');
ok($assignRes['uncovered'] === [], 'autoAssign covered all slots');

$offer = $em->getEntityById('ActivityOffer', $offer->getId());
ok($offer->get('status') === 'Planned', 'offer -> Planned');

foreach (['MealPreparation', 'MealDistribution', 'Cleaning'] as $cat) {
    $slotAfter = $em->getEntityById('ActivityOfferSlot', $slots[$cat]->getId());
    ok(
        ($slotAfter->get('status') ?? '') === 'Covered',
        "autoAssign flips $cat slot Published → Covered"
    );
}
$cov2 = $adminService->coverage($offer->getId());
$assignedNames = [];
foreach ($cov2['slots'] as $row) {
    $assignedNames[$row['category']] = array_map(fn ($u) => $u['name'], $row['assigned']);
}
$prep = $assignedNames['MealPreparation'] ?? [];
$dist = $assignedNames['MealDistribution'] ?? [];
ok(count(array_intersect($prep, $dist)) === 0, 'no volunteer double-booked on overlapping slots');

$confirmRes = $adminService->confirm($offer->getId());
ok(($confirmRes['taskCount'] ?? 0) === 0, 'confirm does not auto-create personal Tasks');
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

$prepConfirmed = $em->getRDBRepository('ActivityInvite')->where([
    'activityOfferSlotId' => $slots['MealPreparation']->getId(),
    'status' => 'Confirmed',
])->find();
$prepConfirmedIds = [];
foreach ($prepConfirmed as $inv) {
    $prepConfirmedIds[] = (string) $inv->get('userId');
}
ok(count($prepConfirmedIds) === 2, 'MealPreparation has 2 Confirmed invites after confirm');

// Post-confirm decline.
$confirmedInvite = $em->getRDBRepository('ActivityInvite')->where([
    'activityOfferSlotId' => $slots['MealPreparation']->getId(),
    'status' => 'Confirmed',
])->findOne();

if ($confirmedInvite) {
    $declineUserId = (string) $confirmedInvite->get('userId');
    $declineUser = $em->getEntityById('User', $declineUserId);

    $respondService = $injectableFactory->createWith(
        \Espo\Modules\NonprofitEspocrm\Tools\InviteResponseService::class,
        ['user' => $declineUser]
    );
    $respondService->decline($confirmedInvite->getId());

    $confirmedInvite = $em->getEntityById('ActivityInvite', $confirmedInvite->getId());
    ok($confirmedInvite->get('status') === 'Declined', 'volunteer decline -> Declined');
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


// --- restored Thursday-evening features --------------------------------------

ok(
    class_exists(\Espo\Modules\NonprofitEspocrm\Tools\ShiftChangeNotifyService::class),
    'ShiftChangeNotifyService class exists'
);
ok(
    method_exists(\Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService::class, 'sendPendingUpdate'),
    'ShiftPlanningService::sendPendingUpdate exists'
);
ok(
    method_exists(\Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService::class, 'extendPendingUpdate'),
    'ShiftPlanningService::extendPendingUpdate exists'
);
ok(
    method_exists(\Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService::class, 'closePlan'),
    'ShiftPlanningService::closePlan exists'
);
ok(
    method_exists(\Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService::class, 'cancelAll'),
    'ShiftPlanningService::cancelAll exists'
);
ok(
    method_exists(\Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService::class, 'slotStaffing'),
    'ShiftPlanningService::slotStaffing exists'
);
ok(
    method_exists(
        \Espo\Modules\NonprofitEspocrm\Controllers\ActivityOfferSlot::class,
        'getActionStaffing'
    ),
    'ActivityOfferSlot controller exposes GET staffing'
);
ok(
    method_exists(
        \Espo\Modules\NonprofitEspocrm\Controllers\ActivityOfferSlot::class,
        'postActionResendInvite'
    ),
    'ActivityOfferSlot controller exposes POST resendInvite'
);
ok(
    is_file(__DIR__ . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer-slot/record/panels/staffing.js'),
    'slot staffing panel view exists'
);
ok(
    method_exists(\Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService::class, 'completePastSlots'),
    'ShiftPlanningService::completePastSlots exists'
);
ok(
    method_exists(\Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService::class, 'syncSlotDatesFromDayOfWeek'),
    'ShiftPlanningService::syncSlotDatesFromDayOfWeek exists'
);
ok(
    is_file(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Hooks/ActivityOfferSlot/BeforeSaveSyncDatesFromDayOfWeek.php'),
    'BeforeSaveSyncDatesFromDayOfWeek hook file exists'
);
ok(
    is_file(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Jobs/CompletePastActivityOfferSlots.php'),
    'CompletePastActivityOfferSlots job file exists'
);
ok(
    is_file(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Jobs/NotifyPlanUpdated.php'),
    'NotifyPlanUpdated job file exists'
);

$offerStatusOpts = $metadata->get(['entityDefs', 'ActivityOffer', 'fields', 'status', 'options']) ?? [];
ok(in_array('Updated', $offerStatusOpts, true), 'ActivityOffer status options include Updated');
ok(in_array('Completed', $offerStatusOpts, true), 'ActivityOffer status options include Completed');

$slotStatusOpts = $metadata->get(['entityDefs', 'ActivityOfferSlot', 'fields', 'status', 'options']) ?? [];
ok(
    $slotStatusOpts === ['Published', 'Covered', 'Completed', 'Cancelled']
        || (in_array('Completed', $slotStatusOpts, true) && in_array('Cancelled', $slotStatusOpts, true)),
    'ActivityOfferSlot status options include Completed/Cancelled'
);

ok(
    (bool) $metadata->get(['entityDefs', 'ActivityOffer', 'fields', 'pendingNotifyAt']),
    'ActivityOffer.pendingNotifyAt field exists'
);
ok(
    (bool) $metadata->get(['entityDefs', 'ActivityOffer', 'fields', 'pendingNotifyKind']),
    'ActivityOffer.pendingNotifyKind field exists'
);

$reportingCss = file_get_contents(__DIR__ . '/../client/custom/modules/nonprofit-espocrm/res/css/reporting-stats.css') ?: '';
$auroraLayoutCss = file_get_contents(__DIR__ . '/../client/custom/css/safehouse-aurora/safehouse-aurora-layout.css') ?: '';
$inlineEditJs = file_get_contents(__DIR__ . '/../client/custom/modules/nonprofit-espocrm/src/views/record/list-inline-edit.js') ?: '';
ok(str_contains($reportingCss, ':has(.sh-editing)'), 'reporting-stats unlocks overflow when sh-editing');
ok(str_contains($auroraLayoutCss, ':has(.sh-editing)'), 'aurora layout unlocks overflow when sh-editing');
ok(str_contains($inlineEditJs, '_unlockOverflowAncestors'), 'list-inline-edit unlocks overflow ancestors');

$shiftActionsJs = file_get_contents($handlerFile) ?: '';
ok(str_contains($shiftActionsJs, 'sendPendingUpdate'), 'shift-actions.js has sendPendingUpdate');
ok(str_contains($shiftActionsJs, 'extendPendingUpdate'), 'shift-actions.js has extendPendingUpdate');
ok(str_contains($shiftActionsJs, 'cancelAll'), 'shift-actions.js has cancelAll');

ok(
    defined(\Espo\Modules\NonprofitEspocrm\Tools\ShiftEmailService::class . '::KIND_PLAN_UPDATED'),
    'ShiftEmailService::KIND_PLAN_UPDATED defined'
);

// dayOfWeek → dateStart/dateEnd sync
$dowOffer = $em->getNewEntity('ActivityOffer');
$dowOffer->set([
    'name' => 'Smoke DayOfWeek Dates',
    'weekStart' => '2026-08-03',
    'status' => 'Draft',
]);
$em->saveEntity($dowOffer, [
    'skipAll' => true,
    'silent' => true,
    \Espo\Modules\NonprofitEspocrm\Tools\StatusGuard::SKIP_OPTION => true,
]);

$dowSlot = $em->getNewEntity('ActivityOfferSlot');
$dowSlot->set([
    'activityOfferId' => $dowOffer->getId(),
    'category' => 'Cleaning',
    'dayOfWeek' => 'Monday',
    'dateStart' => '2026-08-03 10:00:00',
    'dateEnd' => '2026-08-03 12:00:00',
    'requiredCount' => 1,
    'name' => 'DOW sync slot',
    'status' => 'Published',
]);
$em->saveEntity($dowSlot, [
    'skipAll' => true,
    'silent' => true,
    \Espo\Modules\NonprofitEspocrm\Tools\StatusGuard::SKIP_OPTION => true,
]);

$dowSvc = $injectableFactory->create(\Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService::class);
$dowSlot->set('dayOfWeek', 'Wednesday');
$dowSvc->syncSlotDatesFromDayOfWeek($dowSlot);
ok(
    str_starts_with((string) $dowSlot->get('dateStart'), '2026-08-05'),
    'dayOfWeek Wednesday moves dateStart to 2026-08-05'
);
ok(
    str_starts_with((string) $dowSlot->get('dateEnd'), '2026-08-05'),
    'dayOfWeek Wednesday moves dateEnd to 2026-08-05'
);

// completePastSlots
$pastSlot = $em->getNewEntity('ActivityOfferSlot');
$pastSlot->set([
    'activityOfferId' => $dowOffer->getId(),
    'category' => 'Cleaning',
    'dayOfWeek' => 'Monday',
    'dateStart' => '2020-01-06 10:00:00',
    'dateEnd' => '2020-01-06 12:00:00',
    'requiredCount' => 1,
    'name' => 'Past slot',
    'status' => 'Published',
]);
$em->saveEntity($pastSlot, [
    'skipAll' => true,
    'silent' => true,
    \Espo\Modules\NonprofitEspocrm\Tools\StatusGuard::SKIP_OPTION => true,
]);
$completedCount = $dowSvc->completePastSlots('2020-01-08 00:00:00');
$pastSlot = $em->getEntityById('ActivityOfferSlot', $pastSlot->getId());
ok($completedCount >= 1, 'completePastSlots updates at least one past slot');
ok($pastSlot && $pastSlot->get('status') === 'Completed', 'past slot → Completed');

foreach ([$dowSlot->getId(), $pastSlot ? $pastSlot->getId() : null] as $sid) {
    if (!$sid) continue;
    $s = $em->getEntityById('ActivityOfferSlot', $sid);
    if ($s) {
        $em->removeEntity($s, ['skipAll' => true, 'silent' => true]);
    }
}
$o = $em->getEntityById('ActivityOffer', $dowOffer->getId());
if ($o) {
    $em->removeEntity($o, ['skipAll' => true, 'silent' => true]);
}



// --- layout: plan has no category / weekSlots / uniqueAddress (Notion 2026-08-04) ---
$detailLayout = json_decode((string) file_get_contents(__DIR__
    . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/ActivityOffer/detail.json'), true);
$editLayout = json_decode((string) file_get_contents(__DIR__
    . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/ActivityOffer/edit.json'), true);
$layoutBlob = json_encode([$detailLayout, $editLayout]);
ok(!str_contains($layoutBlob, '"category"'), 'ActivityOffer detail/edit layouts have no category');
ok(!str_contains($layoutBlob, '"weekSlots"'), 'ActivityOffer detail/edit layouts have no weekSlots');
ok(!str_contains($layoutBlob, '"uniqueAddress"'), 'ActivityOffer detail/edit layouts have no uniqueAddress');
ok(!str_contains($layoutBlob, '"place"'), 'ActivityOffer detail/edit layouts have no place');
$detailTpl = (string) file_get_contents(__DIR__
    . '/../client/custom/modules/nonprofit-espocrm/res/templates/activity-offer/record/detail.tpl');
ok(str_contains($detailTpl, 'showPendingUpdateBanner'), 'detail.tpl has pending-update banner');
ok(
    str_contains($detailTpl, 'bannerSendPendingUpdate'),
    'detail.tpl banner Send-now uses distinct data-action'
);
ok(
    str_contains($detailTpl, 'canEditPendingUpdate'),
    'detail.tpl gates banner update buttons with canEditPendingUpdate'
);
$detailJsSrc = (string) file_get_contents(__DIR__
    . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer/record/detail.js');
ok(
    str_contains($detailJsSrc, 'canEditPendingUpdate')
        && str_contains($detailJsSrc, "checkModel(this.model, 'edit')"),
    'detail.js sets canEditPendingUpdate from ACL edit'
);
ok(
    str_contains($detailJsSrc, 'startLiveRefresh')
        && str_contains($detailJsSrc, 'liveRefreshIntervalMs'),
    'detail.js polls for live banner/coverage refresh'
);
ok(
    str_contains(
        (string) file_get_contents(
            __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftPlanningInstaller.php'
        ),
        'hanno indicato la propria disponibilità'
    ),
    'weekFullyStaffed email uses indicato (not salvato)'
);
ok(
    !str_contains(
        (string) file_get_contents(
            __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftPlanningInstaller.php'
        ),
        "\$signOff = '{logoHtml}"
    ),
    'email signOff does not repeat logoHtml (no double Safe House)'
);
$clientCssList = $metadata->get(['app', 'client', 'cssList']) ?? [];
ok(
    in_array('client/custom/modules/nonprofit-espocrm/res/css/activity-offer.css', $clientCssList, true),
    'activity-offer.css is registered in app.client.cssList'
);
ok(
    is_file(__DIR__ . '/../client/custom/modules/nonprofit-espocrm/res/css/activity-offer.css'),
    'activity-offer.css file exists'
);
ok(
    str_contains(
        (string) file_get_contents(__DIR__ . '/../client/custom/modules/nonprofit-espocrm/res/css/activity-offer.css'),
        'data-status="Updated"'
    ),
    'activity-offer.css defines Updated badge color'
);
ok(
    ($metadata->get(['entityDefs', 'ActivityOfferSlot', 'fields', 'status', 'view']) ?? '')
        === 'nonprofit-espocrm:views/fields/enum-status-badge',
    'ActivityOfferSlot status uses enum-status-badge view'
);
ok(
    (new ReflectionClass(\Espo\Modules\NonprofitEspocrm\Tools\ShiftChangeNotifyService::class))
        ->hasMethod('markPendingUpdate'),
    'ShiftChangeNotifyService::markPendingUpdate exists'
);
ok(
    (new ReflectionClass(\Espo\Modules\NonprofitEspocrm\Tools\ShiftChangeNotifyService::class))
        ->hasMethod('wasScheduleChangeQueuedForSlot'),
    'ShiftChangeNotifyService::wasScheduleChangeQueuedForSlot exists'
);
ok(
    (new ReflectionClass(\Espo\Modules\NonprofitEspocrm\Tools\ShiftChangeNotifyService::class))
        ->hasMethod('discardPendingUpdate'),
    'ShiftChangeNotifyService::discardPendingUpdate exists'
);
ok(
    (new ReflectionClass(\Espo\Modules\NonprofitEspocrm\Tools\ShiftChangeNotifyService::class))
        ->hasMethod('hardRecollectAvailability'),
    'ShiftChangeNotifyService::hardRecollectAvailability exists'
);
ok(
    method_exists(
        \Espo\Modules\NonprofitEspocrm\Controllers\ActivityOffer::class,
        'postActionDiscardPendingUpdate'
    ),
    'ActivityOffer discardPendingUpdate action exists'
);
$notifySrc = (string) file_get_contents(__DIR__
    . '/../custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftChangeNotifyService.php');
ok(str_contains($notifySrc, "'Confirmed'"), 'notify statuses include Confirmed');
ok(str_contains($notifySrc, "'Updated'"), 'notify statuses include Updated');
ok(str_contains($notifySrc, "DEBOUNCE_INTERVAL = 'PT10M'"), 'pending notify debounce is 10 minutes');
ok(
    in_array('conditions', \Espo\Modules\NonprofitEspocrm\Tools\ShiftChangeNotifyService::placeTimeFields(), true),
    'slot conditions are HARD pending-update triggers (placeTimeFields)'
);
ok(
    !in_array(
        'conditions',
        \Espo\Modules\NonprofitEspocrm\Tools\ShiftChangeNotifyService::importantSlotFields(),
        true
    ),
    'slot conditions are not soft importantSlotFields'
);
ok(
    str_contains($notifySrc, 'strip both availability and assignment')
        || str_contains($notifySrc, 'Clear Assigned/Confirmed'),
    'processScheduleChange clears Available and Assigned/Confirmed on changed slots'
);
ok(
    str_contains($notifySrc, 'Unchanged slots are not passed')
        || str_contains($notifySrc, 'Accepted staffing stay'),
    'unchanged slots keep Assigned/Confirmed (not processed)'
);
ok(
    (bool) $metadata->get(['entityDefs', 'ActivityOffer', 'fields', 'pendingChangedSlotIdList']),
    'ActivityOffer.pendingChangedSlotIdList field exists'
);
$planningSrc = (string) file_get_contents(__DIR__
    . '/../custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftPlanningService.php');
ok(str_contains($planningSrc, "'changed'"), 'availabilityGrid exposes changed slot flag');
ok(
    !str_contains($planningSrc, 'lockedAvailable'),
    'availabilityGrid no longer locks Available (interest is cleared instead)'
);
$availJs = (string) file_get_contents(__DIR__
    . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer/modals/availability.js');
ok(str_contains($availJs, 'availabilitySectionChanged'), 'availability modal groups changed slots first');
ok(
    !str_contains($availJs, 'lockedAvailable'),
    'availability modal does not lock Available checkboxes'
);
$clientDefsOffer = (string) file_get_contents(__DIR__
    . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/ActivityOffer.json');
ok(
    str_contains($clientDefsOffer, '"quickDetailDisabled": true'),
    'ActivityOffer quick detail disabled (always full detail)'
);
$clientDefsSlot = (string) file_get_contents(__DIR__
    . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/ActivityOfferSlot.json');
ok(
    !str_contains($clientDefsSlot, '"quickDetailDisabled": true'),
    'ActivityOfferSlot keeps quick detail (overlay on full plan)'
);

$qvNav = (string) file_get_contents(__DIR__
    . '/../client/custom/modules/nonprofit-espocrm/src/lib/quick-view-navigation.js');
ok(
    str_contains($qvNav, 'applyQuickDetailPolicy')
        && str_contains($qvNav, 'isMetadataQuickDetailDisabled'),
    'quick-view-navigation respects metadata quickDetailDisabled'
);

$qvListHandler = (string) file_get_contents(__DIR__
    . '/../client/custom/modules/nonprofit-espocrm/src/handlers/quick-view-list.js');
ok(
    str_contains($qvListHandler, "applyQuickDetailPolicy(this.view) === 'quick'"),
    'quick-view-list skips force-enable when full-form policy'
);

$listInline = (string) file_get_contents(__DIR__
    . '/../client/custom/modules/nonprofit-espocrm/src/views/record/list-inline-edit.js');
ok(
    str_contains($listInline, "applyQuickDetailPolicy(this) === 'quick'"),
    'list-inline-edit skips force-enable when full-form policy'
);
$lightCss = (string) file_get_contents(__DIR__
    . '/../client/custom/css/safehouse-aurora/safehouse-aurora-light.css');
ok(
    str_contains($lightCss, '--state-primary-text: #1d4ed8'),
    'Aurora Light state-primary is blue (not brand red)'
);
ok(
    str_contains($lightCss, 'var(--state-default-bg)'),
    'Aurora Light label-default uses gray state tokens'
);
$layoutCss = (string) file_get_contents(__DIR__
    . '/../client/custom/css/safehouse-aurora/safehouse-aurora-layout.css');
ok(
    str_contains($layoutCss, 'min-width: 7.5rem'),
    'mobile list cells have readable min-width'
);
ok(
    str_contains($layoutCss, '--panel-default-bg: #ffffff'),
    'mobile Light panels forced opaque white'
);
$volStats = (string) file_get_contents(__DIR__
    . '/../client/custom/modules/nonprofit-espocrm/src/views/activity-offer/record/panels/volunteer-stats.js');
ok(
    str_contains($volStats, 'label-warning') && str_contains($volStats, 'data-status="Assigned"'),
    'volunteer-stats Assigned chip uses warning/amber'
);


echo $fail === 0 ? "ALL OK\n" : "FAILURES: $fail\n";
exit($fail === 0 ? 0 : 1);
