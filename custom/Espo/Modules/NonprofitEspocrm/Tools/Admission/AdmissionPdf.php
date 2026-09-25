<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Admission;

use Espo\Core\InjectableFactory;
use Espo\Entities\Attachment;
use Espo\Entities\Template;
use Espo\Modules\NonprofitEspocrm\Tools\ContactTypeSet;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\Tools\Pdf\Params;
use Espo\Tools\Pdf\Service as PdfService;
use RuntimeException;

/**
 * Stream the Associato admission form at view/download time. Do not
 * store an Attachment. Formula ext\pdf\generate is rejected because it
 * always returns an attachment id (writes to disk).
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/formula/ext.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 */
class AdmissionPdf
{
    public const TEMPLATE_NAME = 'Domanda di ammissione a socio';

    public const TEMPLATE_NAME_CONTACT = 'Domanda di ammissione a socio (Contact)';

    public function __construct(
        private EntityManager $entityManager,
        private InjectableFactory $injectableFactory,
    ) {}

    public function sync(Entity $lead, SaveOptions $options, bool $isNew): void
    {        if ($options->get(AdmissionPdfPlan::SKIP_OPTION)) {
            return;
        }

        $leadAssociato = self::isAssociato($lead->get('contactType'));
        $converted = $lead->get('status') === 'Converted';
        $leadHasFile = AdmissionPdfPlan::hasStoredFile($lead->get('admissionFormId'));

        if (AdmissionPdfPlan::shouldDrop($leadAssociato, $converted, $leadHasFile)) {
            $this->clearFile($lead, true);

            return;
        }

        if (!$converted || !$leadAssociato) {
            return;
        }

        $contactId = $lead->get('createdContactId');

        if (!is_string($contactId) || $contactId === '') {
            return;
        }

        $contact = $this->entityManager->getEntityById('Contact', $contactId);

        if ($contact === null || !self::isAssociato($contact->get('contactType'))) {
            return;
        }

        $contactHasFile = AdmissionPdfPlan::hasStoredFile($contact->get('admissionFormId'));

        if (!AdmissionPdfPlan::shouldClearOnConvert(
            true,
            true,
            true,
            $leadHasFile,
            $contactHasFile,
        )) {
            return;
        }

        $this->clearFile($lead, true);
        $this->clearFile($contact, true);
    }

    public function render(Entity $record): string
    {
        $entityType = $record->getEntityType();
        $template = $this->ensureTemplate($entityType);
        $service = $this->injectableFactory->create(PdfService::class);
        $result = $service->generate(
            entityType: $entityType,
            id: (string) $record->getId(),
            templateId: (string) $template->getId(),
            params: Params::create()->withAcl(false),
        );

        return $result->getString();
    }

    public function stripStoredForm(Entity $entity): void
    {
        if (!AdmissionPdfPlan::hasStoredFile($entity->get('admissionFormId'))) {
            return;
        }

        $this->clearFile($entity, true);
    }

    private function clearFile(Entity $entity, bool $deleteAttachment): void
    {
        $fileId = $entity->get('admissionFormId');
        $entity->set('admissionFormId', null);
        $entity->set('admissionFormName', null);
        $this->entityManager->saveEntity($entity, [
            SaveOption::SKIP_ALL => true,
            AdmissionPdfPlan::SKIP_OPTION => true,
        ]);

        if ($deleteAttachment && AdmissionPdfPlan::hasStoredFile($fileId)) {
            $this->removeAttachment((string) $fileId);
        }
    }

    private function removeAttachment(string $id): void
    {
        $attachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $id);

        if ($attachment === null) {
            return;
        }

        $this->entityManager->removeEntity($attachment, [
            SaveOption::SKIP_ALL => true,
        ]);
    }

    public function provisionTemplate(): void
    {
        $this->ensureTemplate('Lead');
        $this->ensureTemplate('Contact');
    }

    private function ensureTemplate(string $entityType = 'Lead'): Template
    {
        if ($entityType !== 'Lead' && $entityType !== 'Contact') {
            throw new RuntimeException("Admission PDF is only for Lead or Contact.");
        }

        $name = $entityType === 'Contact' ? self::TEMPLATE_NAME_CONTACT : self::TEMPLATE_NAME;
        $body = self::templateBody();
        $existing = $this->entityManager
            ->getRDBRepository(Template::ENTITY_TYPE)
            ->where([
                'name' => $name,
                'entityType' => $entityType,
            ])
            ->findOne();

        if (!$existing instanceof Template) {
            $created = $this->entityManager->getNewEntity(Template::ENTITY_TYPE);

            if (!$created instanceof Template) {
                throw new RuntimeException("Could not create the admission PDF template.");
            }

            $created->set('name', $name);
            $created->set('entityType', $entityType);
            $existing = $created;
        }

        if (
            $existing->isNew()
            || $existing->get('body') !== $body
            || $existing->get('status') !== 'Active'
        ) {
            $existing->set('status', 'Active');
            $existing->set('body', $body);
            $existing->set('printHeader', false);
            $existing->set('printFooter', false);
            $this->entityManager->saveEntity($existing, [
                SaveOption::SKIP_ALL => true,
            ]);
        }

        return $existing;
    }

    public static function downloadName(Entity $lead): string
    {
        $first = self::namePart($lead->get('firstName'));
        $last = self::namePart($lead->get('lastName'));
        $birth = '';
        $rawBirth = $lead->get('birthDate');

        if (is_string($rawBirth) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $rawBirth, $match) === 1) {
            $birth = $match[3] . $match[2] . $match[1];
        }

        $parts = array_values(array_filter(
            ['domanda ammissione', $first, $last, $birth],
            static fn (string $part): bool => $part !== ''
        ));

        return implode(' ', $parts) . '.pdf';
    }

    private static function namePart(mixed $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }

    public static function isAssociato(mixed $contactType): bool
    {
        return in_array(ContactTypeSet::OPTION_MEMBER, ContactTypeSet::normalize($contactType), true);
    }

    public static function templateBody(): string
    {
        $path = dirname(__DIR__, 2) . '/Resources/templates/admission-socio.html';
        $body = file_get_contents($path);

        if ($body === false) {
            throw new RuntimeException("Admission PDF template file is missing.");
        }

        return $body;
    }
}
