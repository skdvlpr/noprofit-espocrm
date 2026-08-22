<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\GoogleIntegration;

use Espo\Core\ApplicationState;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarExportGuard;
use Espo\Modules\GoogleIntegration\Tools\IntegrationState;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;

class GoogleCalendarExportGuardTest extends TestCase
{
    public function testAssertExportAllowedSkipsWhenSaveToGoogleCalendarFalse(): void
    {
        $guard = $this->createGuard(integrationEnabled: false);

        $entity = $this->createMock(Entity::class);
        $entity->method('get')->with('saveToGoogleCalendar')->willReturn(false);

        $guard->assertExportAllowed($entity);

        $this->addToAssertionCount(1);
    }

    public function testAssertExportAllowedPassesWhenIntegrationEnabled(): void
    {
        $guard = $this->createGuard(integrationEnabled: true);

        $entity = $this->createMock(Entity::class);
        $entity->method('get')->willReturnMap([
            ['saveToGoogleCalendar', true],
        ]);

        $guard->assertExportAllowed($entity);

        $this->addToAssertionCount(1);
    }

    public function testAssertExportAllowedThrowsWhenIntegrationDisabled(): void
    {
        $guard = $this->createGuard(integrationEnabled: false);

        $entity = $this->createMock(Entity::class);
        $entity->method('get')->willReturnCallback(
            static fn (string $field) => $field === 'saveToGoogleCalendar' ? true : null
        );
        $entity->method('getEntityType')->willReturn('Meeting');
        $entity->method('getId')->willReturn('meeting-id');

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Google Calendar integration is disabled');

        $guard->assertExportAllowed($entity);
    }

    private function createGuard(bool $integrationEnabled): GoogleCalendarExportGuard
    {
        $integrationState = $this->createMock(IntegrationState::class);
        $integrationState->method('isGoogleCalendarDriveEnabled')->willReturn($integrationEnabled);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-id');

        $applicationState = $this->createMock(ApplicationState::class);
        $applicationState->method('getUser')->willReturn($user);

        $log = $this->createMock(Log::class);

        return new GoogleCalendarExportGuard($integrationState, $applicationState, $log);
    }
}
