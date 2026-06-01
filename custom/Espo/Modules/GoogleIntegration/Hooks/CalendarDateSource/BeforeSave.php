<?php

namespace Espo\Modules\GoogleIntegration\Hooks\CalendarDateSource;

use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSaveHook<Entity>
 */
class BeforeSave implements BeforeSaveHook
{
    public static int $order = 9;

    public function __construct(
        private Metadata $metadata
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $targetEntityType = $entity->get('targetEntityType');
        $dateField = $entity->get('dateField');

        if (!is_string($targetEntityType) || $targetEntityType === '' || !is_string($dateField) || $dateField === '') {
            return;
        }

        $fieldType = $this->metadata->get(['entityDefs', $targetEntityType, 'fields', $dateField, 'type']);

        if ($fieldType === 'date') {
            $entity->set('allDay', true);
        }
    }
}
