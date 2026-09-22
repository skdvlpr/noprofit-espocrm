<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\GoogleIntegration;

use Espo\Modules\GoogleIntegration\Tools\OAuth\RefreshToken;
use PHPUnit\Framework\TestCase;

class OAuthRefreshTokenTest extends TestCase
{
    public function testFromAuthorizationResultReturnsIssuedToken(): void
    {
        $this->assertSame(
            '1//new-refresh',
            RefreshToken::fromAuthorizationResult(['refresh_token' => '1//new-refresh'])
        );
    }

    public function testFromAuthorizationResultAcceptsEspoKey(): void
    {
        $this->assertSame(
            '1//stored',
            RefreshToken::fromAuthorizationResult(['refreshToken' => '1//stored'])
        );
    }

    public function testFromAuthorizationResultOmitsMissingOrEmpty(): void
    {
        $this->assertNull(RefreshToken::fromAuthorizationResult(['access_token' => 'ya29.xxx']));
        $this->assertNull(RefreshToken::fromAuthorizationResult(['refresh_token' => '']));
        $this->assertNull(RefreshToken::fromAuthorizationResult(['refresh_token' => null]));
    }

    public function testShouldWriteRejectsEmptyValues(): void
    {
        $this->assertTrue(RefreshToken::shouldWrite('1//keep'));
        $this->assertFalse(RefreshToken::shouldWrite(null));
        $this->assertFalse(RefreshToken::shouldWrite(''));
        $this->assertFalse(RefreshToken::shouldWrite(0));
    }
}
