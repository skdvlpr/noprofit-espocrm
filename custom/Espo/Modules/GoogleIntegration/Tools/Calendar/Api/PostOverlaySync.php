<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\OverlaySyncRunner;
use Throwable;

/**
 * On-demand Google → CRM overlay sync for the current user (Calendar toolbar).
 */
class PostOverlaySync implements Action
{
    public function __construct(
        private Acl $acl,
        private User $user,
        private OverlaySyncRunner $overlaySyncRunner,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->acl->check('Calendar')) {
            throw new Forbidden();
        }

        $userId = $this->user->getId();

        if (!$userId) {
            throw new Forbidden();
        }

        try {
            $this->overlaySyncRunner->syncForUser($userId);
        } catch (Forbidden $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new Error('Google overlay sync failed: ' . $e->getMessage());
        }

        return ResponseComposer::json([
            'ok' => true,
            'autoIntervalMinutes' => 15,
        ]);
    }
}
