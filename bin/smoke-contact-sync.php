<?php

require __DIR__ . '/lib/refuse-production.php';


/**
 * Smoke: Contact STI fixtures (PersonContactSync retired/removed).
 *
 * VolunteerEmployee / Member entities are retired. User ↔ Contact profile
 * sync is covered by bin/smoke-contact-occasional.php
 * ({@see UserContactProfileSync}).
 *
 * Scenarios:
 *   1. PersonContactSync class is absent (retired).
 *   2. Contact (Volunteer) saves with email/phone.
 *   3. Second Contact (MemberContact) saves with its own email.
 *   4. Contact assigned to a User without email still saves.
 *
 * Usage:
 *   ddev exec php bin/smoke-contact-sync.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\Repository\Option\SaveOption;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
$em = $container->get('entityManager');

$cleanup = [];
$failures = 0;
$report = function (string $name, bool $pass, string $detail = '') use (&$failures): void {
    if (!$pass) {
        $failures++;
    }
    $marker = $pass ? '[PASS]' : '[FAIL]';
    echo "  $marker $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

try {
    echo "Scenario 0: PersonContactSync retirement\n";
    $report(
        'PersonContactSync class removed',
        !class_exists(\Espo\Modules\NonprofitEspocrm\Tools\PersonContactSync::class)
    );

    echo "\nCreating throw-away test user...\n";
    $user = $em->getNewEntity('User');
    $user->set([
        'userName' => 'smoke_user_' . bin2hex(random_bytes(2)),
        'firstName' => 'SmokeUser',
        'lastName' => 'ContactSync',
        'emailAddress' => 'smoke-primary-' . bin2hex(random_bytes(3)) . '@example.com',
        'phoneNumber' => '+39055' . random_int(1000000, 9999999),
        'isActive' => true,
        'type' => 'regular',
    ]);
    $em->saveEntity($user);
    $cleanup[] = $user;

    $userPrimaryEmail = strtolower((string) $user->get('emailAddress'));
    echo "  user: {$user->get('userName')}, email=$userPrimaryEmail\n";

    echo "\nScenario 1: Contact Volunteer fixture saves\n";
    $vol = $em->getNewEntity('Contact');
    $vol->set([
        'firstName' => 'Smoke',
        'lastName' => 'Linked',
        'contactType' => 'Volunteer',
        'assignedUserId' => $user->getId(),
        'weeklyHours' => 8,
        'emailAddress' => $userPrimaryEmail,
        'phoneNumber' => (string) $user->get('phoneNumber'),
    ]);
    try {
        $em->saveEntity($vol);
        $cleanup[] = $vol;
        $report(
            'Contact Volunteer saves',
            $vol->hasId(),
            'id=' . ($vol->getId() ?? '')
        );
        $report(
            'Contact Volunteer keeps email',
            strtolower((string) $vol->get('emailAddress')) === $userPrimaryEmail,
            'got=' . (string) $vol->get('emailAddress')
        );
    } catch (Throwable $e) {
        $report('Contact Volunteer saves', false, $e->getMessage());
    }

    echo "\nScenario 2: Contact MemberContact with distinct email saves\n";
    $memberEmail = 'smoke-member-' . bin2hex(random_bytes(3)) . '@example.com';
    $member = $em->getNewEntity('Contact');
    $member->set([
        'firstName' => 'Smoke',
        'lastName' => 'MemberContact',
        'contactType' => 'MemberContact',
        'emailAddress' => $memberEmail,
        'joinDate' => date('Y-m-d'),
    ]);
    try {
        $em->saveEntity($member);
        $cleanup[] = $member;
        $report('Contact MemberContact saves', $member->hasId());
    } catch (Throwable $e) {
        $report('Contact MemberContact saves', false, $e->getMessage());
    }

    echo "\nScenario 3: Contact assigned to user with no email — save succeeds\n";
    $userNoEmail = $em->getNewEntity('User');
    $userNoEmail->set([
        'userName' => 'smoke_noemail_' . bin2hex(random_bytes(2)),
        'firstName' => 'NoEmail',
        'lastName' => 'User',
        'isActive' => true,
        'type' => 'regular',
    ]);
    $em->saveEntity($userNoEmail, [SaveOption::SKIP_ALL => true]);
    $cleanup[] = $userNoEmail;

    $contactClean = $em->getNewEntity('Contact');
    $contactClean->set([
        'firstName' => 'Smoke',
        'lastName' => 'NoEmailUser',
        'contactType' => 'Volunteer',
        'assignedUserId' => $userNoEmail->getId(),
        'startDate' => date('Y-m-d'),
    ]);
    try {
        $em->saveEntity($contactClean);
        $cleanup[] = $contactClean;
        $report('save succeeds when assigned user has no email', true);
    } catch (Throwable $e) {
        $report('save succeeds when assigned user has no email', false, $e->getMessage());
    }

    echo "\n=== ";
    echo $failures === 0 ? "ALL PASS" : ($failures . " FAILURE(S)");
    echo " ===\n";
    if ($failures > 0) {
        exit(1);
    }
} finally {
    echo "\nCleanup...\n";
    foreach (array_reverse($cleanup) as $entity) {
        try {
            $em->removeEntity($entity, [SaveOption::SKIP_ALL => true]);
        } catch (Throwable $e) {
            echo "  cleanup failed for {$entity->getEntityType()} {$entity->getId()}: {$e->getMessage()}\n";
        }
    }
}
