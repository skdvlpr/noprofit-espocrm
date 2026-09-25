<?php

namespace Espo\Modules\NonprofitEspocrm\Core\Rebuild;

use Espo\Core\Rebuild\RebuildAction;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\AdmissionPdf;
use Espo\ORM\EntityManager;

/**
 * Delete leftover stored admission PDFs on Lead and Contact.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-rebuild.md
 *
 * @noinspection PhpUnused
 */
class StripStoredAdmissionPdfs implements RebuildAction
{
    public function __construct(
        private EntityManager $entityManager,
        private AdmissionPdf $admissionPdf,
    ) {}

    public function process(): void
    {
        foreach (['Lead', 'Contact'] as $entityType) {
            $records = $this->entityManager
                ->getRDBRepository($entityType)
                ->where([
                    'admissionFormId!=' => null,
                ])
                ->find();

            foreach ($records as $record) {
                $this->admissionPdf->stripStoredForm($record);
            }
        }
    }
}
