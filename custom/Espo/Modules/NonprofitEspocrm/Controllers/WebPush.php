<?php

namespace Espo\Modules\NonprofitEspocrm\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Base;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Tools\WebPush\WebPushService;
use Espo\ORM\EntityManager;
use stdClass;

/**
 * Browser Web Push: public key, subscribe/unsubscribe, preference sync.
 */
class WebPush extends Base
{
    protected function checkAccess(): bool
    {
        // Espo 10 User has no isLogged(); session users are regular/admin with an id.
        return $this->isCrmSessionUser();
    }

    public function getActionPublicKey(): stdClass
    {
        $service = $this->createService();

        return (object) [
            'publicKey' => $service->getPublicKey(),
            'enabled' => $service->getPublicKey() !== null,
        ];
    }

    /**
     * @throws BadRequest|Forbidden
     */
    public function postActionSubscribe(Request $request): stdClass
    {
        $user = $this->requireUser();
        $data = $request->getParsedBody();
        $subscription = $data->subscription ?? null;

        if (!$subscription || !is_object($subscription)) {
            throw new BadRequest('No subscription.');
        }

        $payload = json_decode(json_encode($subscription), true);

        if (!is_array($payload)) {
            throw new BadRequest('Invalid subscription.');
        }

        $ua = $request->getHeader('User-Agent');
        $this->createService()->saveSubscription(
            $user->getId(),
            $payload,
            is_string($ua) ? $ua : null
        );
        $this->setPreference($user, true);

        return (object) ['ok' => true];
    }

    /**
     * @throws Forbidden
     */
    public function postActionUnsubscribe(Request $request): stdClass
    {
        $user = $this->requireUser();
        $data = $request->getParsedBody();
        $endpoint = is_string($data->endpoint ?? null) ? $data->endpoint : null;

        $removed = $this->createService()->deleteSubscription($user->getId(), $endpoint);
        $this->setPreference($user, false);

        return (object) ['ok' => true, 'removed' => $removed];
    }

    /**
     * @throws Forbidden
     */
    public function postActionTest(): stdClass
    {
        $user = $this->requireUser();
        $em = $this->getContainer()->getByClass(EntityManager::class);
        $subscriptionCount = $em->getRDBRepository('WebPushSubscription')
            ->where(['userId' => $user->getId()])
            ->count();

        $sent = $this->createService()->sendToUser($user->getId(), [
            'title' => 'Safehouse CRM',
            'body' => 'Browser push is working.',
            'url' => '#',
            'tag' => 'web-push-test',
        ]);

        $remaining = $em->getRDBRepository('WebPushSubscription')
            ->where(['userId' => $user->getId()])
            ->count();

        $reason = null;

        if ($sent < 1) {
            if ($subscriptionCount < 1) {
                $reason = 'no_subscription';
            } elseif ($remaining < 1) {
                $reason = 'stale_subscription';
            } else {
                $reason = 'send_failed';
            }
        }

        return (object) [
            'sent' => $sent,
            'subscriptionCount' => $subscriptionCount,
            'reason' => $reason,
        ];
    }

    private function createService(): WebPushService
    {
        /** @var InjectableFactory $factory */
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);

        return $factory->create(WebPushService::class);
    }

    /**
     * @throws Forbidden
     */
    private function requireUser(): User
    {
        if (!$this->isCrmSessionUser()) {
            throw new Forbidden();
        }

        return $this->user;
    }

    private function isCrmSessionUser(): bool
    {
        $id = $this->user->getId();

        return is_string($id)
            && $id !== ''
            && !$this->user->isPortal()
            && !$this->user->isSystem()
            && !$this->user->isApi();
    }

    private function setPreference(User $user, bool $enabled): void
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->getByClass(EntityManager::class);
        $prefs = $em->getEntityById('Preferences', $user->getId());

        if (!$prefs) {
            return;
        }

        $prefs->set('webPushEnabled', $enabled);
        $em->saveEntity($prefs);
    }
}
