<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Contact;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Mirror Contact.isOccasional → linked User.isOccasional (bidirectional UX).
 *
 * @implements AfterSave<\Espo\Modules\Crm\Entities\Contact>
 */
class SyncOccasionalToUser implements AfterSave
{
    public static int $order = 25;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if (!$entity->isNew() && !$entity->isAttributeChanged('isOccasional')) {
            return;
        }

        $userId = trim((string) ($entity->get('linkedUserId') ?? ''));

        if ($userId === '') {
            return;
        }

        $user = $this->entityManager->getEntityById('User', $userId);

        if (!$user) {
            return;
        }

        $isOccasional = (bool) $entity->get('isOccasional');

        if ((bool) $user->get('isOccasional') === $isOccasional) {
            return;
        }

        $user->set('isOccasional', $isOccasional);
        $this->entityManager->saveEntity($user, [
            SaveOption::SKIP_ALL => true,
        ]);
    }
}
