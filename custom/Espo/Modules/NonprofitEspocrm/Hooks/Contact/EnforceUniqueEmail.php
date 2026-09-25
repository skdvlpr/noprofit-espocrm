<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Contact;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Language;
use Espo\Modules\NonprofitEspocrm\Tools\ContactEmailUniqueness;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Hard unique email among Contacts. Record-API UniqueAmongContacts is skipped
 * on EntityManager::saveEntity (User→Contact channel sync).
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 *
 * @implements BeforeSave<\Espo\Modules\Crm\Entities\Contact>
 */
class EnforceUniqueEmail implements BeforeSave
{
    public static int $order = 8;

    public function __construct(
        private ContactEmailUniqueness $contactEmailUniqueness,
        private Language $language
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if (!$this->contactEmailUniqueness->hasConflict($entity)) {
            return;
        }

        throw new Conflict(
            (string) $this->language->translate('fieldNotUniqueAmongContacts', 'messages', 'Contact')
        );
    }
}
