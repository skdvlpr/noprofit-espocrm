<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Utils\Language;
use Espo\Modules\NonprofitEspocrm\Hooks\Contact\EnforceUniqueEmail;
use Espo\Modules\NonprofitEspocrm\Tools\ContactEmailUniqueness;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\TestCase;

/**
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 */
class ContactEnforceUniqueEmailTest extends TestCase
{
    public function testConflictWhenAnotherContactOwnsAddress(): void
    {
        $this->expectException(Conflict::class);

        $this->hook(hasConflict: true)->beforeSave(
            $this->contact(),
            SaveOptions::fromAssoc([])
        );
    }

    public function testAllowsAddressSharedOnlyWithLinkedUser(): void
    {
        $this->hook(hasConflict: false)->beforeSave(
            $this->contact(),
            SaveOptions::fromAssoc([])
        );

        $this->addToAssertionCount(1);
    }

    public function testSkipAllDoesNotCheck(): void
    {
        $uniqueness = $this->createMock(ContactEmailUniqueness::class);
        $uniqueness->expects($this->never())->method('hasConflict');

        $hook = new EnforceUniqueEmail($uniqueness, $this->language());
        $hook->beforeSave(
            $this->contact(),
            SaveOptions::fromAssoc([SaveOption::SKIP_ALL => true])
        );
    }

    private function hook(bool $hasConflict): EnforceUniqueEmail
    {
        $uniqueness = $this->createMock(ContactEmailUniqueness::class);
        $uniqueness->method('hasConflict')->willReturn($hasConflict);

        return new EnforceUniqueEmail($uniqueness, $this->language());
    }

    private function language(): Language
    {
        $language = $this->createMock(Language::class);
        $language->method('translate')->willReturn('This email is already used by another contact.');

        return $language;
    }

    private function contact(): Entity
    {
        return $this->createMock(Entity::class);
    }
}
