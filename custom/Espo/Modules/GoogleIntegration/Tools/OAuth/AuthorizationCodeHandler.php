<?php

namespace Espo\Modules\GoogleIntegration\Tools\OAuth;

use Espo\Core\Exceptions\Error;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\ExternalAccount\Clients\OAuth2Abstract;
use Espo\Core\HookManager;
use Espo\Core\Utils\Config;
use Espo\Entities\ExternalAccount as ExternalAccountEntity;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google as GoogleClient;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\EntityManager;

/**
 * Token exchange with the same redirect_uri as the authorize popup (Espo core canonical + accepted slash variant).
 */
class AuthorizationCodeHandler
{
    public function __construct(
        private EntityManager $entityManager,
        private ClientManager $clientManager,
        private Config $config,
        private HookManager $hookManager,
    ) {}

    public function exchange(string $userId, string $code, ?string $redirectUriFromClient): bool
    {
        $integration = Installer::INTEGRATION_ID;
        $redirectUri = RedirectUri::resolve($this->config, $redirectUriFromClient);

        $entity = $this->entityManager->getEntityById(
            ExternalAccountEntity::ENTITY_TYPE,
            $integration . '__' . $userId
        );

        if ($entity === null) {
            throw new Error("External Account $integration not found for $userId.");
        }

        $entity->set('enabled', true);
        $this->entityManager->saveEntity($entity);

        $client = $this->clientManager->create($integration, $userId);

        if (!$client instanceof OAuth2Abstract) {
            throw new Error("Could not load client for $integration.");
        }

        $client->setParams(['redirectUri' => $redirectUri]);

        $result = $client->getAccessTokenFromAuthorizationCode($code);

        if (empty($result) || empty($result['accessToken'])) {
            throw new Error("Could not get access token for $integration.");
        }

        $hasNewRefreshToken = array_key_exists('refreshToken', $result)
            && is_string($result['refreshToken'])
            && $result['refreshToken'] !== '';

        if (!$hasNewRefreshToken) {
            unset($result['refreshToken']);
        }

        $entity->clear('accessToken');

        if ($hasNewRefreshToken) {
            $entity->clear('refreshToken');
        }

        $entity->clear('tokenType');
        $entity->clear('expiresAt');

        foreach ($result as $name => $value) {
            $entity->set($name, $value);
        }

        $client->setParams($result);

        if ($client instanceof GoogleClient) {
            $profile = $client->getGoogleAccountProfile();

            if ($profile !== null) {
                foreach ($profile as $name => $value) {
                    $entity->set($name, $value);
                }
            }
        }

        $this->entityManager->saveEntity($entity);

        $this->hookManager->process(ExternalAccountEntity::ENTITY_TYPE, 'afterConnect', $entity, [
            'integration' => $integration,
            'userId' => $userId,
            'code' => $code,
        ]);

        return true;
    }
}
