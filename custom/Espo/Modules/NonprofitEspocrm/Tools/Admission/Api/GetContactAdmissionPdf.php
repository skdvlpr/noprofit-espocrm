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
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Entities\Attachment;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\AdmissionPdf;
use Espo\ORM\EntityManager;

/**
 * Inline admission PDF stored on the Contact. The Lead preview still renders
 * from the lead; this route reads the file convert copied onto the contact.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/api.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 */
class GetContactAdmissionPdf implements Action
{
    public function __construct(
        private EntityManager $entityManager,
        private FileStorageManager $fileStorageManager,
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

        $fileId = $contact->get('admissionFormId');

        if (!is_string($fileId) || $fileId === '') {
            throw new NotFound();
        }

        $attachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $fileId);

        if (!$attachment instanceof Attachment) {
            throw new NotFound();
        }

        $filename = $attachment->getName() ?: AdmissionPdf::downloadName($contact);

        return ResponseComposer::empty()
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader(
                'Content-Disposition',
                'inline; filename="' . str_replace('"', '', $filename) . '"'
            )
            ->writeBody($this->fileStorageManager->getContents($attachment));
    }
}
