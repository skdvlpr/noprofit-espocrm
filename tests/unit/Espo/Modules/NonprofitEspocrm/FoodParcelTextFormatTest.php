<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\FoodParcelTextFormat;
use PHPUnit\Framework\TestCase;

class FoodParcelTextFormatTest extends TestCase
{
    public function testFormatNotesPdfEscapesHtml(): void
    {
        $html = FoodParcelTextFormat::formatNotesPdf("Line one\n<b>Bold</b> & more");

        $this->assertStringContainsString('Line one', $html);
        $this->assertStringContainsString('&amp; more', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function testFormatNotesPdfNullReturnsEmpty(): void
    {
        $this->assertSame('', FoodParcelTextFormat::formatNotesPdf(null));
    }

    public function testFormatDatesListSortsAndFormats(): void
    {
        $result = FoodParcelTextFormat::formatDatesList(['2026-03-01', '2026-01-15']);

        $this->assertSame("01.03.2026\n15.01.2026", $result);
    }

    public function testFormatDatesListNonArrayReturnsEmpty(): void
    {
        $this->assertSame('', FoodParcelTextFormat::formatDatesList('not-an-array'));
    }
}
