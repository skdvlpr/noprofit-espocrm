<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Services;

use Espo\Core\Field\LinkParent;
use Espo\Core\Htmlizer\HtmlizerFactory;
use Espo\Core\Mail\EmailSender;
use Espo\Core\Name\Field;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\SystemUser;
use Espo\Core\WebSocket\Submission as WebSocketSubmission;
use Espo\Entities\Email;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Repositories\EmailAddress as EmailAddressRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Executes workflow actions under the system user (ORM path, skipWorkflowEngine).
 */
class ActionExecutor
{
    public function __construct(
        private EntityManager $entityManager,
        private EmailSender $emailSender,
        private HtmlizerFactory $htmlizerFactory,
        private TemplateRenderer $templateRenderer,
        private ValueResolver $valueResolver,
        private SystemUser $systemUser,
        private Config $config,
        private WebSocketSubmission $webSocketSubmission,
        private LoggerInterface $log,
    ) {}

    /**
     * @param array<string, mixed> $action
     * @return array{type: string, ok: bool, detail?: string}
     */
    public function execute(array $action, Entity $entity): array
    {
        $type = (string) ($action['type'] ?? '');

        return match ($type) {
            'SendEmail' => $this->sendEmail($action, $entity),
            'CreateNotification' => $this->createNotification($action, $entity),
            'UpdateFields' => $this->updateFields($action, $entity),
            'CreateRecord' => $this->createRecord($action, $entity),
            default => [
                'type' => $type !== '' ? $type : 'unknown',
                'ok' => false,
                'detail' => 'Unsupported action type',
            ],
        };
    }

    /**
     * @param array<string, mixed> $action
     * @return array{type: string, ok: bool, detail?: string}
     */
    private function updateFields(array $action, Entity $entity): array
    {
        $assignments = $action['assignments'] ?? [];

        if ($assignments instanceof \stdClass) {
            $assignments = json_decode(json_encode($assignments), true);
        }

        if (!is_array($assignments) || $assignments === []) {
            return ['type' => 'UpdateFields', 'ok' => false, 'detail' => 'No assignments'];
        }

        $resolved = $this->valueResolver->resolveAssignments($assignments, $entity);

        if ($resolved === []) {
            return ['type' => 'UpdateFields', 'ok' => false, 'detail' => 'Empty resolved attributes'];
        }

        try {
            $this->applyAttributes($entity, $resolved);

            return [
                'type' => 'UpdateFields',
                'ok' => true,
                'detail' => implode(',', array_keys($resolved)),
            ];
        } catch (Throwable $e) {
            $this->log->error(
                'WorkflowEngine UpdateFields failed: {message}',
                ['message' => $e->getMessage()]
            );

            return ['type' => 'UpdateFields', 'ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $action
     * @return array{type: string, ok: bool, detail?: string}
     */
    private function createRecord(array $action, Entity $entity): array
    {
        $entityType = trim((string) ($action['entityType'] ?? $action['createEntityType'] ?? ''));
        $assignments = $action['assignments'] ?? [];

        if ($assignments instanceof \stdClass) {
            $assignments = json_decode(json_encode($assignments), true);
        }

        if ($entityType === '') {
            return ['type' => 'CreateRecord', 'ok' => false, 'detail' => 'entityType required'];
        }

        if (!is_array($assignments)) {
            $assignments = [];
        }

        $attributes = $this->valueResolver->resolveAssignments($assignments, $entity);

        try {
            /** @var Entity $created */
            $created = $this->entityManager->getNewEntity($entityType);
            $created->set($attributes);

            $this->entityManager->saveEntity($created, $this->workflowSaveOptions(true));

            return [
                'type' => 'CreateRecord',
                'ok' => true,
                'detail' => $entityType . '#' . $created->getId(),
            ];
        } catch (Throwable $e) {
            $this->log->error(
                'WorkflowEngine CreateRecord failed: {message}',
                ['message' => $e->getMessage()]
            );

            return ['type' => 'CreateRecord', 'ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function applyAttributes(Entity $entity, array $attributes): void
    {
        $own = [];
        /** @var array<string, array<string, mixed>> $relatedByLink */
        $relatedByLink = [];

        foreach ($attributes as $field => $value) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            if (!str_contains($field, '.')) {
                $own[$field] = $value;

                continue;
            }

            [$link, $attribute] = explode('.', $field, 2);

            if ($link === '' || $attribute === '') {
                continue;
            }

            $relatedByLink[$link][$attribute] = $value;
        }

        if ($own !== []) {
            $entity->set($own);
            $this->entityManager->saveEntity($entity, $this->workflowSaveOptions(false));
        }

        if ($relatedByLink === [] || !$entity instanceof CoreEntity) {
            return;
        }

        $repository = $this->entityManager->getRDBRepository($entity->getEntityType());

        foreach ($relatedByLink as $link => $relatedAttributes) {
            $related = $repository->getRelation($entity, $link)->findOne();

            if (!$related instanceof Entity) {
                throw new \RuntimeException("Related record not found for link {$link}");
            }

            $related->set($relatedAttributes);
            $this->entityManager->saveEntity($related, $this->workflowSaveOptions(false));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowSaveOptions(bool $isCreate): array
    {
        $systemUserId = $this->systemUser->getId();

        $options = [
            SaveOption::MODIFIED_BY_ID => $systemUserId,
            'skipWorkflowEngine' => true,
        ];

        if ($isCreate) {
            $options[SaveOption::CREATED_BY_ID] = $systemUserId;
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $action
     * @return array{type: string, ok: bool, detail?: string}
     */
    private function sendEmail(array $action, Entity $entity): array
    {
        $from = trim((string) ($this->config->get('outboundEmailFromAddress') ?? ''));

        if ($from === '') {
            return [
                'type' => 'SendEmail',
                'ok' => false,
                'detail' => 'System outbound email address is not configured',
            ];
        }

        $toList = $this->resolveRecipientEmailList($action, $entity);

        if ($toList === []) {
            return [
                'type' => 'SendEmail',
                'ok' => false,
                'detail' => 'No recipient email (user has no address #' .
                    ((int) ($action['emailAddressIndex'] ?? 1)) .
                    '; set Additional To or add an email on the user)',
            ];
        }

        [$subjectTpl, $bodyTpl, $isHtml] = $this->resolveEmailTemplates($action, $entity);

        if ($subjectTpl === '' && $bodyTpl === '') {
            return ['type' => 'SendEmail', 'ok' => false, 'detail' => 'Empty email template'];
        }

        $htmlizer = $this->htmlizerFactory->create(true);
        $subject = $this->templateRenderer->render($entity, $subjectTpl, $htmlizer);
        $body = $this->templateRenderer->render($entity, $bodyTpl, $htmlizer);

        $cc = $this->normalizeAddressList((string) ($action['cc'] ?? ''));
        $bcc = $this->normalizeAddressList((string) ($action['bcc'] ?? ''));

        /** @var Email $email */
        $email = $this->entityManager->getRDBRepositoryByClass(Email::class)->getNew();
        $email->set([
            'subject' => $subject,
            'body' => $body,
            'isHtml' => $isHtml,
            'from' => $from,
            'to' => implode('; ', $toList),
            'cc' => $cc !== [] ? implode('; ', $cc) : null,
            'bcc' => $bcc !== [] ? implode('; ', $bcc) : null,
            'isSystem' => true,
            'parentId' => $entity->getId(),
            'parentType' => $entity->getEntityType(),
        ]);

        try {
            $this->emailSender->send($email);

            return ['type' => 'SendEmail', 'ok' => true, 'detail' => implode(',', $toList)];
        } catch (Throwable $e) {
            $this->log->error(
                'WorkflowEngine SendEmail failed: {message}',
                ['message' => $e->getMessage()]
            );

            return ['type' => 'SendEmail', 'ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $action
     * @return array{type: string, ok: bool, detail?: string}
     */
    private function createNotification(array $action, Entity $entity): array
    {
        $userId = $this->resolveRecipientUserId($action, $entity);

        if ($userId === null) {
            return ['type' => 'CreateNotification', 'ok' => false, 'detail' => 'No recipient user'];
        }

        $messageTpl = (string) ($action['message'] ?? '');
        $htmlizer = $this->htmlizerFactory->create(true);
        $message = $this->templateRenderer->render($entity, $messageTpl, $htmlizer);

        if ($message === '') {
            $message = sprintf(
                'Workflow notification for %s #%s',
                $entity->getEntityType(),
                $entity->getId()
            );
        }

        $relatedName = null;

        if ($entity->hasAttribute(Field::NAME) && is_string($entity->get(Field::NAME))) {
            $relatedName = $entity->get(Field::NAME);
        }

        $notification = $this->entityManager->getRDBRepositoryByClass(Notification::class)->getNew();
        $notification
            ->setType(Notification::TYPE_MESSAGE)
            ->setUserId($userId)
            ->setMessage($message)
            ->setRelated(LinkParent::create($entity->getEntityType(), $entity->getId()))
            ->setData([
                'workflowEngine' => true,
                'relatedName' => $relatedName,
            ]);

        $this->entityManager->saveEntity($notification);

        // Native popup channel (Meeting reminders use the same pattern).
        // No-op when WebSocket is disabled; client still polls PopupNotification/grouped.
        $this->webSocketSubmission->submit(
            'popupNotifications.workflowMessage',
            $userId,
            [
                'list' => [
                    [
                        'id' => $notification->getId(),
                        'data' => [
                            'message' => $message,
                            'relatedType' => $entity->getEntityType(),
                            'relatedId' => $entity->getId(),
                            'relatedName' => $relatedName,
                        ],
                    ],
                ],
            ]
        );

        return ['type' => 'CreateNotification', 'ok' => true, 'detail' => $userId];
    }

    /**
     * Prefer EmailTemplate record; fall back to inline subject/body.
     *
     * @param array<string, mixed> $action
     * @return array{0: string, 1: string, 2: bool}
     */
    private function resolveEmailTemplates(array $action, Entity $entity): array
    {
        $subjectTpl = (string) ($action['subject'] ?? '');
        $bodyTpl = (string) ($action['body'] ?? '');
        $isHtml = (bool) ($action['isHtml'] ?? true);

        // Prefer editor content saved on the action (template loaded into modal).
        if ($subjectTpl !== '' || $bodyTpl !== '') {
            $subjectTpl = $this->templateRenderer->normalizeEmailTemplatePlaceholders(
                $entity,
                $subjectTpl
            );
            $bodyTpl = $this->templateRenderer->normalizeEmailTemplatePlaceholders(
                $entity,
                $bodyTpl
            );

            return [$subjectTpl, $bodyTpl, $isHtml];
        }

        $templateId = $action['emailTemplateId'] ?? null;

        if (!is_string($templateId) || $templateId === '') {
            return ['', '', $isHtml];
        }

        $template = $this->entityManager->getEntityById('EmailTemplate', $templateId);

        if (!$template) {
            $this->log->warning(
                'WorkflowEngine SendEmail: EmailTemplate {id} not found',
                ['id' => $templateId]
            );

            return ['', '', $isHtml];
        }

        $subjectTpl = (string) ($template->get('subject') ?? '');
        $bodyTpl = (string) ($template->get('body') ?? '');
        $isHtml = (bool) ($template->get('isHtml') ?? true);

        $subjectTpl = $this->templateRenderer->normalizeEmailTemplatePlaceholders(
            $entity,
            $subjectTpl
        );
        $bodyTpl = $this->templateRenderer->normalizeEmailTemplatePlaceholders(
            $entity,
            $bodyTpl
        );

        return [$subjectTpl, $bodyTpl, $isHtml];
    }

    /**
     * Primary recipient(s) + additional To addresses.
     *
     * @param array<string, mixed> $action
     * @return list<string>
     */
    private function resolveRecipientEmailList(array $action, Entity $entity): array
    {
        $list = [];

        $primary = $this->resolvePrimaryRecipientEmail($action, $entity);

        if ($primary !== null) {
            $list[] = $primary;
        }

        // Legacy field name "email" treated as additional To.
        foreach (['additionalTo', 'email'] as $key) {
            if (!empty($action[$key]) && is_string($action[$key])) {
                $list = array_merge($list, $this->normalizeAddressList($action[$key]));
            }
        }

        $unique = [];

        foreach ($list as $address) {
            $normalized = strtolower($address);

            if (!isset($unique[$normalized])) {
                $unique[$normalized] = $address;
            }
        }

        return array_values($unique);
    }

    /**
     * @param array<string, mixed> $action
     */
    private function resolvePrimaryRecipientEmail(array $action, Entity $entity): ?string
    {
        $to = (string) ($action['to'] ?? 'assignedUser');

        if ($to === 'entityEmail') {
            $email = $entity->get('emailAddress');

            return is_string($email) && $email !== '' ? trim($email) : null;
        }

        $userId = $this->resolveRecipientUserId($action, $entity);

        if ($userId === null) {
            return null;
        }

        /** @var ?User $user */
        $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

        if (!$user) {
            return null;
        }

        $index = (int) ($action['emailAddressIndex'] ?? 1);

        if ($index < 1) {
            $index = 1;
        }

        if ($index > 3) {
            $index = 3;
        }

        return $this->getUserEmailByIndex($user, $index);
    }

    private function getUserEmailByIndex(User $user, int $index): ?string
    {
        $addresses = $this->getUserEmailAddressList($user);

        if ($addresses === []) {
            return null;
        }

        return $addresses[$index - 1] ?? null;
    }

    /**
     * @return list<string>
     */
    private function getUserEmailAddressList(User $user): array
    {
        /** @var EmailAddressRepository $repo */
        $repo = $this->entityManager->getRepository('EmailAddress');
        $data = $repo->getEmailAddressData($user);
        $list = [];

        if (is_array($data)) {
            foreach ($data as $item) {
                $address = null;

                if (is_object($item) && isset($item->emailAddress)) {
                    $address = (string) $item->emailAddress;
                }
                else if (is_array($item) && isset($item['emailAddress'])) {
                    $address = (string) $item['emailAddress'];
                }

                $address = trim((string) $address);

                if ($address !== '') {
                    $list[] = $address;
                }
            }
        }

        if ($list === []) {
            $primary = trim((string) ($user->get('emailAddress') ?? ''));

            if ($primary !== '') {
                $list[] = $primary;
            }
        }

        return array_values(array_slice($list, 0, 3));
    }

    /**
     * @return list<string>
     */
    private function normalizeAddressList(string $raw): array
    {
        $parts = preg_split('/[;,]+/', $raw) ?: [];
        $out = [];

        foreach ($parts as $part) {
            $email = trim($part);

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $action
     */
    private function resolveRecipientUserId(array $action, Entity $entity): ?string
    {
        $to = (string) ($action['to'] ?? 'assignedUser');

        if ($to === 'assignedUser') {
            $id = $entity->get('assignedUserId');

            return is_string($id) && $id !== '' ? $id : null;
        }

        if ($to === 'createdBy') {
            $id = $entity->get('createdById');

            return is_string($id) && $id !== '' ? $id : null;
        }

        if ($to === 'userId' || str_starts_with($to, 'user:')) {
            if (str_starts_with($to, 'user:')) {
                $id = substr($to, 5);

                return $id !== '' ? $id : null;
            }

            $id = $action['userId'] ?? null;

            return is_string($id) && $id !== '' ? $id : null;
        }

        if ($entity->hasAttribute($to)) {
            $id = $entity->get($to);

            return is_string($id) && $id !== '' ? $id : null;
        }

        return null;
    }
}
