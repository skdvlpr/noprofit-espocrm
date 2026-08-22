<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\GoogleIntegration;

use Espo\Modules\GoogleIntegration\Tools\Calendar\SyncMode;
use PHPUnit\Framework\TestCase;

class SyncModeTest extends TestCase
{
    public function testDefaultIsNone(): void
    {
        $this->assertSame(SyncMode::NONE, SyncMode::DEFAULT);
    }

    public function testIsValidRecognizesLegacyValues(): void
    {
        $this->assertTrue(SyncMode::isValid(SyncMode::NONE));
        $this->assertTrue(SyncMode::isValid(SyncMode::CRM_TO_GOOGLE));
        $this->assertTrue(SyncMode::isValid(SyncMode::GOOGLE_TO_CRM));
        $this->assertTrue(SyncMode::isValid(SyncMode::BIDIRECTIONAL));
    }

    public function testIsValidRejectsUnknown(): void
    {
        $this->assertFalse(SyncMode::isValid(null));
        $this->assertFalse(SyncMode::isValid('invalid'));
    }
}
