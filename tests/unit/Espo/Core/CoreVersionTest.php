<?php

declare(strict_types=1);

namespace tests\unit\Espo\Core;

use PHPUnit\Framework\TestCase;

/**
 * Core version checks without touching the dev/prod database.
 */
class CoreVersionTest extends TestCase
{
    public function testPhpVersionIsSupported(): void
    {
        $this->assertTrue(
            version_compare(PHP_VERSION, '8.3.0', '>='),
            'PHP must be >= 8.3 for Espo 10.x'
        );
    }

    public function testEspoVersionIsTenSeries(): void
    {
        $version = $this->espocrmVersion();

        $this->assertMatchesRegularExpression(
            '/^10\.\d+\.\d+$/',
            $version,
            'Expected Espo 10.x, got: ' . $version
        );
    }

    public function testNonprofitManifestAcceptableVersionsIncludeCurrentCore(): void
    {
        $manifestPath = dirname(__DIR__, 4)
            . '/custom/Espo/Modules/NonprofitEspocrm/manifest.json';

        $this->assertFileExists($manifestPath);

        /** @var array{acceptableVersions?: list<string>} $manifest */
        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        $this->assertIsArray($manifest['acceptableVersions'] ?? null);

        $version = $this->espocrmVersion();
        $accepted = false;

        foreach ($manifest['acceptableVersions'] as $constraint) {
            if ($this->versionMatchesConstraint($version, $constraint)) {
                $accepted = true;
                break;
            }
        }

        $this->assertTrue($accepted, "Espo {$version} must match manifest acceptableVersions");
    }

    private function espocrmVersion(): string
    {
        $root = dirname(__DIR__, 4);
        $packagePath = $root . '/package.json';
        $this->assertFileExists($packagePath);

        /** @var array{version?: string} $package */
        $package = json_decode((string) file_get_contents($packagePath), true);

        return (string) ($package['version'] ?? '');
    }

    private function versionMatchesConstraint(string $version, string $constraint): bool
    {
        if (preg_match('/^>=([0-9.]+)\s*<([0-9.]+)$/', $constraint, $m)) {
            return version_compare($version, $m[1], '>=')
                && version_compare($version, $m[2], '<');
        }

        if (preg_match('/^>=([0-9.]+)$/', $constraint, $m)) {
            return version_compare($version, $m[1], '>=');
        }

        return false;
    }
}
