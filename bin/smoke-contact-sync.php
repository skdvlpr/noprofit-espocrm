<?php
/**
 * Smoke test of {@see PersonContactSync} behavior on VolunteerEmployee / Member.
 *
 * Scenarios:
 *   1. Default-from-user: a VolunteerEmployee linked to a User with a primary
 *      email/phone should inherit them as its own primary contact rows.
 *   2. Multi-value add: extra emails/phones added on the entity coexist with
 *      the inherited primary, and exactly one row is marked primary.
 *   3. Cross-entity dedup: a Member trying to claim an email already used on a
 *      different VolunteerEmployee record must be rejected with BadRequest.
 *   4. Dedup runs even when enforceLinkedUserPrimary throws: a Member linked
 *      to a User without a primary email must still get its own dedup
 *      validated (duplicate email rejected) before the "no linked primary"
 *      BadRequest surfaces.
 *
 * Idempotent: creates throw-away User + entities under known names and
 * removes them at the end via try/finally.
 *
 * Usage:
 *   ddev exec php bin/smoke-contact-sync.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Exceptions\BadRequest;
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
    echo "Creating throw-away test user...\n";
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
    $userPrimaryPhone = (string) $user->get('phoneNumber');
    echo "  user: {$user->get('userName')}, email=$userPrimaryEmail, phone=$userPrimaryPhone\n";

    echo "\nScenario 1: default-from-user on VolunteerEmployee\n";
    $ve = $em->getNewEntity('VolunteerEmployee');
    $ve->set([
        'firstName' => 'Smoke',
        'lastName' => 'Linked',
        'type' => 'Volunteer',
        'userId' => $user->getId(),
        'weeklyHours' => 8,
    ]);
    $em->saveEntity($ve);
    $cleanup[] = $ve;

    $veEmail = strtolower((string) $ve->get('emailAddress'));
    $vePhone = (string) $ve->get('phoneNumber');
    $report('inherits primary email from user', $veEmail === $userPrimaryEmail, "got=$veEmail");
    $report('inherits primary phone from user', $vePhone === $userPrimaryPhone, "got=$vePhone");

    echo "\nScenario 2: multi-value add (extra email + phone alongside inherited primary)\n";
    $ve->set('emailAddressData', [
        (object) ['emailAddress' => 'whatever@ignored.local', 'primary' => true],
        (object) ['emailAddress' => 'smoke-extra-' . bin2hex(random_bytes(3)) . '@example.com', 'primary' => false],
    ]);
    $ve->set('phoneNumberData', [
        (object) ['phoneNumber' => '+39022' . random_int(1000000, 9999999), 'type' => 'Office', 'primary' => false],
    ]);
    $em->saveEntity($ve);

    $emailRowsAfter = $em->getRepository('EmailAddress')->getEmailAddressData($ve);
    $primaryEmails = array_values(array_filter($emailRowsAfter, fn ($r) => !empty($r->primary)));
    $report(
        'extra email persisted, primary still inherited',
        count($emailRowsAfter) >= 2 && count($primaryEmails) === 1
            && strtolower((string) $primaryEmails[0]->emailAddress) === $userPrimaryEmail,
        sprintf(
            'rows=%d primary=%s',
            count($emailRowsAfter),
            $primaryEmails[0]->emailAddress ?? 'NONE'
        )
    );

    $phoneRowsAfter = $em->getRepository('PhoneNumber')->getPhoneNumberData($ve);
    $primaryPhones = array_values(array_filter($phoneRowsAfter, fn ($r) => !empty($r->primary)));
    $report(
        'extra phone persisted, primary still inherited',
        count($phoneRowsAfter) >= 2 && count($primaryPhones) === 1
            && (string) $primaryPhones[0]->phoneNumber === $userPrimaryPhone,
        sprintf(
            'rows=%d primary=%s',
            count($phoneRowsAfter),
            $primaryPhones[0]->phoneNumber ?? 'NONE'
        )
    );

    echo "\nScenario 3: cross-entity dedup (Member must not reuse VolunteerEmployee email)\n";
    $member = $em->getNewEntity('Member');
    $member->set([
        'firstName' => 'Smoke',
        'lastName' => 'Conflict',
        'emailAddress' => $userPrimaryEmail,
        'joinDate' => date('Y-m-d'),
    ]);
    try {
        $em->saveEntity($member);
        $cleanup[] = $member;
        $report('cross-entity email dedup throws BadRequest', false, 'no exception thrown');
    } catch (BadRequest $e) {
        $report('cross-entity email dedup throws BadRequest', true, $e->getMessage());
    }

    echo "\nScenario 4: dedup runs even when linked user has no primary email\n";
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

    $duplicateEmail = (string) $ve->get('emailAddress');
    $memberLinked = $em->getNewEntity('Member');
    $memberLinked->set([
        'firstName' => 'Smoke',
        'lastName' => 'LinkedNoPrimary',
        'userId' => $userNoEmail->getId(),
        'emailAddress' => $duplicateEmail,
        'joinDate' => date('Y-m-d'),
    ]);
    try {
        $em->saveEntity($memberLinked);
        $cleanup[] = $memberLinked;
        $report('dedup wins over enforce-error', false, 'no exception thrown');
    } catch (BadRequest $e) {
        $msg = $e->getMessage();
        $isDedup = str_contains($msg, 'already used on');
        $report('dedup error surfaces before "no primary email" error', $isDedup, $msg);
    }

    echo "\nScenario 4b: enforce error still propagates when no dedup conflict\n";
    $memberClean = $em->getNewEntity('Member');
    $memberClean->set([
        'firstName' => 'Smoke',
        'lastName' => 'LinkedNoPrimaryClean',
        'userId' => $userNoEmail->getId(),
        'joinDate' => date('Y-m-d'),
    ]);
    try {
        $em->saveEntity($memberClean);
        $cleanup[] = $memberClean;
        $report('enforce error rethrown after clean dedup', false, 'no exception thrown');
    } catch (BadRequest $e) {
        $msg = $e->getMessage();
        $isEnforce = str_contains($msg, 'primary email');
        $report('enforce BadRequest still propagates after dedup', $isEnforce, $msg);
    }

    echo "\n=== ";
    echo $failures === 0 ? "ALL PASS" : ($failures . " FAILURE(S)");
    echo " ===\n";
} finally {
    echo "\nCleanup...\n";
    foreach (array_reverse($cleanup) as $entity) {
        try {
            $em->removeEntity($entity, [SaveOption::SKIP_ALL => true]);
        } catch (\Throwable $e) {
            echo "  cleanup failed for {$entity->getEntityType()} {$entity->getId()}: {$e->getMessage()}\n";
        }
    }
}
