<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Utils\Language;
use Espo\Modules\NonprofitEspocrm\Hooks\User\EnforceUniqueEmail;
use Espo\Modules\NonprofitEspocrm\Tools\UserEmailUniqueness;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\TestCase;

/**
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 */
class EnforceUniqueEmailTest extends TestCase
{
    public function testConflictWhenAnotherUserOwnsAddress(): void
    {
        $this->expectException(Conflict::class);

        $this->hook(hasConflict: true)->beforeSave(
            $this->user(),
            SaveOptions::fromAssoc([])
        );
    }

    public function testAllowsSameUserOrContactShare(): void
    {
        $this->hook(hasConflict: false)->beforeSave(
            $this->user(),
            SaveOptions::fromAssoc([])
        );

        $this->addToAssertionCount(1);
    }

    public function testSkipAllDoesNotCheck(): void
    {
        $uniqueness = $this->createMock(UserEmailUniqueness::class);
        $uniqueness->expects($this->never())->method('hasConflict');

        $hook = new EnforceUniqueEmail($uniqueness, $this->language());
        $hook->beforeSave(
            $this->user(),
            SaveOptions::fromAssoc([SaveOption::SKIP_ALL => true])
        );
    }

    public function testSystemUserIsSkipped(): void
    {
        $uniqueness = $this->createMock(UserEmailUniqueness::class);
        $uniqueness->expects($this->never())->method('hasConflict');

        $hook = new EnforceUniqueEmail($uniqueness, $this->language());
        $hook->beforeSave(
            $this->user('system'),
            SaveOptions::fromAssoc([])
        );
    }

    private function hook(bool $hasConflict): EnforceUniqueEmail
    {
        $uniqueness = $this->createMock(UserEmailUniqueness::class);
        $uniqueness->method('hasConflict')->willReturn($hasConflict);

        return new EnforceUniqueEmail($uniqueness, $this->language());
    }

    private function language(): Language
    {
        $language = $this->createMock(Language::class);
        $language->method('translate')->willReturn('This email is already used by another CRM user.');

        return $language;
    }

    private function user(string $type = 'regular'): Entity
    {
        $user = $this->createMock(Entity::class);
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'type' ? $type : null
        );

        return $user;
    }
}
