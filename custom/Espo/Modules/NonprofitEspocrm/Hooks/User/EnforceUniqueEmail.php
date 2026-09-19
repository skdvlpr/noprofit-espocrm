<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\User;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Language;
use Espo\Modules\NonprofitEspocrm\Tools\UserEmailUniqueness;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Hard unique login email among Users. Record-API UniqueAmongUsers is skipped
 * on EntityManager::saveEntity (Contact→User channel sync).
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 *
 * @implements BeforeSave<\Espo\Entities\User>
 */
class EnforceUniqueEmail implements BeforeSave
{
    public static int $order = 8;

    public function __construct(
        private UserEmailUniqueness $userEmailUniqueness,
        private Language $language
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if ((string) ($entity->get('type') ?? '') === 'system') {
            return;
        }

        if (!$this->userEmailUniqueness->hasConflict($entity)) {
            return;
        }

        throw new Conflict(
            (string) $this->language->translate('fieldNotUniqueAmongUsers', 'messages', 'User')
        );
    }
}
