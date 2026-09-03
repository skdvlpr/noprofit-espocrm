<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Hooks\Contact\ProtectLinkedUser;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\TestCase;

class ProtectLinkedUserTest extends TestCase
{
    public function testRegularUserCannotBindOtherUserOnCreate(): void
    {
        $hook = $this->hookForRegularUser('vol-1');
        $entity = $this->newContact(['linkedUserId' => 'admin-1']);

        $this->expectException(Forbidden::class);
        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
    }

    public function testRegularUserCanSelfLinkOnCreate(): void
    {
        $hook = $this->hookForRegularUser('vol-1');
        $entity = $this->newContact(['linkedUserId' => 'vol-1']);

        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
        $this->addToAssertionCount(1);
    }

    public function testRegularUserCanCreateWithoutLinkedUser(): void
    {
        $hook = $this->hookForRegularUser('vol-1');
        $entity = $this->newContact(['linkedUserId' => null]);

        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
        $this->addToAssertionCount(1);
    }

    public function testRegularUserCannotBindPortalUserOnCreate(): void
    {
        $hook = $this->hookForRegularUser('vol-1');
        $entity = $this->newContact(['portalUserId' => 'portal-1']);

        $this->expectException(Forbidden::class);
        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
    }

    public function testSkipAllBypassesGuard(): void
    {
        $hook = $this->hookForRegularUser('vol-1');
        $entity = $this->newContact(['linkedUserId' => 'admin-1', 'portalUserId' => 'portal-1']);

        $hook->beforeSave($entity, SaveOptions::fromAssoc([SaveOption::SKIP_ALL => true]));
        $this->addToAssertionCount(1);
    }

    public function testAdminCanBindOtherUser(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isAdmin')->willReturn(true);
        $user->method('isSystem')->willReturn(false);
        $user->method('getId')->willReturn('admin-1');

        $hook = new ProtectLinkedUser($user, $this->language());
        $entity = $this->newContact(['linkedUserId' => 'other-1']);

        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
        $this->addToAssertionCount(1);
    }

    public function testUnchangedExistingLinkedUserIsAllowed(): void
    {
        $hook = $this->hookForRegularUser('vol-1');
        $entity = $this->createMock(Entity::class);
        $entity->method('isNew')->willReturn(false);
        $entity->method('isAttributeChanged')->willReturn(false);

        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
        $this->addToAssertionCount(1);
    }

    public function testRegularUserCanUnlinkExistingLinkedUser(): void
    {
        $hook = $this->hookForRegularUser('vol-1');
        $entity = $this->createMock(Entity::class);
        $entity->method('isNew')->willReturn(false);
        $entity->method('isAttributeChanged')->willReturnCallback(
            static fn (string $attr): bool => $attr === 'linkedUserId'
        );
        $entity->method('get')->willReturnCallback(
            static fn (string $attr): mixed => $attr === 'linkedUserId' ? null : null
        );

        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
        $this->addToAssertionCount(1);
    }

    private function hookForRegularUser(string $userId): ProtectLinkedUser
    {
        $user = $this->createMock(User::class);
        $user->method('isAdmin')->willReturn(false);
        $user->method('isSystem')->willReturn(false);
        $user->method('getId')->willReturn($userId);

        return new ProtectLinkedUser($user, $this->language());
    }

    private function language(): Language
    {
        $language = $this->createMock(Language::class);
        $language->method('translate')->willReturnCallback(
            static fn (string $key): string => $key
        );

        return $language;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function newContact(array $values): Entity
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('isNew')->willReturn(true);
        $entity->method('isAttributeChanged')->willReturn(true);
        $entity->method('get')->willReturnCallback(
            static fn (string $attr): mixed => $values[$attr] ?? null
        );

        return $entity;
    }
}
