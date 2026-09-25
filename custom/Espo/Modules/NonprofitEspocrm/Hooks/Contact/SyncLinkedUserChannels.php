<?php

declare(strict_types=1);

namespace Espo\Modules\NonprofitEspocrm\Hooks\Contact;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Modules\NonprofitEspocrm\Tools\ContactUserChannelSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Copy Volunteer/Employee/Associato Contact name and email/phone sets
 * to the linked User.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 *
 * @implements AfterSave<\Espo\Modules\Crm\Entities\Contact>
 */
class SyncLinkedUserChannels implements AfterSave
{
    public static int $order = 30;

    public function __construct(
        private ContactUserChannelSync $contactUserChannelSync
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $this->contactUserChannelSync->afterContactSave($entity, $options);
    }
}
