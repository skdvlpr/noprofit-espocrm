<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Notification;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Config;
use Espo\Modules\NonprofitEspocrm\Tools\WebPush\WebPushPreferenceChecker;
use Espo\Modules\NonprofitEspocrm\Tools\WebPush\WebPushService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Optional browser Web Push when Preferences.webPushEnabled is on.
 * Respects assignmentPushNotificationsIgnoreEntityTypeList (parity with in-app).
 */
class AfterSaveWebPush implements AfterSave
{
    public static int $order = 90;

    public function __construct(
        private EntityManager $entityManager,
        private WebPushService $webPushService,
        private WebPushPreferenceChecker $preferenceChecker,
        private Config $config,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SILENT)) {
            return;
        }

        if (!$entity->isNew()) {
            return;
        }

        $userId = (string) ($entity->get('userId') ?? '');

        if ($userId === '') {
            return;
        }

        $prefs = $this->entityManager->getEntityById('Preferences', $userId);

        if (!$prefs) {
            return;
        }

        $relatedType = (string) ($entity->get('relatedType') ?? '');
        $data = $entity->get('data');
        $fromData = is_object($data) ? (string) ($data->entityType ?? '') : '';
        $entityType = $relatedType !== '' ? $relatedType : $fromData;

        if (!$this->preferenceChecker->allowsEntity($prefs, $entityType !== '' ? $entityType : null)) {
            return;
        }

        $title = 'Safehouse CRM';
        $body = trim(strip_tags((string) ($entity->get('message') ?? '')));

        if ($body === '') {
            $body = 'New notification';
        }

        $url = '#Notification';
        $relatedId = (string) ($entity->get('relatedId') ?? '');

        if ($relatedType !== '' && $relatedId !== '') {
            $url = '#' . $relatedType . '/view/' . $relatedId;
        }

        $siteUrl = rtrim((string) ($this->config->get('siteUrl') ?? ''), '/');
        $absoluteUrl = $siteUrl !== '' ? $siteUrl . '/' . ltrim($url, '/') : $url;

        // Keep room for deep link text (notification click also opens `url`).
        $suffix = ' · ' . $absoluteUrl;
        $maxBody = 180 - mb_strlen($suffix);

        if ($maxBody < 40) {
            $maxBody = 40;
        }

        if (mb_strlen($body) > $maxBody) {
            $body = mb_substr($body, 0, $maxBody - 1) . '…';
        }

        if ($url !== '#Notification') {
            $body .= $suffix;
        }

        $this->webPushService->sendToUser($userId, [
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => 'notification-' . $entity->getId(),
        ]);
    }
}
