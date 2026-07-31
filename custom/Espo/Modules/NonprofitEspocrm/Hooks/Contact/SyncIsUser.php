<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Contact;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Keep Contact.isUser in sync with linkedUser / portalUser (optional both ways).
 */
class SyncIsUser implements BeforeSave
{
    public static int $order = 8;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        $linked = trim((string) ($entity->get('linkedUserId') ?? ''));
        $portal = trim((string) ($entity->get('portalUserId') ?? ''));

        $entity->set('isUser', $linked !== '' || $portal !== '');
    }
}
