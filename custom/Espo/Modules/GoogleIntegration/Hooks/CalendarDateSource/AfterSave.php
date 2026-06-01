<?php

namespace Espo\Modules\GoogleIntegration\Hooks\CalendarDateSource;

use Espo\Core\DataManager;
use Espo\Core\Hook\Hook\AfterSave as AfterSaveHook;
use Espo\Core\InjectableFactory;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DefaultCalendarTemplateProvisioner;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarLayoutProvisioner;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarSchemaProvisioner;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Regenerates layout files and DB columns when date source configuration changes.
 * Uses per-entity schema rebuild (not full rebuild) to avoid memory spikes.
 *
 * @implements AfterSaveHook<Entity>
 */
class AfterSave implements AfterSaveHook
{
    public static int $order = 100;

    public function __construct(
        private InjectableFactory $injectableFactory,
        private DataManager $dataManager
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if (
            !$entity->isNew()
            && !$entity->isAttributeChanged('targetEntityType')
            && !$entity->isAttributeChanged('isActive')
        ) {
            return;
        }

        $targetEntityType = $entity->get('targetEntityType');

        if (!is_string($targetEntityType) || $targetEntityType === '') {
            return;
        }

        if (!(bool) $entity->get('isActive')) {
            $this->injectableFactory
                ->create(GoogleCalendarLayoutProvisioner::class)
                ->provisionEntityType($targetEntityType);

            return;
        }

        $this->injectableFactory
            ->create(GoogleCalendarSchemaProvisioner::class)
            ->provisionEntityType($targetEntityType);

        $this->injectableFactory
            ->create(GoogleCalendarLayoutProvisioner::class)
            ->provisionEntityType($targetEntityType);

        $this->injectableFactory
            ->create(DefaultCalendarTemplateProvisioner::class)
            ->ensureForEntityType($targetEntityType);

        $this->dataManager->rebuild();
    }
}
