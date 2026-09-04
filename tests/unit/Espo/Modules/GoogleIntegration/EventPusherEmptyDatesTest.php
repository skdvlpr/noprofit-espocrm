<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\GoogleIntegration;

use Espo\Core\Acl;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\Htmlizer\TemplateRendererFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarDateTimeResolver;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarDisplayDateResolver;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarProvisioner;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarTemplateApplier;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventRemover;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarExportGuard;
use Espo\Modules\GoogleIntegration\Tools\Calendar\ManagerCalendarShare;
use Espo\Modules\GoogleIntegration\Tools\IntegrationState;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Clearing every selected date must still delete leftover Google Calendar events.
 */
class EventPusherEmptyDatesTest extends TestCase
{
    public function testEmptyDateFieldsStillRemoveStaleGoogleLinks(): void
    {
        $eventRemover = $this->createMock(EventRemover::class);
        $clientManager = $this->createMock(ClientManager::class);
        $dateSourceProvider = $this->createMock(DateSourceProvider::class);

        $dateSourceProvider->method('getActiveSourcesForEntityType')->willReturn([
            [
                'sourceDateType' => 'dateStart',
                'dateField' => 'dateStart',
            ],
        ]);

        $user = $this->createUser();
        $entity = $this->createTaskEntity(dateStart: null, dateSourceList: ['dateStart']);

        $eventRemover->expects($this->once())
            ->method('removeStaleDateSourceLinks')
            ->with($entity, $user, []);

        $clientManager->expects($this->never())->method('create');

        $this->createPusher($user, $dateSourceProvider, $eventRemover, $clientManager)
            ->pushIfRequested($entity);
    }

    public function testNoConfiguredDateSourcesDoesNotCallRemover(): void
    {
        $eventRemover = $this->createMock(EventRemover::class);
        $clientManager = $this->createMock(ClientManager::class);
        $dateSourceProvider = $this->createMock(DateSourceProvider::class);

        $dateSourceProvider->method('getActiveSourcesForEntityType')->willReturn([]);

        $eventRemover->expects($this->never())->method('removeStaleDateSourceLinks');
        $clientManager->expects($this->never())->method('create');

        $this->createPusher(
            $this->createUser(),
            $dateSourceProvider,
            $eventRemover,
            $clientManager
        )->pushIfRequested(
            $this->createTaskEntity(dateStart: '2026-09-04 10:00:00', dateSourceList: ['dateStart'])
        );
    }

    private function createPusher(
        User $user,
        DateSourceProvider $dateSourceProvider,
        EventRemover $eventRemover,
        ClientManager $clientManager,
    ): EventPusher {
        $guard = $this->createMock(GoogleCalendarExportGuard::class);
        $guard->method('assertExportAllowed');

        $acl = $this->createMock(Acl::class);
        $acl->method('checkEntityEdit')->willReturn(true);

        $displayResolver = $this->createMock(CalendarDisplayDateResolver::class);
        $displayResolver->method('isDateTimeOptionalAllDay')->willReturn(false);
        $displayResolver->method('resolveDateOnly')->willReturn(null);

        return new EventPusher(
            $this->createMock(EntityManager::class),
            $clientManager,
            $this->createMock(TemplateRendererFactory::class),
            $this->createMock(Config::class),
            $this->createMock(Metadata::class),
            $user,
            $this->createMock(Log::class),
            $dateSourceProvider,
            $this->createMock(CalendarTemplateApplier::class),
            $this->createMock(IntegrationState::class),
            $guard,
            $acl,
            $eventRemover,
            $displayResolver,
            $this->createMock(CalendarDateTimeResolver::class),
            $this->createMock(CalendarProvisioner::class),
            $this->createMock(ManagerCalendarShare::class),
        );
    }

    private function createUser(): User&MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-1');
        $user->method('isApi')->willReturn(false);

        return $user;
    }

    private function createTaskEntity(?string $dateStart, array $dateSourceList): Entity&MockObject
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('getEntityType')->willReturn('Task');
        $entity->method('getId')->willReturn('task-1');
        $entity->method('getAttributeType')->willReturn('datetime');
        $entity->method('get')->willReturnCallback(
            static function (string $field) use ($dateStart, $dateSourceList): mixed {
                return match ($field) {
                    'saveToGoogleCalendar' => true,
                    'googleCalendarDateSourceList' => $dateSourceList,
                    'dateStart' => $dateStart,
                    default => null,
                };
            }
        );

        return $entity;
    }
}
