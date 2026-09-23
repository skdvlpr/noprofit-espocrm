<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Admission;

use Espo\Core\Field\LinkParent;
use Espo\Core\FileStorage\Manager as FileStorageManager;
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
 * One admission PDF on an Associato Lead, then release it onto the Contact.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/formula/ext.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
 */
class AdmissionPdf
{
    public const TEMPLATE_NAME = 'Domanda di ammissione a socio';

    /** @var list<string> */
    private const PRINTED_FIELDS = [
        'firstName',
        'lastName',
        'emailAddress',
        'phoneNumber',
        'addressStreet',
        'addressCity',
        'addressState',
        'addressPostalCode',
        'taxCode',
        'birthDate',
        'birthPlace',
        'birthProvince',
        'description',
        'contactType',
        'admissionBoardDate',
        'admissionOutcome',
        'memberBookNumber',
        'admissionFeePaid',
        'admissionReceiptNumber',
        'newsletterConsent',
    ];

    public function __construct(
        private EntityManager $entityManager,
        private InjectableFactory $injectableFactory,
        private FileStorageManager $fileStorageManager,
    ) {}

    public function sync(Entity $lead, SaveOptions $options, bool $isNew): void
    {
        if ($options->get(AdmissionPdfPlan::SKIP_OPTION)) {
            return;
        }

        $leadAssociato = self::isAssociato($lead->get('contactType'));
        $converted = $lead->get('status') === 'Converted';
        $hasFile = is_string($lead->get('admissionFormId')) && $lead->get('admissionFormId') !== '';

        if (AdmissionPdfPlan::shouldDrop($leadAssociato, $converted, $hasFile)) {
            $this->clearLeadFile($lead, true);

            return;
        }

        $printedChanged = false;

        foreach (self::PRINTED_FIELDS as $field) {
            if ($lead->isAttributeChanged($field)) {
                $printedChanged = true;

                break;
            }
        }

        if (AdmissionPdfPlan::shouldGenerate($leadAssociato, $isNew, $printedChanged, $hasFile)) {
            $this->generate($lead);
        }

        if ($converted && $leadAssociato) {
            $fresh = $this->entityManager->getEntityById('Lead', (string) $lead->getId()) ?? $lead;
            $this->mirrorToContact($fresh);
        }
    }

    private function mirrorToContact(Entity $lead): void
    {
        $contactId = $lead->get('createdContactId');
        $leadFileId = $lead->get('admissionFormId');

        if (!is_string($contactId) || $contactId === '' || !is_string($leadFileId) || $leadFileId === '') {
            return;
        }

        $contact = $this->entityManager->getEntityById('Contact', $contactId);

        if ($contact === null || !self::isAssociato($contact->get('contactType'))) {
            return;
        }

        if ($contact->get('admissionFormId') === $leadFileId) {
            return;
        }

        $contact->set('admissionFormId', $leadFileId);
        $contact->set('admissionFormName', $lead->get('admissionFormName'));
        $this->entityManager->saveEntity($contact, [
            SaveOption::SKIP_ALL => true,
        ]);
    }

    public function render(Entity $lead): string
    {
        $template = $this->ensureTemplate();
        $service = $this->injectableFactory->create(PdfService::class);
        $result = $service->generate(
            entityType: 'Lead',
            id: (string) $lead->getId(),
            templateId: (string) $template->getId(),
            params: Params::create()->withAcl(false),
        );

        return $result->getString();
    }

    private function generate(Entity $lead): void
    {
        $template = $this->ensureTemplate();
        $leadId = (string) $lead->getId();

        $service = $this->injectableFactory->create(PdfService::class);
        $result = $service->generate(
            entityType: 'Lead',
            id: $leadId,
            templateId: (string) $template->getId(),
            params: Params::create()->withAcl(false),
        );

        $previousId = $lead->get('admissionFormId');
        $fileName = self::downloadName($lead);

        $attachment = $this->entityManager->getRDBRepositoryByClass(Attachment::class)->getNew();
        $attachment
            ->setName($fileName)
            ->setType('application/pdf')
            ->setSize($result->getLength())
            ->setRelated(LinkParent::create('Lead', $leadId))
            ->setRole(Attachment::ROLE_ATTACHMENT);

        $this->entityManager->saveEntity($attachment);
        $this->fileStorageManager->putStream($attachment, $result->getStream());

        $lead->set('admissionFormId', $attachment->getId());
        $lead->set('admissionFormName', $fileName);
        $this->entityManager->saveEntity($lead, [
            SaveOption::SKIP_ALL => true,
            AdmissionPdfPlan::SKIP_OPTION => true,
        ]);

        if (is_string($previousId) && $previousId !== '' && $previousId !== $attachment->getId()) {
            $this->removeAttachment($previousId);
        }
    }

    private function clearLeadFile(Entity $lead, bool $deleteAttachment): void
    {
        $fileId = $lead->get('admissionFormId');
        $lead->set('admissionFormId', null);
        $lead->set('admissionFormName', null);
        $this->entityManager->saveEntity($lead, [
            SaveOption::SKIP_ALL => true,
            AdmissionPdfPlan::SKIP_OPTION => true,
        ]);

        if ($deleteAttachment && is_string($fileId) && $fileId !== '') {
            $this->removeAttachment($fileId);
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

    private function ensureTemplate(): Template
    {
        $body = self::templateBody();
        $existing = $this->entityManager
            ->getRDBRepository(Template::ENTITY_TYPE)
            ->where([
                'name' => self::TEMPLATE_NAME,
                'entityType' => 'Lead',
            ])
            ->findOne();

        if (!$existing instanceof Template) {
            $created = $this->entityManager->getNewEntity(Template::ENTITY_TYPE);

            if (!$created instanceof Template) {
                throw new RuntimeException("Could not create the admission PDF template.");
            }

            $created->set('name', self::TEMPLATE_NAME);
            $created->set('entityType', 'Lead');
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
