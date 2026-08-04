<?php

declare(strict_types=1);

require __DIR__ . '/lib/refuse-production.php';

/**
 * Smoke: User Volunteer/Member role → Contact profile sync.
 *
 * Usage:
 *   ddev exec php bin/smoke-contact-occasional.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\Tools\Layout\LayoutProvider;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
$em = $container->get('entityManager');
$metadata = $container->get('metadata');
/** @var LayoutProvider $layoutProvider */
$layoutProvider = $container->get('injectableFactory')->create(LayoutProvider::class);

$fail = 0;
$ok = 0;

$assert = static function (string $label, bool $cond, string $detail = '') use (&$fail, &$ok): void {
    if ($cond) {
        echo "OK  {$label}" . ($detail !== '' ? " ({$detail})" : '') . PHP_EOL;
        $ok++;
    } else {
        echo "FAIL {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
        $fail++;
    }
};

$assert(
    'Contact.isOccasional field',
    ($metadata->get(['entityDefs', 'Contact', 'fields', 'isOccasional', 'type']) === 'bool')
);
$assert(
    'Contact.birthProvince field',
    ($metadata->get(['entityDefs', 'Contact', 'fields', 'birthProvince', 'type']) === 'varchar')
);
$assert(
    'Contact.positionsHeld multiEnum',
    ($metadata->get(['entityDefs', 'Contact', 'fields', 'positionsHeld', 'type']) === 'multiEnum')
);
$assert(
    'Contact primary filter volunteersOccasionali',
    is_string($metadata->get(['selectDefs', 'Contact', 'primaryFilterClassNameMap', 'volunteersOccasionali']))
);
$assert(
    'Contact client filter volunteersOccasionali',
    (static function () use ($metadata): bool {
        $list = $metadata->get(['clientDefs', 'Contact', 'filterList']) ?? [];
        foreach ($list as $item) {
            if (is_array($item) && ($item['name'] ?? null) === 'volunteersOccasionali') {
                return true;
            }
            if (is_object($item) && ($item->name ?? null) === 'volunteersOccasionali') {
                return true;
            }
        }

        return false;
    })()
);
$assert(
    'Contact exportFormatList includes csv-email',
    in_array('csv-email', $metadata->get(['scopes', 'Contact', 'exportFormatList']) ?? [], true)
);
$assert(
    'User.startDate notStorable staging field',
    ($metadata->get(['entityDefs', 'User', 'fields', 'startDate', 'notStorable']) === true)
);
$assert(
    'User.memberNotes staging field',
    ($metadata->get(['entityDefs', 'User', 'fields', 'memberNotes', 'type']) === 'wysiwyg')
);
$assert(
    'User recordViews.edit custom',
    $metadata->get(['clientDefs', 'User', 'recordViews', 'edit'])
        === 'nonprofit-espocrm:views/user/record/edit'
);

$userDetail = (string) $layoutProvider->get('User', 'detail');
$assert('User detail has volunteeringProfile', str_contains($userDetail, 'volunteeringProfile'));
$assert('User detail has memberProfile', str_contains($userDetail, 'memberProfile'));

$volunteerRole = $em->getRDBRepository('Role')->where(['name' => 'Volunteer'])->findOne();
$employeeRole = $em->getRDBRepository('Role')->where(['name' => 'Employee'])->findOne();
$memberRole = $em->getRDBRepository('Role')->where(['name' => 'Member'])->findOne();
$assert('Volunteer role exists', $volunteerRole !== null);
$assert('Employee role exists', $employeeRole !== null);
$assert('Member role exists', $memberRole !== null);

$created = [];

try {
    $suffix = substr(md5((string) microtime(true)), 0, 8);

    // --- Volunteer role user ---
    $volUser = $em->getNewEntity('User');
    $volUser->set([
        'userName' => 'smoke_vol_' . $suffix,
        'firstName' => 'Smoke',
        'lastName' => 'Volunteer',
        'type' => 'regular',
        'isActive' => true,
        'isOccasional' => true,
        'startDate' => date('Y-m-d', strtotime('-7 days')),
        'weeklyHours' => 8,
        'taxCode' => '',
        'emailAddress' => 'smoke.vol.' . $suffix . '@example.com',
    ]);
    if ($volunteerRole) {
        $volUser->set('rolesIds', [$volunteerRole->getId()]);
        $volUser->set('rolesNames', (object) [$volunteerRole->getId() => 'Volunteer']);
    }
    $em->saveEntity($volUser);
    $created[] = $volUser;

    $volContact = null;
    foreach ($em->getRDBRepository('Contact')->where(['linkedUserId' => $volUser->getId()])->find() as $c) {
        $volContact = $c;
        break;
    }
    $assert('Volunteer role creates Contact', $volContact !== null);
    $assert(
        'Volunteer Contact type',
        $volContact !== null && $volContact->get('contactType') === 'Volunteer',
        $volContact ? (string) $volContact->get('contactType') : ''
    );
    $assert(
        'Volunteer Contact isOccasional',
        $volContact !== null && (bool) $volContact->get('isOccasional') === true
    );
    $assert(
        'Volunteer Contact startDate synced',
        $volContact !== null && $volContact->get('startDate') === $volUser->get('startDate')
    );
    if ($volContact) {
        $created[] = $volContact;
    }

    // --- Member role user ---
    $memUser = $em->getNewEntity('User');
    $memUser->set([
        'userName' => 'smoke_mem_' . $suffix,
        'firstName' => 'Smoke',
        'lastName' => 'Member',
        'type' => 'regular',
        'isActive' => true,
        'joinDate' => date('Y-m-d', strtotime('-30 days')),
        'birthPlace' => 'Roma',
        'birthProvince' => 'RM',
        'positionsHeld' => ['President', 'Founder'],
        'memberNotes' => 'Associato smoke',
        'emailAddress' => 'smoke.mem.' . $suffix . '@example.com',
    ]);
    if ($memberRole) {
        $memUser->set('rolesIds', [$memberRole->getId()]);
        $memUser->set('rolesNames', (object) [$memberRole->getId() => 'Member']);
    }
    $em->saveEntity($memUser);
    $created[] = $memUser;

    $memContact = null;
    foreach ($em->getRDBRepository('Contact')->where(['linkedUserId' => $memUser->getId()])->find() as $c) {
        $memContact = $c;
        break;
    }
    $assert('Member role creates Contact', $memContact !== null);
    $assert(
        'Member Contact type',
        $memContact !== null && $memContact->get('contactType') === 'MemberContact',
        $memContact ? (string) $memContact->get('contactType') : ''
    );
    $assert(
        'Member Contact joinDate synced',
        $memContact !== null && $memContact->get('joinDate') === $memUser->get('joinDate')
    );
    $assert(
        'Member Contact birthProvince synced',
        $memContact !== null && $memContact->get('birthProvince') === 'RM'
    );
    $positions = $memContact ? $memContact->get('positionsHeld') : null;
    if (is_string($positions)) {
        $decoded = json_decode($positions, true);
        $positions = is_array($decoded) ? $decoded : [$positions];
    }
    $assert(
        'Member Contact positionsHeld synced',
        is_array($positions)
            && in_array('President', $positions, true)
            && in_array('Founder', $positions, true),
        is_array($positions) ? implode(',', $positions) : gettype($positions)
    );
    $assert(
        'Member Contact notes synced',
        $memContact !== null && $memContact->get('notes') === 'Associato smoke'
    );
    if ($memContact) {
        $created[] = $memContact;

        $em->removeEntity($memUser);
        $memFresh = $em->getEntityById('Contact', $memContact->getId());
        $assert(
            'Member User delete → Contact Inactive',
            $memFresh !== null && $memFresh->get('personnelStatus') === 'Inactive'
        );
    }

    // --- Employee role user (Dipendente) ---
    $empUser = $em->getNewEntity('User');
    $empUser->set([
        'userName' => 'smoke_emp_' . $suffix,
        'firstName' => 'Smoke',
        'lastName' => 'Employee',
        'type' => 'regular',
        'isActive' => true,
        'startDate' => date('Y-m-d', strtotime('-14 days')),
        'weeklyHours' => 20,
        'emailAddress' => 'smoke.emp.' . $suffix . '@example.com',
    ]);
    if ($employeeRole) {
        $empUser->set('rolesIds', [$employeeRole->getId()]);
        $empUser->set('rolesNames', (object) [$employeeRole->getId() => 'Employee']);
    }
    $em->saveEntity($empUser);
    $created[] = $empUser;

    $empContact = null;
    foreach ($em->getRDBRepository('Contact')->where(['linkedUserId' => $empUser->getId()])->find() as $c) {
        $empContact = $c;
        break;
    }
    $assert('Employee role creates Contact', $empContact !== null);
    $assert(
        'Employee Contact type',
        $empContact !== null && $empContact->get('contactType') === 'Employee',
        $empContact ? (string) $empContact->get('contactType') : ''
    );
    $assert(
        'Employee Contact startDate synced',
        $empContact !== null && $empContact->get('startDate') === $empUser->get('startDate')
    );
    if ($empContact) {
        $created[] = $empContact;

        $em->removeEntity($empUser);
        $empFresh = $em->getEntityById('Contact', $empContact->getId());
        $assert(
            'Employee User delete → Contact Inactive',
            $empFresh !== null && $empFresh->get('personnelStatus') === 'Inactive'
        );
    }
} catch (Throwable $e) {
    echo 'FAIL exception — ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    $fail++;
} finally {
    foreach (array_reverse($created) as $entity) {
        try {
            if ($entity->getEntityType() === 'User' && $entity->get('deleted')) {
                continue;
            }
            $em->removeEntity($entity);
        } catch (Throwable $e) {
            // best-effort
        }
    }
}

echo PHP_EOL . "Passed: {$ok}, Failed: {$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
