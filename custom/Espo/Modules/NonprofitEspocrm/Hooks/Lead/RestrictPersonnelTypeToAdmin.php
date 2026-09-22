<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Lead;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\PersonnelTypeRestriction;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Volunteer / Employee on Lead type is admin-only, same as Contact.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 *
 * @implements BeforeSave<\Espo\Modules\Crm\Entities\Lead>
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
