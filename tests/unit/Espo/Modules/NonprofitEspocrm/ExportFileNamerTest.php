<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\Utils\Config;
use Espo\Modules\NonprofitEspocrm\Tools\Export\ExportFileNamer;
use PHPUnit\Framework\TestCase;

class ExportFileNamerTest extends TestCase
{
    public function testBuildBaseNameUsesConfiguredTimezone(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->with('timeZone')->willReturn('Europe/Rome');

        $namer = new ExportFileNamer($config);
        $baseName = $namer->buildBaseName('ActivityOfferSlot');

        $this->assertMatchesRegularExpression(
            '/^\d{8}-\d{4}-Export-ActivityOfferSlot$/',
            $baseName
        );
    }

    public function testBuildBaseNameFallsBackToUtcOnInvalidTimezone(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->with('timeZone')->willReturn('Not/A/Timezone');

        $namer = new ExportFileNamer($config);
        $baseName = $namer->buildBaseName('Contact');

        $this->assertStringEndsWith('-Export-Contact', $baseName);
        $this->assertMatchesRegularExpression('/^\d{8}-\d{4}-Export-Contact$/', $baseName);
    }

    public function testBuildBaseNameUsesUtcWhenTimezoneMissing(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->with('timeZone')->willReturn(null);

        $namer = new ExportFileNamer($config);

        $this->assertMatchesRegularExpression(
            '/^\d{8}-\d{4}-Export-MealCount$/',
            $namer->buildBaseName('MealCount')
        );
    }
}
