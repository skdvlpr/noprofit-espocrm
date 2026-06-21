<?php

namespace Espo\Modules\SafehouseCrm\Tools\Reporting;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Mail\EmailSender;
use Espo\Core\Mail\Exceptions\SendingError;
use Espo\Core\Mail\SmtpParams;
use Espo\Core\Select\SearchParams;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Entities\Attachment;
use Espo\Entities\Email;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\Tools\Export\Factory as ExportFactory;
use Espo\Tools\Export\Params as ExportParams;

/**
 * Builds a MealCount export attachment and emails it to selected recipients (Task 7.3.6).
 */
class MealCountEmailExporter
{
    private const ENTITY_TYPE = 'MealCount';

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private ExportFactory $exportFactory,
        private EmailSender $emailSender,
        private Language $language,
        private Config $config,
        private User $user,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @throws BadRequest
     * @throws Forbidden
     * @throws SendingError
     */
    public function send(array $data): void
    {
        if (!$this->acl->check(self::ENTITY_TYPE, Table::ACTION_READ)) {
            throw new Forbidden();
        }

        $format = $data['format'] ?? 'csv';

        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new BadRequest('Invalid export format.');
        }

        $recipients = $this->normalizeRecipients($data['emailAddressList'] ?? []);

        if ($recipients === []) {
            throw new BadRequest('At least one recipient is required.');
        }

        $searchParams = SearchParams::fromRaw($data);
        $includeTotals = array_key_exists('includeTotals', $data)
            ? (bool) $data['includeTotals']
            : true;

        $exportParams = ExportParams::create(self::ENTITY_TYPE)
            ->withFormat($format)
            ->withSearchParams($searchParams)
            ->withParam('includeTotals', $includeTotals);

        if (!empty($data['fieldList']) && is_array($data['fieldList'])) {
            $exportParams = $exportParams->withFieldList($data['fieldList']);
        }

        $export = $this->exportFactory->createForUser($this->user);
        $result = $export->setParams($exportParams)->run();

        /** @var ?Attachment $attachment */
        $attachment = $this->entityManager->getEntityById(
            Attachment::ENTITY_TYPE,
            $result->getAttachmentId()
        );

        if ($attachment === null) {
            throw new BadRequest('Export attachment was not created.');
        }

        $scopeLabel = $this->language->translateLabel(self::ENTITY_TYPE, 'scopeNamesPlural');
        $subject = $scopeLabel . ' — ' . $this->language->translateLabel('reportingEmailExport', 'labels', 'Global');
        $body = $this->language->translateLabel('reportingEmailExportBody', 'labels', 'Global');

        if ($body === 'reportingEmailExportBody') {
            $body = 'Meal Count export is attached.';
        }

        /** @var Email $email */
        $email = $this->entityManager->getNewEntity(Email::ENTITY_TYPE);

        foreach ($recipients as $address) {
            $email->addToAddress($address);
        }

        $email
            ->setSubject($subject)
            ->setBody($body)
            ->setIsHtml(false);

        $sender = $this->emailSender->create()->withAttachments([$attachment]);

        $smtpParams = $this->resolveSystemSmtpParams();

        if ($smtpParams !== null) {
            $sender = $sender->withSmtpParams($smtpParams);
        }

        $sender->send($email);

        $this->entityManager->removeEntity($attachment);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSystemSmtpParams(): ?array
    {
        $server = $this->config->get('smtpServer');

        if (!$server) {
            return null;
        }

        return SmtpParams::create($server, (int) $this->config->get('smtpPort'))
            ->withAuth((bool) $this->config->get('smtpAuth'))
            ->withUsername($this->config->get('smtpUsername'))
            ->withPassword($this->config->get('smtpPassword'))
            ->withSecurity($this->config->get('smtpSecurity') ?: null)
            ->withFromAddress($this->config->get('outboundEmailFromAddress'))
            ->withFromName($this->config->get('outboundEmailFromName'))
            ->toArray();
    }

    /**
     * @param mixed $raw
     * @return string[]
     */
    private function normalizeRecipients(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $list = [];

        foreach ($raw as $item) {
            if (!is_string($item)) {
                continue;
            }

            $address = trim($item);

            if ($address === '' || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $list[] = $address;
        }

        return array_values(array_unique($list));
    }
}
