<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Contact STI retirement and save invariants (converted from bin/smoke-contact-sync.php).
 */
class ContactSyncTest extends SafehouseBaseTestCase
{
    public function testPersonContactSyncRetiredAndContactsSave(): void
    {
        $this->assertFalse(
            class_exists(\Espo\Modules\NonprofitEspocrm\Tools\PersonContactSync::class)
        );

        $em = $this->getEntityManager();

        $user = $em->getNewEntity('User');
        $user->set([
            'userName' => 'phpunit_user_' . bin2hex(random_bytes(3)),
            'firstName' => 'PHPUnit',
            'lastName' => 'ContactSync',
            'emailAddress' => 'phpunit-' . bin2hex(random_bytes(3)) . '@example.com',
            'isActive' => true,
            'type' => 'regular',
        ]);
        $em->saveEntity($user);

        $vol = $em->getNewEntity('Contact');
        $vol->set([
            'firstName' => 'PHPUnit',
            'lastName' => 'Volunteer',
            'contactType' => 'Volunteer',
            'assignedUserId' => $user->getId(),
            'weeklyHours' => 8,
        ]);
        $em->saveEntity($vol);

        $this->assertTrue($vol->hasId());

        $member = $em->getNewEntity('Contact');
        $member->set([
            'firstName' => 'PHPUnit',
            'lastName' => 'Member',
            'contactType' => 'MemberContact',
            'emailAddress' => 'member-' . bin2hex(random_bytes(3)) . '@example.com',
        ]);
        $em->saveEntity($member);

        $this->assertTrue($member->hasId());
    }
}
