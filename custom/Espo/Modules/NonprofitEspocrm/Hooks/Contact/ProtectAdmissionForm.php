<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Contact;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\AdmissionFormGuard;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Keep Contact.admissionForm on the file released from the Lead.
 * Generator saves use SKIP_ALL, which does not run this hook.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 *
 * @implements BeforeSave<\Espo\Modules\Crm\Entities\Contact>
 */
class ProtectAdmissionForm implements BeforeSave
{
    public static int $order = 3;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        AdmissionFormGuard::revertClientChange($entity);
    }
}
