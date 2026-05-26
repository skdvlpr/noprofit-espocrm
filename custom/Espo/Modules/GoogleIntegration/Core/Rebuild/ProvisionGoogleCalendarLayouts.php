<?php

namespace Espo\Modules\GoogleIntegration\Core\Rebuild;

use Espo\Core\InjectableFactory;
use Espo\Core\Rebuild\RebuildAction;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarLayoutProvisioner;

class ProvisionGoogleCalendarLayouts implements RebuildAction
{
    public function __construct(
        private InjectableFactory $injectableFactory
    ) {}

    public function process(): void
    {
        $this->injectableFactory
            ->create(GoogleCalendarLayoutProvisioner::class)
            ->provisionAll();
    }
}
