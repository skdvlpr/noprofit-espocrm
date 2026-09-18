<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\FieldProcessing\Loader\Params as LoaderParams;
use Espo\Core\FieldValidation\FieldValidationManager;
use Espo\Modules\NonprofitEspocrm\Classes\FieldProcessing\User\ContactProfileLoader;
use Espo\ORM\Repository\Option\SaveOption;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * User profile is a Contact reflection; Member User-first still auto-creates Contact.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/record-defs.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 */
class UserContactProfileSyncTest extends SafehouseBaseTestCase
{
    public function testContactCompetencesReflectOnUserWithoutCopyBack(): void
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
            'emailAddress' => 'phpunit.vol.' . bin2hex(random_bytes(2)) . '@example.com',
            'rolesIds' => [$volunteerRole->getId()],
            'rolesNames' => (object) [$volunteerRole->getId() => 'Volunteer'],
        ]);
        $em->saveEntity($user);

        $this->assertTrue((bool) $user->get('hasVolunteerRole'));
        $this->assertNull(
            $em->getRDBRepository('Contact')->where(['linkedUserId' => $user->getId()])->findOne()
        );

        $contact = $em->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'PHPUnit',
            'lastName' => 'Volunteer',
            'contactType' => 'Volunteer',
            'linkedUserId' => $user->getId(),
            'isOccasional' => true,
            'startDate' => $startDate,
            'weeklyHours' => 8,
            'activityCompetences' => ['Reception'],
        ]);
        $em->saveEntity($contact);

        $userFresh = $em->getEntityById('User', $user->getId());
        $this->assertNotNull($userFresh);
        $this->loader()->process($userFresh, LoaderParams::create());

        $this->assertTrue((bool) $userFresh->get('isOccasional'));
        $this->assertSame($startDate, $userFresh->get('startDate'));
        $this->assertSame(['Reception'], $userFresh->get('activityCompetences'));

        $userFresh->set('activityCompetences', ['Cleaning']);
        $userFresh->set('isOccasional', false);
        $em->saveEntity($userFresh);

        $contactFresh = $em->getEntityById('Contact', $contact->getId());
        $this->assertNotNull($contactFresh);
        $this->assertSame(['Reception'], $contactFresh->get('activityCompetences'));
        $this->assertTrue((bool) $contactFresh->get('isOccasional'));
    }

    public function testMemberAutoCreateDoesNotCopyTaxCodeFromUser(): void
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
        $this->assertSame('MemberContact', $contact->get('contactType'));
        $this->assertTrue(
            $contact->get('taxCode') === null || $contact->get('taxCode') === '',
            'User taxCode must not be copied onto Contact'
        );

        $contact->set('taxCode', 'RSSMRA85T10A562S');
        $em->saveEntity($contact);

        $userFresh = $em->getEntityById('User', $user->getId());
        $this->assertNotNull($userFresh);
        $this->loader()->process($userFresh, LoaderParams::create());
        $this->assertSame('RSSMRA85T10A562S', $userFresh->get('taxCode'));
    }

    public function testDuplicateUserEmailFailsValidation(): void
    {
        $em = $this->getEntityManager();
        $email = 'phpunit.dup.' . bin2hex(random_bytes(2)) . '@example.com';

        $first = $em->getNewEntity('User');
        $first->set([
            'userName' => 'phpunit_dup1_' . bin2hex(random_bytes(2)),
            'firstName' => 'PHPUnit',
            'lastName' => 'DupOne',
            'type' => 'regular',
            'isActive' => true,
            'emailAddress' => $email,
        ]);
        $em->saveEntity($first);

        $second = $em->getNewEntity('User');
        $second->set([
            'userName' => 'phpunit_dup2_' . bin2hex(random_bytes(2)),
            'firstName' => 'PHPUnit',
            'lastName' => 'DupTwo',
            'type' => 'regular',
            'isActive' => true,
            'emailAddress' => $email,
        ]);

        $manager = $this->getContainer()
            ->getByClass(\Espo\Core\InjectableFactory::class)
            ->create(FieldValidationManager::class);

        $this->expectException(\Espo\Core\FieldValidation\Exceptions\ValidationError::class);
        $manager->process($second, (object) ['emailAddress' => $email]);
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

    private function loader(): ContactProfileLoader
    {
        return $this->getInjectableFactory()->create(ContactProfileLoader::class);
    }
}
