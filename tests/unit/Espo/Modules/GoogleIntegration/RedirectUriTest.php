<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\GoogleIntegration;

use Espo\Core\Utils\Config;
use Espo\Modules\GoogleIntegration\Tools\OAuth\RedirectUri;
use PHPUnit\Framework\TestCase;

class RedirectUriTest extends TestCase
{
    public function testBuildAppendsEntryPointQuery(): void
    {
        $config = $this->createConfig(['siteUrl' => 'https://crm.example.com']);

        $this->assertSame(
            'https://crm.example.com?entryPoint=oauthCallback',
            RedirectUri::build($config)
        );
    }

    public function testAllowedListIncludesSlashVariantWhenDifferent(): void
    {
        $config = $this->createConfig(['siteUrl' => 'https://crm.example.com']);

        $list = RedirectUri::allowedList($config);

        $this->assertContains('https://crm.example.com/?entryPoint=oauthCallback', $list);
        $this->assertContains('https://crm.example.com?entryPoint=oauthCallback', $list);
        $this->assertCount(2, $list);
    }

    public function testAllowedListDedupesWhenSiteUrlHasTrailingSlash(): void
    {
        $config = $this->createConfig(['siteUrl' => 'https://crm.example.com/']);

        $this->assertCount(1, RedirectUri::allowedList($config));
    }

    public function testResolveAcceptsAllowedClientUri(): void
    {
        $config = $this->createConfig(['siteUrl' => 'https://crm.example.com']);
        $allowed = RedirectUri::allowedList($config);

        $this->assertSame($allowed[0], RedirectUri::resolve($config, $allowed[0]));
    }

    public function testResolveFallsBackToCanonicalWhenClientUriInvalid(): void
    {
        $config = $this->createConfig(['siteUrl' => 'https://crm.example.com']);

        $this->assertSame(
            RedirectUri::build($config),
            RedirectUri::resolve($config, 'https://evil.example.com/callback')
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createConfig(array $values): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            static fn (string $key) => $values[$key] ?? null
        );

        return $config;
    }
}
