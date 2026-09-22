<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Hooks\User\InactivateLinkedContacts;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
 */
class InactivateLinkedContactsTest extends TestCase
{
    public function testAfterSaveWhenUserDeactivatedSetsContactInactive(): void
    {
        $contact = $this->createMock(Entity::class);
        $contact->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'personnelStatus' ? 'Active' : null
        );
        $contact->expects($this->once())->method('set')->with('personnelStatus', 'Inactive');

        $builder = $this->createMock(RDBSelectBuilder::class);
        $builder->method('find')->willReturn(new EntityCollection([$contact], 'Contact'));

        $repo = $this->createMock(RDBRepository::class);
        $repo->method('where')->willReturn($builder);

        $em = $this->createMock(EntityManager::class);
        $em->method('getRDBRepository')->with('Contact')->willReturn($repo);
        $em->expects($this->once())->method('saveEntity')->with(
            $contact,
            [SaveOption::SKIP_ALL => true]
        );

        $user = $this->createMock(Entity::class);
        $user->method('getId')->willReturn('uid-1');
        $user->method('isAttributeChanged')->with('isActive')->willReturn(true);
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'isActive' ? false : null
        );

        $hook = new InactivateLinkedContacts($em);
        $hook->afterSave($user, SaveOptions::fromAssoc([]));
    }

    public function testAfterSaveActiveUserDoesNotTouchContacts(): void
    {
        $em = $this->createMock(EntityManager::class);
        $em->expects($this->never())->method('getRDBRepository');

        $user = $this->createMock(Entity::class);
        $user->method('isAttributeChanged')->with('isActive')->willReturn(true);
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'isActive' ? true : null
        );

        $hook = new InactivateLinkedContacts($em);
        $hook->afterSave($user, SaveOptions::fromAssoc([]));
    }

    public function testAfterSaveSkipAllDoesNotTouchContacts(): void
    {
        $em = $this->createMock(EntityManager::class);
        $em->expects($this->never())->method('getRDBRepository');

        $user = $this->createMock(Entity::class);

        $hook = new InactivateLinkedContacts($em);
        $hook->afterSave($user, SaveOptions::fromAssoc([SaveOption::SKIP_ALL => true]));
    }
}
