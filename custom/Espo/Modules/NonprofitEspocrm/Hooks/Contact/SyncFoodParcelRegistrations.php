<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Contact;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\FoodParcelContactSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

class SyncFoodParcelRegistrations implements AfterSave
{
    public static int $order = 15;

    public function __construct(
        private FoodParcelContactSync $foodParcelContactSync,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if (!$entity->isAttributeChanged('taxCode')
            && !$entity->isAttributeChanged('phoneNumber')
            && !$entity->isAttributeChanged('phoneNumberData')
            && !$entity->isAttributeChanged('addressStreet')
            && !$entity->isAttributeChanged('addressCity')
            && !$entity->isAttributeChanged('addressState')
            && !$entity->isAttributeChanged('addressCountry')
            && !$entity->isAttributeChanged('addressPostalCode')) {
            return;
        }

        $contactId = $entity->getId();

        if ($contactId === null || $contactId === '') {
            return;
        }

        $this->foodParcelContactSync->syncRegistrationsForContactId($contactId);
    }
}
