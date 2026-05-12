<?php

namespace Espo\Modules\SafehouseCrm\Hooks\VolontarioDipendente;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\SafehouseCrm\Tools\PersonContactSync;
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
