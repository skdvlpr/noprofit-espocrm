<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\ContactUserChannelSync;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\TestCase;

/**
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 */
class ContactUserChannelSyncTest extends TestCase
{
    public function testVolunteerContactEmailSetCopiedToUser(): void
    {
        $contactEmails = [
            (object) [
                'emailAddress' => 'vol@example.com',
                'primary' => true,
                'optOut' => false,
                'invalid' => false,
            ],
        ];

        $contact = $this->entity([
            'contactType' => 'Volunteer',
            'linkedUserId' => 'user-1',
            'emailAddressData' => $contactEmails,
            'phoneNumberData' => [],
            'emailAddress' => 'vol@example.com',
            'phoneNumber' => null,
        ], changed: ['emailAddressData']);

        $user = $this->entity([
            'type' => 'regular',
            'emailAddressData' => [],
            'phoneNumberData' => [],
            'emailAddress' => null,
            'phoneNumber' => null,
        ]);

        $em = $this->createMock(EntityManager::class);
        $em->method('getEntityById')->with('User', 'user-1')->willReturn($user);
        $em->expects($this->once())->method('saveEntity')->with(
            $user,
            $this->callback(static function (array $options): bool {
                return !empty($options[ContactUserChannelSync::SKIP_OPTION]);
            })
        );

        $this->sync($em)->afterContactSave($contact, SaveOptions::fromAssoc([]));
    }

    public function testUserExtraPhoneCopiedToContact(): void
    {
        $userPhones = [
            (object) [
                'phoneNumber' => '+390111',
                'type' => 'Mobile',
                'primary' => true,
                'optOut' => false,
                'invalid' => false,
            ],
            (object) [
                'phoneNumber' => '+390222',
                'type' => 'Office',
                'primary' => false,
                'optOut' => false,
                'invalid' => false,
            ],
        ];

        $user = $this->entity([
            'type' => 'regular',
            'emailAddressData' => [],
            'phoneNumberData' => $userPhones,
            'emailAddress' => null,
            'phoneNumber' => '+390111',
        ], id: 'user-2', changed: ['phoneNumberData']);

        $contact = $this->entity([
            'contactType' => 'Employee',
            'linkedUserId' => 'user-2',
            'emailAddressData' => [],
            'phoneNumberData' => [],
            'emailAddress' => null,
            'phoneNumber' => null,
        ]);

        $em = $this->createMock(EntityManager::class);
        $em->method('getRDBRepository')->with('Contact')->willReturn($this->contactLookup($contact));
        $em->expects($this->once())->method('saveEntity')->with(
            $contact,
            $this->callback(static function (array $options): bool {
                return !empty($options[ContactUserChannelSync::SKIP_OPTION]);
            })
        );

        $this->sync($em)->afterUserSave($user, SaveOptions::fromAssoc([]));
    }

    public function testHelpSeekerNeverWritesUser(): void
    {
        $contact = $this->entity([
            'contactType' => 'HelpSeeker',
            'linkedUserId' => 'user-1',
            'emailAddressData' => [
                (object) ['emailAddress' => 'hs@example.com', 'primary' => true],
            ],
            'phoneNumberData' => [],
        ], changed: ['emailAddressData']);

        $em = $this->createMock(EntityManager::class);
        $em->expects($this->never())->method('getEntityById');
        $em->expects($this->never())->method('saveEntity');

        $this->sync($em)->afterContactSave($contact, SaveOptions::fromAssoc([]));
    }

    public function testSkipOptionDoesNotSaveCounterpart(): void
    {
        $contact = $this->entity([
            'contactType' => 'Volunteer',
            'linkedUserId' => 'user-1',
            'emailAddressData' => [
                (object) ['emailAddress' => 'a@example.com', 'primary' => true],
            ],
            'phoneNumberData' => [],
        ], changed: ['emailAddressData']);

        $em = $this->createMock(EntityManager::class);
        $em->expects($this->never())->method('getEntityById');
        $em->expects($this->never())->method('saveEntity');

        $this->sync($em)->afterContactSave(
            $contact,
            SaveOptions::fromAssoc([ContactUserChannelSync::SKIP_OPTION => true])
        );
    }

    public function testUnlinkedContactIsNoOp(): void
    {
        $contact = $this->entity([
            'contactType' => 'Volunteer',
            'linkedUserId' => '',
            'emailAddressData' => [
                (object) ['emailAddress' => 'a@example.com', 'primary' => true],
            ],
            'phoneNumberData' => [],
        ], changed: ['emailAddressData']);

        $em = $this->createMock(EntityManager::class);
        $em->expects($this->never())->method('getEntityById');
        $em->expects($this->never())->method('saveEntity');

        $this->sync($em)->afterContactSave($contact, SaveOptions::fromAssoc([]));
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $changed
     */
    private function entity(array $values, array $changed = [], string $id = 'id-1'): Entity
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('getId')->willReturn($id);
        $entity->method('isNew')->willReturn(false);
        $entity->method('isAttributeChanged')->willReturnCallback(
            static fn (string $name): bool => in_array($name, $changed, true)
        );
        $entity->method('get')->willReturnCallback(
            static fn (string $field): mixed => $values[$field] ?? null
        );
        $entity->method('set')->willReturn($entity);

        return $entity;
    }

    private function contactLookup(?Entity $contact): RDBRepository
    {
        $select = $this->createMock(RDBSelectBuilder::class);
        $select->method('findOne')->willReturn($contact);

        $repo = $this->createMock(RDBRepository::class);
        $repo->method('where')->willReturn($select);

        return $repo;
    }

    private function sync(EntityManager $entityManager): ContactUserChannelSync
    {
        return new ContactUserChannelSync($entityManager);
    }
}
