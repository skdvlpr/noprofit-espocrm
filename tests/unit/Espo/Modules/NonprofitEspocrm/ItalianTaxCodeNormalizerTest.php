<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\ItalianTaxCodeNormalizer;
use PHPUnit\Framework\TestCase;

class ItalianTaxCodeNormalizerTest extends TestCase
{
    public function testNullAndEmptyPassThrough(): void
    {
        $this->assertNull(ItalianTaxCodeNormalizer::normalize(null));
        $this->assertSame('', ItalianTaxCodeNormalizer::normalize(''));
    }

    public function testLowercaseCodiceFiscaleIsUppercased(): void
    {
        $this->assertSame('RSSMRA85T10A562S', ItalianTaxCodeNormalizer::normalize('rssmra85t10a562s'));
    }

    public function testWhitespaceIsTrimmed(): void
    {
        $this->assertSame('RSSMRA85T10A562S', ItalianTaxCodeNormalizer::normalize(' rssmra85t10a562s '));
    }

    public function testNonStringPassThrough(): void
    {
        $this->assertSame(12345678901, ItalianTaxCodeNormalizer::normalize(12345678901));
    }
}
