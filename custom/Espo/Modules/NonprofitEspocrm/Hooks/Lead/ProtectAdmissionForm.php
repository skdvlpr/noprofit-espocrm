<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Lead;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\AdmissionFormGuard;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Keep Lead.admissionForm on the generated file.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 *
 * @implements BeforeSave<\Espo\Modules\Crm\Entities\Lead>
 */
class ProtectAdmissionForm implements BeforeSave
{
    public static int $order = 3;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        AdmissionFormGuard::revertClientChange($entity);
    }
}
