<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\CaseObj\WebsiteReference;
use PHPUnit\Framework\TestCase;

class WebsiteReferenceTest extends TestCase
{
    public function testPrefixForKnownTypes(): void
    {
        $this->assertSame('sd', WebsiteReference::prefixForType('SportelloDigitale'));
        $this->assertSame('sl', WebsiteReference::prefixForType('SportelloLegale'));
        $this->assertSame('rg', WebsiteReference::prefixForType('RichiestaGenerica'));
    }

    public function testPrefixForUnknownOrEmptyTypeUsesDefault(): void
    {
        $this->assertSame('sh', WebsiteReference::prefixForType('OtherType'));
        $this->assertSame('sh', WebsiteReference::prefixForType(''));
        $this->assertSame('sh', WebsiteReference::prefixForType('   '));
    }

    public function testBuildNormalizesCaseAndWhitespace(): void
    {
        $this->assertSame('sd-abc123', WebsiteReference::build(' SD ', ' ABC123 '));
    }

    public function testExtractCorrelationTokenFromExplicitLine(): void
    {
        $source = "Subject: help\nCorrelation: corr-abc-123\nBody text";

        $this->assertSame('corr-abc-123', WebsiteReference::extractCorrelationToken($source));
    }

    public function testExtractCorrelationTokenFromBracketedCorrUuid(): void
    {
        $this->assertSame(
            '550e8400-e29b-41d4-a716-446655440000',
            WebsiteReference::extractCorrelationToken('[corr-550e8400-e29b-41d4-a716-446655440000]')
        );
    }

    public function testExtractCorrelationTokenFromLegacyBracketedPrefix(): void
    {
        $this->assertSame(
            '550e8400-e29b-41d4-a716-446655440000',
            WebsiteReference::extractCorrelationToken('[sd-550e8400-e29b-41d4-a716-446655440000]')
        );
    }

    public function testExtractCorrelationTokenEmptySourceReturnsNull(): void
    {
        $this->assertNull(WebsiteReference::extractCorrelationToken(''));
        $this->assertNull(WebsiteReference::extractCorrelationToken('no token here'));
    }

    public function testExtractFromTextDeprecatedReturnsFullBracketedId(): void
    {
        $this->assertSame(
            'sd-abc123',
            WebsiteReference::extractFromText('[sd-abc123] in subject')
        );
    }

    public function testMintForTypeProducesPrefixedUuid(): void
    {
        $reference = WebsiteReference::mintForType('SportelloDigitale');

        $this->assertMatchesRegularExpression(
            '/^sd-[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $reference
        );
    }
}
