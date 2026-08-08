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
        return $this->user->isLogged() && !$this->user->isPortal();
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
        $sent = $this->createService()->sendToUser($user->getId(), [
            'title' => 'Safehouse CRM',
            'body' => 'Browser push is working.',
            'url' => '#',
            'tag' => 'web-push-test',
        ]);

        return (object) ['sent' => $sent];
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
        if (!$this->user->isLogged() || $this->user->isSystem() || $this->user->isPortal()) {
            throw new Forbidden();
        }

        return $this->user;
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
