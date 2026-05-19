<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google;
use Espo\Modules\GoogleIntegration\Tools\Installer;

class GoogleClientProvider
{
    public function __construct(
        private ClientManager $clientManager,
        private User $user
    ) {}

    public function get(): Google
    {
        if (!$this->user->getId()) {
            throw new Forbidden();
        }

        $client = $this->clientManager->create(Installer::INTEGRATION_ID, $this->user->getId());

        if (!$client instanceof Google) {
            throw new Forbidden('Google account is not connected.');
        }

        return $client;
    }
}
