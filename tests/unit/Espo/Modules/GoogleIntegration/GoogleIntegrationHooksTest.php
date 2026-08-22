<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\GoogleIntegration;

use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\GoogleIntegration\Hooks\ExternalAccount\CalendarSyncMode;
use Espo\Modules\GoogleIntegration\Hooks\GoogleCalendarOverlayEvent\BeforeSave as OverlayBeforeSave;
use Espo\Modules\GoogleIntegration\Tools\Calendar\OverlaySyncRunner;
use Espo\Modules\GoogleIntegration\Tools\Calendar\SyncMode;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\TestCase;

class GoogleIntegrationHooksTest extends TestCase
{
    public function testCalendarSyncModeHookSetsNoneForGoogleExternalAccount(): void
    {
        $hook = new CalendarSyncMode();
        $entity = $this->createMock(Entity::class);
        $entity->method('getId')->willReturn(Installer::INTEGRATION_ID . '__user123');
        $entity->expects($this->once())
            ->method('set')
            ->with('calendarSyncMode', SyncMode::NONE);

        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
    }

    public function testCalendarSyncModeHookIgnoresNonGoogleExternalAccount(): void
    {
        $hook = new CalendarSyncMode();
        $entity = $this->createMock(Entity::class);
        $entity->method('getId')->willReturn('OtherIntegration__user123');
        $entity->expects($this->never())->method('set');

        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
    }

    public function testOverlayBeforeSaveBlocksManualCreate(): void
    {
        $hook = new OverlayBeforeSave();

        $this->expectException(Forbidden::class);
        $this->expectExceptionMessage('managed by sync only');

        $hook->beforeSave($this->createMock(Entity::class), SaveOptions::fromAssoc([]));
    }

    public function testOverlayBeforeSaveAllowsSyncOption(): void
    {
        $hook = new OverlayBeforeSave();
        $options = SaveOptions::fromAssoc([OverlaySyncRunner::SAVE_OPTION_SYNC => true]);

        $hook->beforeSave($this->createMock(Entity::class), $options);

        $this->addToAssertionCount(1);
    }
}
