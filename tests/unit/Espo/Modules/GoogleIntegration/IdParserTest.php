<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\GoogleIntegration;

use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\GoogleIntegration\Tools\ExternalAccount\IdParser;
use PHPUnit\Framework\TestCase;

class IdParserTest extends TestCase
{
    public function testParseValidId(): void
    {
        $parsed = IdParser::parse('GoogleCalendarDrive__user123');

        $this->assertSame([
            'integration' => 'GoogleCalendarDrive',
            'userId' => 'user123',
        ], $parsed);
    }

    public function testParseRejectsMissingUserId(): void
    {
        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Invalid external account id.');

        IdParser::parse('GoogleCalendarDrive__');
    }

    public function testParseRejectsMissingIntegration(): void
    {
        $this->expectException(BadRequest::class);

        IdParser::parse('__user123');
    }

    public function testParseRejectsPlainId(): void
    {
        $this->expectException(BadRequest::class);

        IdParser::parse('not-an-external-account-id');
    }
}
