<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Services;

use Espo\Core\Field\LinkParent;
use Espo\Core\Htmlizer\HtmlizerFactory;
use Espo\Core\Mail\EmailSender;
use Espo\Entities\Email;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Executes validated W2 actions: SendEmail, CreateNotification.
 */
class ActionExecutor
{
    public function __construct(
        private EntityManager $entityManager,
        private EmailSender $emailSender,
        private HtmlizerFactory $htmlizerFactory,
        private TemplateRenderer $templateRenderer,
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
            default => [
                'type' => $type !== '' ? $type : 'unknown',
                'ok' => false,
                'detail' => 'Unsupported action type in W2',
            ],
        };
    }

    /**
     * @param array<string, mixed> $action
     * @return array{type: string, ok: bool, detail?: string}
     */
    private function sendEmail(array $action, Entity $entity): array
    {
        $to = $this->resolveRecipientEmail($action, $entity);

        if ($to === null || $to === '') {
            return ['type' => 'SendEmail', 'ok' => false, 'detail' => 'No recipient email'];
        }

        $subjectTpl = (string) ($action['subject'] ?? '');
        $bodyTpl = (string) ($action['body'] ?? '');
        $isHtml = (bool) ($action['isHtml'] ?? true);

        $htmlizer = $this->htmlizerFactory->create(true);
        $subject = $this->templateRenderer->render($entity, $subjectTpl, $htmlizer);
        $body = $this->templateRenderer->render($entity, $bodyTpl, $htmlizer);

        /** @var Email $email */
        $email = $this->entityManager->getRDBRepositoryByClass(Email::class)->getNew();
        $email->set([
            'subject' => $subject,
            'body' => $body,
            'isHtml' => $isHtml,
            'to' => $to,
            'isSystem' => true,
            'parentId' => $entity->getId(),
            'parentType' => $entity->getEntityType(),
        ]);

        try {
            $this->emailSender->send($email);

            return ['type' => 'SendEmail', 'ok' => true, 'detail' => $to];
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

        $notification = $this->entityManager->getRDBRepositoryByClass(Notification::class)->getNew();
        $notification
            ->setType(Notification::TYPE_MESSAGE)
            ->setUserId($userId)
            ->setMessage($message)
            ->setRelated(LinkParent::create($entity->getEntityType(), $entity->getId()));

        $this->entityManager->saveEntity($notification);

        return ['type' => 'CreateNotification', 'ok' => true, 'detail' => $userId];
    }

    /**
     * @param array<string, mixed> $action
     */
    private function resolveRecipientEmail(array $action, Entity $entity): ?string
    {
        if (!empty($action['email']) && is_string($action['email'])) {
            return trim($action['email']);
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

        $email = $user->get('emailAddress');

        return is_string($email) && $email !== '' ? $email : null;
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

        // Attribute holding a user id on the entity
        if ($entity->hasAttribute($to)) {
            $id = $entity->get($to);

            return is_string($id) && $id !== '' ? $id : null;
        }

        return null;
    }
}
