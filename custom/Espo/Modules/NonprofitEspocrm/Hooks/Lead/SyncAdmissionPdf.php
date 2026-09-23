<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Lead;

use Espo\Core\Hook\Hook\LateAfterSave;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\AdmissionPdf;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveContext;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Build or release the Associato admission PDF after the Lead is stored.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
 *
 * @implements LateAfterSave<\Espo\Modules\Crm\Entities\Lead>
 */
class SyncAdmissionPdf implements LateAfterSave
{
    public static int $order = 40;

    public function __construct(
        private AdmissionPdf $admissionPdf,
    ) {}

    public function lateAfterSave(Entity $entity, SaveOptions $options): void
    {
        $context = $options->get(SaveContext::NAME);
        $isNew = $context instanceof SaveContext && $context->isNew();

        $this->admissionPdf->sync($entity, $options, $isNew);
    }
}
