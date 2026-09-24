<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Admission;

use Espo\ORM\Entity;

/**
 * The admission PDF file is generated. A client-supplied id is reverted
 * before the core file saver can reparent that attachment and delete the
 * previous one.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 */
class AdmissionFormGuard
{
    public static function revertClientChange(Entity $entity): void
    {
        foreach (['admissionFormId', 'admissionFormName'] as $attribute) {
            if (!$entity->isAttributeChanged($attribute)) {
                continue;
            }

            $entity->set($attribute, $entity->getFetched($attribute));
        }
    }
}
