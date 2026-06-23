<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\ApplicationState;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Log;
use Espo\Modules\GoogleIntegration\Tools\IntegrationState;
use Espo\ORM\Entity;

/**
 * Rejects entity saves that request Google Calendar export when the integration is globally disabled.
 */
class GoogleCalendarExportGuard
{
    public const MESSAGE_LABEL = 'googleCalendarIntegrationDisabled';

    public function __construct(
        private IntegrationState $integrationState,
        private ApplicationState $applicationState,
        private Log $log,
    ) {}

    public function assertExportAllowed(Entity $entity): void
    {
        if (!$entity->get('saveToGoogleCalendar')) {
            return;
        }

        if ($this->integrationState->isGoogleCalendarDriveEnabled()) {
            return;
        }

        $user = $this->applicationState->getUser();
        $userId = $user->getId();

        $this->log->warning(
            'Google Calendar export rejected: integration disabled for '
            . $entity->getEntityType()
            . ' '
            . ($entity->getId() ?? 'new')
            . ' user '
            . (is_string($userId) ? $userId : 'unknown')
        );

        throw new BadRequest(
            'Google Calendar integration is disabled. Turn it on under Administration → Integrations, '
            . 'or uncheck Save in Google Calendar.'
        );
    }
}
