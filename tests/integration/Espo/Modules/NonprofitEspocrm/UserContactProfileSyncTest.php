<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\ORM\Repository\Option\SaveOption;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * User ↔ Contact profile sync hooks (converted from bin/smoke-contact-occasional.php).
 */
class UserContactProfileSyncTest extends SafehouseBaseTestCase
{
    public function testVolunteerRoleCreatesLinkedContactWithSyncedFields(): void
    {
        $em = $this->getEntityManager();
        $volunteerRole = $em->getRDBRepository('Role')->where(['name' => 'Volunteer'])->findOne();
        if ($volunteerRole === null) {
            $this->markTestSkipped('Volunteer role not provisioned in test database.');
        }

        $startDate = date('Y-m-d', strtotime('-7 days'));
        $user = $em->getNewEntity('User');
        $user->set([
            'userName' => 'phpunit_vol_' . bin2hex(random_bytes(2)),
            'firstName' => 'PHPUnit',
            'lastName' => 'Volunteer',
            'type' => 'regular',
            'isActive' => true,
            'isOccasional' => true,
            'startDate' => $startDate,
            'weeklyHours' => 8,
            'emailAddress' => 'phpunit.vol.' . bin2hex(random_bytes(2)) . '@example.com',
            'rolesIds' => [$volunteerRole->getId()],
            'rolesNames' => (object) [$volunteerRole->getId() => 'Volunteer'],
        ]);
        $em->saveEntity($user);

        $this->assertTrue((bool) $user->get('hasVolunteerRole'));

        $contact = $em->getRDBRepository('Contact')
            ->where(['linkedUserId' => $user->getId()])
            ->findOne();

        $this->assertNotNull($contact);
        $this->assertSame('Volunteer', $contact->get('contactType'));
        $this->assertTrue((bool) $contact->get('isOccasional'));
        $this->assertSame($startDate, $contact->get('startDate'));
        $this->assertSame(34.6, (float) $user->get('monthlyHours'));
    }

    public function testUserTaxCodeSyncUppercasesLowercaseInputOnContact(): void
    {
        $em = $this->getEntityManager();
        $memberRole = $em->getRDBRepository('Role')->where(['name' => 'Member'])->findOne();
        if ($memberRole === null) {
            $this->markTestSkipped('Member role not provisioned in test database.');
        }

        $user = $em->getNewEntity('User');
        $user->set([
            'userName' => 'phpunit_cf_' . bin2hex(random_bytes(2)),
            'firstName' => 'PHPUnit',
            'lastName' => 'TaxCode',
            'type' => 'regular',
            'isActive' => true,
            'taxCode' => 'rssmra85t10a562s',
            'emailAddress' => 'phpunit.cf.' . bin2hex(random_bytes(2)) . '@example.com',
            'rolesIds' => [$memberRole->getId()],
            'rolesNames' => (object) [$memberRole->getId() => 'Member'],
        ]);
        $em->saveEntity($user);

        $contact = $em->getRDBRepository('Contact')
            ->where(['linkedUserId' => $user->getId()])
            ->findOne();

        $this->assertNotNull($contact);
        $this->assertSame('RSSMRA85T10A562S', $contact->get('taxCode'));
    }

    public function testUserDeleteInactivatesLinkedContact(): void
    {
        $em = $this->getEntityManager();
        $memberRole = $em->getRDBRepository('Role')->where(['name' => 'Member'])->findOne();
        if ($memberRole === null) {
            $this->markTestSkipped('Member role not provisioned in test database.');
        }

        $user = $em->getNewEntity('User');
        $user->set([
            'userName' => 'phpunit_mem_' . bin2hex(random_bytes(2)),
            'firstName' => 'PHPUnit',
            'lastName' => 'Member',
            'type' => 'regular',
            'isActive' => true,
            'joinDate' => date('Y-m-d'),
            'emailAddress' => 'phpunit.mem.' . bin2hex(random_bytes(2)) . '@example.com',
            'rolesIds' => [$memberRole->getId()],
            'rolesNames' => (object) [$memberRole->getId() => 'Member'],
        ]);
        $em->saveEntity($user);

        $contact = $em->getRDBRepository('Contact')
            ->where(['linkedUserId' => $user->getId()])
            ->findOne();
        $this->assertNotNull($contact);
        $contactId = $contact->getId();

        $em->removeEntity($user, [SaveOption::SKIP_ALL => true]);

        $fresh = $em->getEntityById('Contact', $contactId);
        $this->assertNotNull($fresh);
        $this->assertSame('Inactive', $fresh->get('personnelStatus'));
    }
}
