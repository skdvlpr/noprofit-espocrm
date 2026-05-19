<?php

namespace Espo\Modules\GoogleIntegration\Hooks\Opportunity;

use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSaveHook<Entity>
 */
class BeforeSave implements BeforeSaveHook
{
    private const ALLOWED_DATE_LIST = ['presentationDate', 'closeDate'];

    public static int $order = 9;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isAttributeChanged('googleCalendarOpportunityDateList')) {
            return;
        }

        $selected = $entity->get('googleCalendarOpportunityDateList');

        if (!is_array($selected)) {
            $entity->set('googleCalendarOpportunityDateList', ['closeDate']);

            return;
        }

        $filtered = array_values(array_unique(array_filter(
            $selected,
            fn ($item) => is_string($item) && in_array($item, self::ALLOWED_DATE_LIST, true)
        )));

        if ($filtered === []) {
            $filtered = ['closeDate'];
        }

        $entity->set('googleCalendarOpportunityDateList', $filtered);
    }
}
