<?php

namespace Espo\Modules\GoogleIntegration\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarSyncRunner;
use Throwable;

/**
 * Background CRM ↔ Google Calendar sync per ExternalAccount.calendarSyncMode.
 */
class SyncCalendar implements JobDataLess
{
    public function __construct(
        private CalendarSyncRunner $calendarSyncRunner
    ) {}

    public function run(): void
    {
        try {
            $this->calendarSyncRunner->run();
        } catch (Throwable) {
            // Errors are logged per user inside CalendarSyncRunner.
        }
    }
}
