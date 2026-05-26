<?php

namespace Espo\Modules\GoogleIntegration\Hooks\CalendarDateSource;

use Espo\Core\Hook\Hook\AfterRemove as AfterRemoveHook;
use Espo\Core\InjectableFactory;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarLayoutProvisioner;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;

/**
 * @implements AfterRemoveHook<Entity>
 */
class AfterRemove implements AfterRemoveHook
{
    public function __construct(
        private InjectableFactory $injectableFactory
    ) {}

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        $this->injectableFactory
            ->create(GoogleCalendarLayoutProvisioner::class)
            ->provisionAll();
    }
}
