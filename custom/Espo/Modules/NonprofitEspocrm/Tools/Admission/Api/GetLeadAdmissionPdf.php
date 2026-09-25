<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Admission\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\AdmissionPdf;
use Espo\ORM\EntityManager;
use Espo\Core\Acl;

/**
 * Inline admission PDF for the Lead preview panel. Built on demand.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/api.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 */
class GetLeadAdmissionPdf implements Action
{
    public function __construct(
        private AdmissionPdf $admissionPdf,
        private EntityManager $entityManager,
        private Acl $acl,
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if (!is_string($id) || $id === '') {
            throw new BadRequest();
        }

        $lead = $this->entityManager->getEntityById('Lead', $id);

        if ($lead === null) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityRead($lead)) {
            throw new Forbidden();
        }

        if (!AdmissionPdf::isAssociato($lead->get('contactType'))) {
            throw new NotFound();
        }

        $pdf = $this->admissionPdf->render($lead);
        $filename = AdmissionPdf::downloadName($lead);

        return ResponseComposer::empty()
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader(
                'Content-Disposition',
                'inline; filename="' . str_replace('"', '', $filename) . '"'
            )
            ->writeBody($pdf);
    }
}
