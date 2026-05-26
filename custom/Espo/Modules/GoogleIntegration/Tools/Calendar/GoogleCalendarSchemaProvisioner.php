<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\DataManager;
use Espo\Core\Utils\Log;

/**
 * Adds Google Calendar field columns when a new CalendarDateSource target entity is enabled.
 */
class GoogleCalendarSchemaProvisioner
{
    public function __construct(
        private DataManager $dataManager,
        private Log $log
    ) {}

    public function provisionEntityType(string $entityType): void
    {
        try {
            $this->dataManager->rebuildDatabase([$entityType]);
        } catch (\Throwable $e) {
            $this->log->error(
                'Google Calendar schema rebuild failed for '
                . $entityType
                . ': '
                . $e->getMessage()
            );

            throw $e;
        }
    }
}
