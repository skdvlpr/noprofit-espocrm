<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\WebPush;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * VAPID provision + send Web Push for opted-in users.
 */
class WebPushService
{
    private const CONFIG_PUBLIC = 'webPushVapidPublicKey';
    private const CONFIG_PRIVATE = 'webPushVapidPrivateKey';
    private const CONFIG_SUBJECT = 'webPushVapidSubject';

    private static bool $autoloadDone = false;

    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
        private ConfigWriter $configWriter,
        private Log $log,
    ) {
        $this->ensureAutoload();
    }

    public function getPublicKey(): ?string
    {
        $this->ensureVapidKeys();

        $key = $this->config->get(self::CONFIG_PUBLIC);

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * @param array{endpoint: string, keys: array{p256dh: string, auth: string}, expirationTime?: mixed} $subscription
     */
    public function saveSubscription(string $userId, array $subscription, ?string $userAgent = null): void
    {
        $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
        $p256dh = trim((string) ($subscription['keys']['p256dh'] ?? ''));
        $auth = trim((string) ($subscription['keys']['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            throw new \Espo\Core\Exceptions\BadRequest('Invalid push subscription.');
        }

        $existing = $this->entityManager
            ->getRDBRepository('WebPushSubscription')
            ->where([
                'userId' => $userId,
                'endpoint' => $endpoint,
            ])
            ->findOne();

        $entity = $existing ?? $this->entityManager->getNewEntity('WebPushSubscription');
        $entity->set([
            'name' => mb_substr($endpoint, 0, 100),
            'userId' => $userId,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'userAgent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);

        $this->entityManager->saveEntity($entity);
    }

    public function deleteSubscription(string $userId, ?string $endpoint = null): int
    {
        $where = ['userId' => $userId];

        if ($endpoint !== null && $endpoint !== '') {
            $where['endpoint'] = $endpoint;
        }

        $count = 0;

        foreach ($this->entityManager->getRDBRepository('WebPushSubscription')->where($where)->find() as $row) {
            $this->entityManager->removeEntity($row);
            $count++;
        }

        return $count;
    }

    /**
     * @param array{title: string, body: string, url?: string, tag?: string} $payload
     */
    public function sendToUser(string $userId, array $payload): int
    {
        $this->ensureVapidKeys();

        $publicKey = (string) $this->config->get(self::CONFIG_PUBLIC);
        $privateKey = (string) $this->config->get(self::CONFIG_PRIVATE);
        $subject = (string) ($this->config->get(self::CONFIG_SUBJECT) ?: 'mailto:admin@localhost');

        if ($publicKey === '' || $privateKey === '') {
            return 0;
        }

        $subs = $this->entityManager
            ->getRDBRepository('WebPushSubscription')
            ->where(['userId' => $userId])
            ->find();

        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        $webPush = new WebPush($auth);
        $webPush->setReuseVAPIDHeaders(true);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $sent = 0;

        foreach ($subs as $row) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => (string) $row->get('endpoint'),
                    'keys' => [
                        'p256dh' => (string) $row->get('p256dh'),
                        'auth' => (string) $row->get('auth'),
                    ],
                ]);

                $report = $webPush->sendOneNotification($subscription, $json);

                if ($report->isSuccess()) {
                    $sent++;
                    continue;
                }

                $reason = (string) $report->getReason();
                $gone = $report->isSubscriptionExpired()
                    || str_contains($reason, '410')
                    || str_contains(strtolower($reason), 'gone');

                if ($gone) {
                    $this->entityManager->removeEntity($row);
                }

                $this->log->warning(
                    'WebPush send failed for user {userId}: {reason}',
                    [
                        'userId' => $userId,
                        'reason' => $reason,
                    ]
                );
            } catch (Throwable $e) {
                $this->log->warning(
                    'WebPush exception for user {userId}: {message}',
                    [
                        'userId' => $userId,
                        'message' => $e->getMessage(),
                    ]
                );
            }
        }

        return $sent;
    }

    public function ensureVapidKeys(): void
    {
        $public = $this->config->get(self::CONFIG_PUBLIC);
        $private = $this->config->get(self::CONFIG_PRIVATE);

        if (is_string($public) && $public !== '' && is_string($private) && $private !== '') {
            return;
        }

        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();

        $this->configWriter->set(self::CONFIG_PUBLIC, $keys['publicKey']);
        $this->configWriter->set(self::CONFIG_PRIVATE, $keys['privateKey']);

        $siteUrl = (string) ($this->config->get('siteUrl') ?? '');
        $subject = $siteUrl !== '' ? $siteUrl : 'mailto:admin@localhost';
        $this->configWriter->set(self::CONFIG_SUBJECT, $subject);
        $this->configWriter->save();

        // Reload in-memory config for this request.
        $this->config->update();
    }

    private function ensureAutoload(): void
    {
        if (self::$autoloadDone) {
            return;
        }

        $path = dirname(__DIR__, 2) . '/libs/web-push/vendor/autoload.php';

        if (is_file($path)) {
            require_once $path;
        }

        self::$autoloadDone = true;
    }
}
