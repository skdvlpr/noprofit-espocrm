<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reporting;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Mail\EmailSender;
use Espo\Core\Mail\Exceptions\SendingError;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Entities\Attachment;
use Espo\Entities\Email;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\Tools\Export\Factory as ExportFactory;
use Espo\Tools\Export\Params as ExportParams;
use stdClass;

/**
 * Builds a reporting-entity export attachment and emails it (Task 7.3.6 / 7.4.2).
 *
 * Accepts the same payload shape as the native Export API plus email recipient params.
 */
class ReportingEmailExporter
{
    private const EMAIL_FORMAT_MAP = [
        'csv-email' => 'csv',
        'xlsx-email' => 'xlsx',
    ];

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private ExportFactory $exportFactory,
        private EmailSender $emailSender,
        private Language $language,
        private Config $config,
        private User $user,
        private ReportingProfileRegistry $profileRegistry,
        private EmailRecipientResolver $recipientResolver,
    ) {}

    /**
     * @param array<string, mixed>|stdClass $data
     * @throws BadRequest
     * @throws Forbidden
     * @throws SendingError
     */
    public function send(array|stdClass $data): void
    {
        if ($data instanceof stdClass) {
            $data = json_decode(json_encode($data), true);
        }

        $entityType = $data['entityType'] ?? null;

        if (!is_string($entityType) || $entityType === '') {
            throw new BadRequest('Entity type is required.');
        }

        if (!$this->acl->check($entityType, Table::ACTION_READ)) {
            throw new Forbidden();
        }

        $requestFormat = $data['format'] ?? 'xlsx-email';

        if (!is_string($requestFormat)) {
            throw new BadRequest('Invalid export format.');
        }

        $baseFormat = self::EMAIL_FORMAT_MAP[$requestFormat] ?? null;

        if ($baseFormat === null) {
            throw new BadRequest('Format must be csv-email or xlsx-email.');
        }

        $delivery = $this->parseEmailDelivery($data);

        if ($delivery['to'] === []) {
            throw new BadRequest('At least one recipient is required.');
        }

        $exportParams = $this->buildExportParams($entityType, $data, $baseFormat);

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

        $scopeLabel = $this->language->translateLabel($entityType, 'scopeNamesPlural');
        $subject = $scopeLabel . ' — ' . $this->language->translateLabel('reportingEmailExport', 'labels', 'Global');
        $bodyTemplate = $this->language->translateLabel('reportingEmailExportBody', 'labels', 'Global');
        $body = str_replace('{scope}', $scopeLabel, $bodyTemplate);

        if ($body === 'reportingEmailExportBody') {
            $body = $scopeLabel . ' export is attached.';
        }

        /** @var Email $email */
        $email = $this->entityManager->getNewEntity(Email::ENTITY_TYPE);

        foreach ($delivery['to'] as $address) {
            $email->addToAddress($address);
        }

        foreach ($delivery['cc'] as $address) {
            $email->addCcAddress($address);
        }

        foreach ($delivery['bcc'] as $address) {
            $email->addBccAddress($address);
        }

        $fromAddress = (string) ($this->config->get('outboundEmailFromAddress') ?: '');

        $email
            ->setSubject($subject)
            ->setBody($body)
            ->setIsHtml(false);

        if ($fromAddress !== '') {
            $email->setFromAddress($fromAddress);
        }

        $sender = $this->emailSender->create()->withAttachments([$attachment]);

        // Use Espo's system sending account (Group Email SMTP matching
        // outboundEmailFromAddress) — same path as Admin "Send Test Email".
        // Do NOT force config.php smtpServer (often Mailpit on DDEV); smokes
        // temporarily retarget the group account to Mailpit instead.
        try {
            $sender->send($email);
        } catch (SendingError $e) {
            // Keep the attachment for retry/debug; surface SMTP failure to the UI.
            throw $e;
        }

        // Soft-delete the temp export file only after a successful send.
        $this->entityManager->removeEntity($attachment);
    }

    private function buildExportParams(string $entityType, array $data, string $baseFormat): ExportParams
    {
        $params = ExportParams::fromRaw($this->buildRawExportParams($entityType, $data, $baseFormat));

        $rawParams = $data['params'] ?? [];

        if ($rawParams instanceof stdClass) {
            $rawParams = json_decode(json_encode($rawParams), true);
        }

        if (!is_array($rawParams)) {
            $rawParams = [];
        }

        foreach ($rawParams as $key => $value) {
            if (!is_string($key) || str_starts_with($key, 'email')) {
                continue;
            }

            $params = $params->withParam($key, $value);
        }

        $includeTotals = array_key_exists('includeTotals', $rawParams)
            ? (bool) $rawParams['includeTotals']
            : $this->profileRegistry->isReportingEntity($entityType);

        return $params->withParam('includeTotals', $includeTotals);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildRawExportParams(string $entityType, array $data, string $format): array
    {
        $raw = [
            'entityType' => $entityType,
            'format' => $format,
        ];

        $ids = $data['ids'] ?? null;

        if (is_array($ids) && $ids !== []) {
            $raw['ids'] = $ids;
        } else {
            $searchParams = [];

            if (!empty($data['where']) && is_array($data['where'])) {
                $searchParams['where'] = $data['where'];
            }

            if (!empty($data['searchParams']) && is_array($data['searchParams'])) {
                $searchParams = array_merge($searchParams, $data['searchParams']);
            }

            if (!empty($data['primaryFilter'])) {
                $searchParams['primaryFilter'] = $data['primaryFilter'];
            }

            if (!empty($data['boolFilterList']) && is_array($data['boolFilterList'])) {
                $searchParams['boolFilterList'] = $data['boolFilterList'];
            }

            if (!empty($data['textFilter'])) {
                $searchParams['textFilter'] = $data['textFilter'];
            }

            if (isset($data['orderBy'])) {
                $searchParams['orderBy'] = $data['orderBy'];
                $searchParams['order'] = $data['order'] ?? 'asc';
            }

            if ($searchParams !== []) {
                $raw['searchParams'] = $searchParams;
            }
        }

        if (!empty($data['attributeList']) && is_array($data['attributeList'])) {
            $raw['attributeList'] = $data['attributeList'];
        }

        if (!empty($data['fieldList']) && is_array($data['fieldList'])) {
            $raw['fieldList'] = $data['fieldList'];
        }

        return $raw;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{to: string[], cc: string[], bcc: string[]}
     */
    private function parseEmailDelivery(array $data): array
    {
        $rawParams = $this->normalizeRawParams($data['params'] ?? null);

        $to = $this->parseSemicolonEmailParam($rawParams['emailTo'] ?? null);
        $cc = $this->parseSemicolonEmailParam($rawParams['emailCc'] ?? null);
        $bcc = $this->parseSemicolonEmailParam($rawParams['emailBcc'] ?? null);

        if ($to === []) {
            $payload = $this->decodeEmailDeliveryPayload($rawParams['emailDelivery'] ?? null);
            $to = $this->resolveToAddresses($payload['to'] ?? []);
            $cc = $cc !== [] ? $cc : $this->normalizeAddressList($payload['cc'] ?? []);
            $bcc = $bcc !== [] ? $bcc : $this->normalizeAddressList($payload['bcc'] ?? []);
        }

        if ($to === [] && isset($data['emailAddressList'])) {
            $to = $this->normalizeAddressList($data['emailAddressList']);
        }

        return [
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
        ];
    }

    /**
     * @param mixed $rawParams
     * @return array<string, mixed>
     */
    private function normalizeRawParams(mixed $rawParams): array
    {
        if ($rawParams instanceof stdClass) {
            $rawParams = json_decode(json_encode($rawParams), true);
        }

        return is_array($rawParams) ? $rawParams : [];
    }

    /**
     * Espo native email-address-varchar stores addresses separated by semicolons.
     *
     * @return string[]
     */
    private function parseSemicolonEmailParam(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $parts = array_map('trim', explode(';', $raw));

        return $this->normalizeAddressList(array_filter($parts, fn ($part) => $part !== ''));
    }

    /**
     * @return array{to: array<int, array<string, mixed>>, cc: array<int, string>, bcc: array<int, string>}
     */
    private function decodeEmailDeliveryPayload(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return [
                    'to' => is_array($decoded['to'] ?? null) ? $decoded['to'] : [],
                    'cc' => is_array($decoded['cc'] ?? null) ? $decoded['cc'] : [],
                    'bcc' => is_array($decoded['bcc'] ?? null) ? $decoded['bcc'] : [],
                ];
            }
        }

        if (is_array($raw)) {
            return [
                'to' => is_array($raw['to'] ?? null) ? $raw['to'] : [],
                'cc' => is_array($raw['cc'] ?? null) ? $raw['cc'] : [],
                'bcc' => is_array($raw['bcc'] ?? null) ? $raw['bcc'] : [],
            ];
        }

        return ['to' => [], 'cc' => [], 'bcc' => []];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return string[]
     */
    private function resolveToAddresses(array $items): array
    {
        $list = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $source = $item['source'] ?? 'manual';
            $entityType = $item['entityType'] ?? null;
            $id = $item['id'] ?? null;

            if ($source === 'record' && is_string($entityType) && is_string($id) && $id !== '') {
                $list[] = $this->recipientResolver->resolvePrimaryEmail($entityType, $id);

                continue;
            }

            $email = $item['email'] ?? null;

            if (is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                $list[] = trim($email);
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * @param mixed $raw
     * @return string[]
     */
    private function normalizeAddressList(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[,;\s]+/', $raw) ?: [];
        }

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
