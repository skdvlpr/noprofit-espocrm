<?php

namespace Espo\Modules\GoogleIntegration\Tools\OAuth;

use Espo\Core\Exceptions\Error;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\ExternalAccount\Clients\OAuth2Abstract;
use Espo\Core\HookManager;
use Espo\Core\Utils\Config;
use Espo\Entities\ExternalAccount as ExternalAccountEntity;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google as GoogleClient;
use Espo\Modules\GoogleIntegration\Tools\ExternalAccount\AccountProvisioner;
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
        private AccountProvisioner $accountProvisioner,
    ) {}

    public function exchange(string $userId, string $code, ?string $redirectUriFromClient): bool
    {
        $integration = Installer::INTEGRATION_ID;
        $redirectUri = RedirectUri::resolve($this->config, $redirectUriFromClient);

        $entity = $this->accountProvisioner->ensureForUser($userId);

        $entity->set('enabled', true);
        $this->entityManager->saveEntity($entity);

        $client = $this->clientManager->create($integration, $userId);

        if (!$client instanceof OAuth2Abstract) {
            throw new Error(
                "Could not load OAuth client for $integration. "
                . 'Enable the integration under Administration → Integrations → Google calendar & drive '
                . 'and confirm Client ID / Client Secret are saved.'
            );
        }

        $client->setParams(['redirectUri' => $redirectUri]);

        $result = $client->getAccessTokenFromAuthorizationCode($code);

        if (empty($result) || empty($result['accessToken'])) {
            throw new Error("Could not get access token for $integration.");
        }

        $entity->clear('accessToken');
        $entity->clear('tokenType');
        $entity->clear('expiresAt');

        foreach ($result as $name => $value) {
            // Google often omits refresh_token on reconnect; keep the existing
            // long-lived token unless a new non-empty one is issued.
            if ($name === 'refreshToken' && (!is_string($value) || $value === '')) {
                continue;
            }

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
