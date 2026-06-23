<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\VolunteerEmployee;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\PersonContactSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class SyncLinkedUserContacts implements BeforeSave
{
    public static int $order = 15;

    public function __construct(private PersonContactSync $personContactSync) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->personContactSync->process($entity, $options);
    }
}
