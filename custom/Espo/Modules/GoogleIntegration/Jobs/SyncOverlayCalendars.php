<?php

namespace Espo\Modules\GoogleIntegration\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Modules\GoogleIntegration\Tools\Calendar\OverlaySyncRunner;
use Throwable;

/**
 * Pull selected personal Google calendars into CRM overlay entity + enforce 30-day retention.
 */
class SyncOverlayCalendars implements JobDataLess
{
    public function __construct(
        private OverlaySyncRunner $overlaySyncRunner
    ) {}

    public function run(): void
    {
        try {
            $this->overlaySyncRunner->run();
        } catch (Throwable) {
            // Per-user errors are logged inside OverlaySyncRunner.
        }
    }
}
