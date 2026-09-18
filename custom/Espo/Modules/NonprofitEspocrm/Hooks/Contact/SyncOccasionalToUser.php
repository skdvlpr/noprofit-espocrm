<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Contact;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * 004.2: isOccasional lives on Contact. User shows it via ContactProfileLoader.
 * This hook MUST NOT write a second copy onto User.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 *
 * @implements AfterSave<\Espo\Modules\Crm\Entities\Contact>
 */
class SyncOccasionalToUser implements AfterSave
{
    public static int $order = 25;

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
    }
}
