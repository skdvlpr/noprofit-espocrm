<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\ContactTypeEnumToMulti;
use PHPUnit\Framework\TestCase;

class ContactTypeEnumToMultiTest extends TestCase
{
    public function testEmptyValues(): void
    {
        foreach ([null, '', '   ', 0, false] as $value) {
            $this->assertSame(
                ['state' => ContactTypeEnumToMulti::STATE_EMPTY, 'list' => []],
                ContactTypeEnumToMulti::classify($value)
            );
        }
    }

    public function testOptionKeyStringWrapsToOneItemList(): void
    {
        $this->assertSame(
            ['state' => ContactTypeEnumToMulti::STATE_STRING, 'list' => ['Volunteer']],
            ContactTypeEnumToMulti::classify('Volunteer')
        );

        // Unexpected leftover key: still wrapped, never merged or dropped.
        $this->assertSame(
            ['state' => ContactTypeEnumToMulti::STATE_STRING, 'list' => ['Legacy']],
            ContactTypeEnumToMulti::classify(' Legacy ')
        );
    }

    public function testArrayAndJsonListAreAlreadyCopied(): void
    {
        $this->assertSame(
            ['state' => ContactTypeEnumToMulti::STATE_ARRAY, 'list' => ['Volunteer', 'MemberContact']],
            ContactTypeEnumToMulti::classify(['Volunteer', 'MemberContact'])
        );

        $this->assertSame(
            ['state' => ContactTypeEnumToMulti::STATE_ARRAY, 'list' => ['Employee']],
            ContactTypeEnumToMulti::classify('["Employee"]')
        );

        $this->assertSame(
            ['state' => ContactTypeEnumToMulti::STATE_ARRAY, 'list' => []],
            ContactTypeEnumToMulti::classify('[]')
        );

        $this->assertSame(
            ['state' => ContactTypeEnumToMulti::STATE_ARRAY, 'list' => ['Other']],
            ContactTypeEnumToMulti::classify(['Other', 1, null])
        );
    }

    public function testBrokenJsonLookingStringIsTreatedAsPlainKey(): void
    {
        $this->assertSame(
            ['state' => ContactTypeEnumToMulti::STATE_STRING, 'list' => ['[oops']],
            ContactTypeEnumToMulti::classify('[oops')
        );
    }
}
