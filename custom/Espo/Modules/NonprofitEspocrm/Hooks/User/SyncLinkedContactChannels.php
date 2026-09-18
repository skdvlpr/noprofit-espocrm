<?php

declare(strict_types=1);

namespace Espo\Modules\NonprofitEspocrm\Hooks\User;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Modules\NonprofitEspocrm\Tools\ContactUserChannelSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Copy User email and phone sets to the linked Volunteer/Employee Contact.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 *
 * @implements AfterSave<\Espo\Entities\User>
 */
class SyncLinkedContactChannels implements AfterSave
{
    public static int $order = 30;

    public function __construct(
        private ContactUserChannelSync $contactUserChannelSync
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $this->contactUserChannelSync->afterUserSave($entity, $options);
    }
}
