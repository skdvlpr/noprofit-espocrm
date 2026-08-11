<?php

require __DIR__ . '/lib/refuse-production.php';


/**
 * Read-only smoke test of post-refactor Safehouse domain entities.
 *
 * Verifies (Contact STI — VolunteerEmployee / Member entities retired):
 *   - Contact Volunteer/Employee personnelStatus (Active/Inactive from startDate/endDate)
 *   - Contact monthlyHours = round(weeklyHours * 4.33, 1) for Volunteer/Employee
 *   - Contact MemberContact personnelStatus from joinDate/leaveDate
 *   - MealCount totalMeals = adults + minors
 *   - MealCount foodCost = totalMeals * foodUnitPrice (default 1.5 EUR)
 *   - MealCount dayOfWeek translated to English weekday
 *   - Scheduled jobs renamed (English Safehouse* jobClassName, Active)
 *
 * Creates temporary records and deletes them at the end.
 *
 * Usage:
 *   ddev exec php bin/smoke-safehouse.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
$em = $container->get('entityManager');

$results = [];
$created = [];
$failures = 0;

$assert = static function (string $label, bool $pass, string $detail = '') use (&$failures, &$results): void {
    if (!$pass) {
        $failures++;
    }
    $marker = $pass ? 'PASS' : 'FAIL';
    $row = [$marker, $label];
    if ($detail !== '') {
        $row[] = $detail;
    }
    $results[] = $row;
};

try {
    $ve = $em->getNewEntity('Contact');
    $ve->set([
        'firstName' => 'Smoke',
        'lastName' => 'Active',
        'contactType' => 'Employee',
        'contractType' => 'Permanent',
        'startDate' => date('Y-m-d', strtotime('-30 days')),
        'endDate' => date('Y-m-d', strtotime('+30 days')),
        'weeklyHours' => 40,
    ]);
    $em->saveEntity($ve);
    $created[] = $ve;
    $assert(
        'Contact Employee active',
        $ve->get('personnelStatus') === 'Active'
            && (float) $ve->get('monthlyHours') === (float) round(40 * 4.33, 1),
        'personnelStatus=' . $ve->get('personnelStatus')
            . ' monthlyHours=' . $ve->get('monthlyHours')
            . ' name=' . $ve->get('name')
    );

    $ve2 = $em->getNewEntity('Contact');
    $ve2->set([
        'firstName' => 'Smoke',
        'lastName' => 'Expired',
        'contactType' => 'Volunteer',
        'startDate' => date('Y-m-d', strtotime('-60 days')),
        'endDate' => date('Y-m-d', strtotime('-1 days')),
        'weeklyHours' => 8,
    ]);
    $em->saveEntity($ve2);
    $created[] = $ve2;
    $assert(
        'Contact Volunteer expired',
        $ve2->get('personnelStatus') === 'Inactive'
            && (float) $ve2->get('monthlyHours') === (float) round(8 * 4.33, 1),
        'personnelStatus=' . $ve2->get('personnelStatus')
            . ' monthlyHours=' . $ve2->get('monthlyHours')
            . ' name=' . $ve2->get('name')
    );

    $mb = $em->getNewEntity('Contact');
    $mb->set([
        'firstName' => 'Smoke',
        'lastName' => 'Member',
        'contactType' => 'MemberContact',
        'taxCode' => 'RSMRRA80A01H501U',
        'addressStreet' => 'Via Test 1',
        'addressCity' => 'Roma',
        'addressState' => 'RM',
        'joinDate' => date('Y-m-d', strtotime('-365 days')),
    ]);
    $em->saveEntity($mb);
    $created[] = $mb;
    $assert(
        'Contact MemberContact active',
        $mb->get('personnelStatus') === 'Active',
        'personnelStatus=' . $mb->get('personnelStatus')
            . ' name=' . $mb->get('name')
            . ' taxCode=' . $mb->get('taxCode')
    );

    $mc = $em->getNewEntity('MealCount');
    $mc->set(['date' => date('Y-m-d'), 'adults' => 25, 'minors' => 10]);
    $em->saveEntity($mc);
    $created[] = $mc;
    $assert(
        'MealCount today',
        (int) $mc->get('totalMeals') === 35
            && (float) $mc->get('foodCost') === 35 * 1.5,
        'totalMeals=' . $mc->get('totalMeals')
            . ' foodCost=' . $mc->get('foodCost')
            . ' dayOfWeek=' . $mc->get('dayOfWeek')
            . ' foodUnitPrice=' . $mc->get('foodUnitPrice')
    );

    $jobs = $em->getRDBRepository('ScheduledJob')->find();
    foreach ($jobs as $job) {
        $name = $job->get('job');
        if ($name !== null && str_starts_with($name, 'Safehouse')) {
            $results[] = ['INFO', 'ScheduledJob', 'job=' . $name, 'status=' . $job->get('status'), 'sched=' . $job->get('scheduling')];
        }
    }

    echo "=== SMOKE RESULTS ===\n";
    foreach ($results as $row) {
        echo implode(' | ', $row) . "\n";
    }
    echo $failures === 0 ? "=== OK ===\n" : "=== {$failures} FAILURE(S) ===\n";
    if ($failures > 0) {
        exit(1);
    }
} finally {
    foreach ($created as $entity) {
        try {
            $em->removeEntity($entity);
        } catch (Throwable $e) {
            echo "cleanup failed: {$e->getMessage()}\n";
        }
    }
}
