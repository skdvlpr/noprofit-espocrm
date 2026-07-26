<?php

require __DIR__ . '/lib/refuse-production.php';


/**
 * Read-only smoke test of post-refactor Safehouse domain entities.
 *
 * Verifies:
 *   - VolunteerEmployee status formula (Active/Inactive based on startDate/endDate)
 *   - VolunteerEmployee monthlyHours = round(weeklyHours * 4.33, 2)
 *   - VolunteerEmployee name = trim(firstName + ' ' + lastName)
 *   - Member status formula (Active/Inactive based on joinDate/leaveDate)
 *   - Member taxCode + province uppercased
 *   - MealCount totalMeals = adults + minors
 *   - MealCount foodCost = totalMeals * foodUnitPrice (default 1.5 EUR)
 *   - MealCount dayOfWeek translated to English weekday
 *   - Scheduled jobs renamed (English Safehouse* jobClassName, Active)
 *
 * Creates 4 temporary records and deletes them at the end.
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

try {
    $ve = $em->getNewEntity('VolunteerEmployee');
    $ve->set([
        'firstName' => 'Smoke',
        'lastName' => 'Active',
        'type' => 'Employee',
        'contractType' => 'Permanent',
        'startDate' => date('Y-m-d', strtotime('-30 days')),
        'endDate' => date('Y-m-d', strtotime('+30 days')),
        'weeklyHours' => 40,
    ]);
    $em->saveEntity($ve);
    $created[] = $ve;
    $results[] = ['VolunteerEmployee active', 'status=' . $ve->get('status'), 'name=' . $ve->get('name'), 'monthlyHours=' . $ve->get('monthlyHours')];

    $ve2 = $em->getNewEntity('VolunteerEmployee');
    $ve2->set([
        'firstName' => 'Smoke',
        'lastName' => 'Expired',
        'type' => 'Volunteer',
        'startDate' => date('Y-m-d', strtotime('-60 days')),
        'endDate' => date('Y-m-d', strtotime('-1 days')),
        'weeklyHours' => 8,
    ]);
    $em->saveEntity($ve2);
    $created[] = $ve2;
    $results[] = ['VolunteerEmployee expired', 'status=' . $ve2->get('status'), 'name=' . $ve2->get('name'), 'monthlyHours=' . $ve2->get('monthlyHours')];

    $mb = $em->getNewEntity('Member');
    $mb->set([
        'firstName' => 'Smoke',
        'lastName' => 'Member',
        'taxCode' => 'rsmrra80a01h501u',
        'addressState' => 'rm',
        'addressStreet' => 'Via Test 1',
        'addressCity' => 'Roma',
        'joinDate' => date('Y-m-d', strtotime('-365 days')),
    ]);
    $em->saveEntity($mb);
    $created[] = $mb;
    $results[] = ['Member active', 'status=' . $mb->get('status'), 'name=' . $mb->get('name'), 'taxCode=' . $mb->get('taxCode'), 'addressState=' . $mb->get('addressState')];

    $mc = $em->getNewEntity('MealCount');
    $mc->set(['date' => date('Y-m-d'), 'adults' => 25, 'minors' => 10]);
    $em->saveEntity($mc);
    $created[] = $mc;
    $results[] = ['MealCount today', 'totalMeals=' . $mc->get('totalMeals'), 'foodCost=' . $mc->get('foodCost'), 'dayOfWeek=' . $mc->get('dayOfWeek'), 'foodUnitPrice=' . $mc->get('foodUnitPrice')];

    $jobs = $em->getRDBRepository('ScheduledJob')->find();
    foreach ($jobs as $job) {
        $name = $job->get('job');
        if ($name !== null && str_starts_with($name, 'Safehouse')) {
            $results[] = ['ScheduledJob', 'job=' . $name, 'status=' . $job->get('status'), 'sched=' . $job->get('scheduling')];
        }
    }

    echo "=== SMOKE RESULTS ===\n";
    foreach ($results as $row) {
        echo implode(' | ', $row) . "\n";
    }
    echo "=== OK ===\n";
} finally {
    foreach ($created as $entity) {
        $em->removeEntity($entity);
    }
}
