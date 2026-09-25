<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Admission\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\AdmissionPdf;
use Espo\ORM\EntityManager;

/**
 * Inline admission PDF for the Contact preview. Built on demand from
 * the contact fields. MUST NOT read or write admissionForm.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/api.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 */
class GetContactAdmissionPdf implements Action
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

        $contact = $this->entityManager->getEntityById('Contact', $id);

        if ($contact === null) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityRead($contact)) {
            throw new Forbidden();
        }

        if (!AdmissionPdf::isAssociato($contact->get('contactType'))) {
            throw new NotFound();
        }

        $pdf = $this->admissionPdf->render($contact);
        $filename = AdmissionPdf::downloadName($contact);

        return ResponseComposer::empty()
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader(
                'Content-Disposition',
                'inline; filename="' . str_replace('"', '', $filename) . '"'
            )
            ->writeBody($pdf);
    }
}
