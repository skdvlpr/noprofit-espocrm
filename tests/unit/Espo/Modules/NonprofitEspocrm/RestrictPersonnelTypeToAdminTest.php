<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\ApplicationState;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\NonprofitEspocrm\Hooks\Contact\RestrictPersonnelTypeToAdmin;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\TestCase;

/**
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 */
class RestrictPersonnelTypeToAdminTest extends TestCase
{
    public function testAdminMayCreateVolunteer(): void
    {
        $hook = new RestrictPersonnelTypeToAdmin($this->state(logged: true, admin: true));
        $hook->beforeSave($this->contact('Volunteer', isNew: true), SaveOptions::fromAssoc([]));

        $this->addToAssertionCount(1);
    }

    public function testNonAdminCannotCreateEmployee(): void
    {
        $hook = new RestrictPersonnelTypeToAdmin($this->state(logged: true, admin: false));

        $this->expectException(Forbidden::class);
        $hook->beforeSave($this->contact('Employee', isNew: true), SaveOptions::fromAssoc([]));
    }

    public function testNonAdminMayCreateHelpSeeker(): void
    {
        $hook = new RestrictPersonnelTypeToAdmin($this->state(logged: true, admin: false));
        $hook->beforeSave($this->contact('HelpSeeker', isNew: true), SaveOptions::fromAssoc([]));

        $this->addToAssertionCount(1);
    }

    public function testNonAdminMayKeepExistingVolunteer(): void
    {
        $hook = new RestrictPersonnelTypeToAdmin($this->state(logged: true, admin: false));
        $hook->beforeSave(
            $this->contact('Volunteer', isNew: false, typeChanged: false),
            SaveOptions::fromAssoc([])
        );

        $this->addToAssertionCount(1);
    }

    public function testNonAdminCannotChangeTypeToVolunteer(): void
    {
        $hook = new RestrictPersonnelTypeToAdmin($this->state(logged: true, admin: false));

        $this->expectException(Forbidden::class);
        $hook->beforeSave(
            $this->contact('Volunteer', isNew: false, typeChanged: true),
            SaveOptions::fromAssoc([])
        );
    }

    public function testUnloggedSystemMayCreateVolunteer(): void
    {
        $hook = new RestrictPersonnelTypeToAdmin($this->state(logged: false, admin: false));
        $hook->beforeSave($this->contact('Volunteer', isNew: true), SaveOptions::fromAssoc([]));

        $this->addToAssertionCount(1);
    }

    private function state(bool $logged, bool $admin): ApplicationState
    {
        $state = $this->createMock(ApplicationState::class);
        $state->method('isLogged')->willReturn($logged);
        $state->method('isAdmin')->willReturn($admin);

        return $state;
    }

    private function contact(string $type, bool $isNew, bool $typeChanged = false): Entity
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'contactType' ? $type : null
        );
        $entity->method('isNew')->willReturn($isNew);
        $entity->method('isAttributeChanged')->willReturnCallback(
            static fn (string $field): bool => $field === 'contactType' && $typeChanged
        );

        return $entity;
    }
}
