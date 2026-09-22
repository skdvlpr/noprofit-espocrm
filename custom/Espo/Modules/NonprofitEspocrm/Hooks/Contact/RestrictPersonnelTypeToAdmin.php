<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Contact;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\PersonnelTypeRestriction;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Volunteer / Employee in the type list is admin-only (adding those keys).
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 *
 * @implements BeforeSave<\Espo\Modules\Crm\Entities\Contact>
 */
class RestrictPersonnelTypeToAdmin implements BeforeSave
{
    public static int $order = 5;

    public function __construct(
        private PersonnelTypeRestriction $restriction
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->restriction->assertMaySave($entity);
    }
}
