<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reminder\Sender;

use Espo\Core\Htmlizer\HtmlizerFactory;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\TemplateFileManager;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\Modules\Crm\Entities\Reminder;
use Espo\Modules\NonprofitEspocrm\Tools\WebPush\WebPushService;
use Espo\ORM\EntityManager;
use RuntimeException;

/**
 * Native Reminder type=Push — mirrors EmailReminder templates + WebPush delivery.
 */
class PushReminder
{
    public function __construct(
        private EntityManager $entityManager,
        private TemplateFileManager $templateFileManager,
        private HtmlizerFactory $htmlizerFactory,
        private Language $language,
        private Config $config,
        private WebPushService $webPushService,
    ) {}

    public function send(Reminder $reminder): void
    {
        $entityType = $reminder->getTargetEntityType();
        $entityId = $reminder->getTargetEntityId();
        $userId = $reminder->getUserId();

        if (!$entityType || !$entityId || !$userId) {
            throw new RuntimeException('Bad reminder.');
        }

        $prefs = $this->entityManager->getEntityById('Preferences', $userId);

        if (!$prefs || !$prefs->get('webPushEnabled')) {
            return;
        }

        $user = $this->entityManager->getRDBRepositoryByClass(User::class)->getById($userId);
        $entity = $this->entityManager->getEntityById($entityType, $entityId);

        if (!$user || !$entity instanceof CoreEntity) {
            return;
        }

        if (
            $entity->hasLinkMultipleField('users') &&
            $entity->hasAttribute('usersColumns')
        ) {
            $status = $entity->getLinkMultipleColumn('users', 'status', $user->getId());

            if ($status === Meeting::ATTENDEE_STATUS_DECLINED) {
                return;
            }
        }

        [$title, $body, $url] = $this->getTitleBodyUrl($entity, $user);

        $this->webPushService->sendToUser($userId, [
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => 'reminder-push-' . $reminder->getId(),
        ]);
    }

    /**
     * @return array{string, string, string}
     */
    private function getTitleBodyUrl(CoreEntity $entity, User $user): array
    {
        $entityType = $entity->getEntityType();
        $entityId = $entity->getId();

        $subjectTpl = $this->templateFileManager
            ->getTemplate('reminderPush', 'subject', $entityType);
        $bodyTpl = $this->templateFileManager
            ->getTemplate('reminderPush', 'body', $entityType);

        $subjectTpl = str_replace(["\n", "\r"], '', $subjectTpl);
        $bodyTpl = trim(str_replace(["\r"], '', $bodyTpl));

        $siteUrl = rtrim((string) ($this->config->get('siteUrl') ?? ''), '/');
        $translatedEntityType = $this->language->translateLabel($entityType, 'scopeNames');
        $hashUrl = '#' . $entityType . '/view/' . $entityId;
        $recordUrl = $siteUrl !== '' ? $siteUrl . '/' . ltrim($hashUrl, '/') : $hashUrl;

        $data = [
            'recordUrl' => $recordUrl,
            'entityType' => $translatedEntityType,
            'entityTypeLowerFirst' => Util::mbLowerCaseFirst($translatedEntityType),
            'userName' => $user->getName(),
        ];

        $htmlizer = $this->htmlizerFactory->createForUser($user);

        $title = trim(strip_tags($htmlizer->render($entity, $subjectTpl, $data, true)));
        $body = trim(strip_tags($htmlizer->render($entity, $bodyTpl, $data, false)));

        if ($title === '') {
            $title = $translatedEntityType;
        }

        if ($body === '') {
            $body = (string) ($entity->get('name') ?? $translatedEntityType);
        }

        if (mb_strlen($body) > 180) {
            $body = mb_substr($body, 0, 179) . '…';
        }

        return [$title, $body, $hashUrl];
    }
}
