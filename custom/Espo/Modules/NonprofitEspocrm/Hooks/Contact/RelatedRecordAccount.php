<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Contact;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Contact;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Keeps native accountId in sync with relatedRecord (single Account link)
 * so Account detail → Contatti panel continues to work via core CRM hooks.
 */
class RelatedRecordAccount implements BeforeSave
{
    public static int $order = 8;

    public function __construct(private EntityManager $entityManager) {}

    /**
     * @param Contact $entity
     */
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $relatedChanged = $entity->isAttributeChanged('relatedRecordId')
            || $entity->isAttributeChanged('relatedRecordName');

        if ($relatedChanged) {
            $accountId = $entity->get('relatedRecordId');

            if ($accountId) {
                $entity->set('accountId', $accountId);

                if (!$entity->get('relatedRecordName')) {
                    $account = $this->entityManager
                        ->getRDBRepositoryByClass(Account::class)
                        ->select([Attribute::ID, 'name'])
                        ->where([Attribute::ID => $accountId])
                        ->findOne();

                    if ($account) {
                        $entity->set('relatedRecordName', $account->get('name'));
                        $entity->set('accountName', $account->get('name'));
                    }
                } else {
                    $entity->set('accountName', $entity->get('relatedRecordName'));
                }
            } else {
                $entity->set('accountId', null);
                $entity->set('accountName', null);
            }

            return;
        }

        if (!$entity->get('relatedRecordId') && $entity->get('accountId')) {
            $entity->set('relatedRecordId', $entity->get('accountId'));
            $entity->set('relatedRecordName', $entity->get('accountName'));
        }
    }
}
