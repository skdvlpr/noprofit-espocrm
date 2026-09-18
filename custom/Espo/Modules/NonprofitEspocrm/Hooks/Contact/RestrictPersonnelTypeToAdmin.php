<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Contact;

use Espo\Core\ApplicationState;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Volunteer / Employee Contact type is admin-only.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 *
 * @implements BeforeSave<\Espo\Modules\Crm\Entities\Contact>
 */
class RestrictPersonnelTypeToAdmin implements BeforeSave
{
    public static int $order = 5;

    /** @var list<string> */
    private const PERSONNEL_TYPES = ['Volunteer', 'Employee'];

    public function __construct(
        private ApplicationState $applicationState
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $type = trim((string) ($entity->get('contactType') ?? ''));

        if (!in_array($type, self::PERSONNEL_TYPES, true)) {
            return;
        }

        if (!$entity->isNew() && !$entity->isAttributeChanged('contactType')) {
            return;
        }

        // CLI / system user is not a staff login. Record ACL still applies in the UI.
        if (!$this->applicationState->isLogged()) {
            return;
        }

        if ($this->applicationState->isAdmin()) {
            return;
        }

        throw new Forbidden("Only an administrator can set Contact type Volunteer or Employee.");
    }
}
