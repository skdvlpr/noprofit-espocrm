<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Task;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Mail\EmailSender;
use Espo\Core\Name\Field;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Log;
use Espo\Entities\Email;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Tools\WebPush\WebPushPreferenceChecker;
use Espo\Modules\NonprofitEspocrm\Tools\WebPush\WebPushService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;
use Throwable;

/**
 * Optional per-task notify channels (Email / InApp / Push) when assignee is set/changed.
 * Controlled from Task detail/edit UI (not list). Default is none.
 */
class AfterSaveNotifyAssignees implements AfterSave
{
    public static int $order = 90;

    public function __construct(
        private EntityManager $entityManager,
        private Language $language,
        private User $user,
        private EmailSender $emailSender,
        private Config $config,
        private WebPushService $webPushService,
        private WebPushPreferenceChecker $preferenceChecker,
        private Log $log,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SILENT)) {
            return;
        }

        $channels = $this->normalizeChannels($entity->get('notifyChannelList'));

        if ($channels === []) {
            return;
        }

        $assigneeId = (string) ($entity->get('assignedUserId') ?? '');

        if ($assigneeId === '') {
            return;
        }

        $isNew = $entity->isNew();
        $assigneeChanged = $entity->isAttributeChanged('assignedUserId');
        $channelsChanged = $entity->isAttributeChanged('notifyChannelList');

        if (!$isNew && !$assigneeChanged && !$channelsChanged) {
            return;
        }

        if ($assigneeId === $this->user->getId()) {
            return;
        }

        /** @var ?User $assignee */
        $assignee = $this->entityManager->getEntityById(User::ENTITY_TYPE, $assigneeId);

        if (!$assignee || !$assignee->get('isActive')) {
            return;
        }

        $name = (string) ($entity->get(Field::NAME) ?? '');
        $displayName = $name !== '' ? $name : 'Task';
        $message = $this->language->translate('assignedYouTask', 'messages', 'Task');
        $message = str_replace('{name}', $displayName, $message);

        if (in_array('InApp', $channels, true)) {
            $notification = $this->entityManager->getNewEntity(Notification::ENTITY_TYPE);
            $notification->set([
                'type' => Notification::TYPE_ASSIGN,
                'userId' => $assigneeId,
                'data' => (object) [
                    'entityType' => 'Task',
                    'entityId' => $entity->getId(),
                    'entityName' => $name,
                    'userId' => $this->user->getId(),
                    'userName' => $this->user->getName(),
                ],
                'relatedType' => 'Task',
                'relatedId' => $entity->getId(),
                'message' => $message,
            ]);

            // SILENT so WebPush hook does not auto-fan-out; Push is explicit below.
            $this->entityManager->saveEntity($notification, [SaveOption::SILENT => true]);
        }

        if (in_array('Push', $channels, true)) {
            $prefs = $this->entityManager->getEntityById('Preferences', $assigneeId);

            if ($prefs && $this->preferenceChecker->allowsEntity($prefs, 'Task')) {
                $this->webPushService->sendToUser($assigneeId, [
                    'title' => 'Safehouse CRM',
                    'body' => strip_tags($message),
                    'url' => '#Task/view/' . $entity->getId(),
                    'tag' => 'task-assign-' . $entity->getId(),
                ]);
            }
        }

        if (in_array('Email', $channels, true)) {
            $this->sendAssigneeEmail($assignee, $entity, $displayName, $message);
        }
    }

    /**
     * @param mixed $raw
     * @return string[]
     */
    private function normalizeChannels(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $allowed = ['Email', 'InApp', 'Push'];
        $out = [];

        foreach ($raw as $item) {
            if (is_string($item) && in_array($item, $allowed, true)) {
                $out[$item] = true;
            }
        }

        return array_keys($out);
    }

    private function sendAssigneeEmail(
        User $assignee,
        Entity $task,
        string $displayName,
        string $message
    ): void {
        $from = (string) ($this->config->get('outboundEmailFromAddress') ?? '');
        $to = $this->primaryEmail($assignee);

        if ($from === '' || $to === '') {
            return;
        }

        $siteUrl = rtrim((string) ($this->config->get('siteUrl') ?? ''), '/');
        $url = $siteUrl !== ''
            ? $siteUrl . '/#Task/view/' . $task->getId()
            : '#Task/view/' . $task->getId();

        $subject = $this->language->translate('assignedYouTaskEmailSubject', 'messages', 'Task');
        $subject = str_replace('{name}', $displayName, $subject);

        $body = '<p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</a></p>';

        try {
            /** @var Email $email */
            $email = $this->entityManager->getRDBRepositoryByClass(Email::class)->getNew();
            $email->set([
                'subject' => $subject,
                'body' => $body,
                'isHtml' => true,
                'from' => $from,
                'to' => $to,
                'isSystem' => true,
                'parentId' => $task->getId(),
                'parentType' => 'Task',
            ]);

            $this->emailSender->send($email);
        } catch (Throwable $e) {
            $this->log->warning(
                'Task assignee email failed: ' . $e->getMessage()
            );
        }
    }

    private function primaryEmail(User $user): string
    {
        $address = trim((string) ($user->get('emailAddress') ?? ''));

        return $address;
    }
}
