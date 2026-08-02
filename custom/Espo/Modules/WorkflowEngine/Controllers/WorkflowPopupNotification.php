<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Controllers;

use Espo\Core\InjectableFactory;
use Espo\Entities\User;
use Espo\Modules\WorkflowEngine\Tools\PopupNotification\WorkflowMessageProvider;
use stdClass;

/**
 * Login-only polling endpoint for WF CreateNotification popups.
 * Must not live on WorkflowDefinition (admin-only ACL would hide popups from recipients).
 */
class WorkflowPopupNotification
{
    public function __construct(
        private User $user,
        private InjectableFactory $injectableFactory,
    ) {}

    /**
     * GET WorkflowPopupNotification
     *
     * @return list<stdClass>
     */
    public function getActionIndex(): array
    {
        /** @var WorkflowMessageProvider $provider */
        $provider = $this->injectableFactory->create(WorkflowMessageProvider::class);

        $list = [];

        foreach ($provider->get($this->user) as $item) {
            $list[] = (object) [
                'id' => $item->getId(),
                'data' => $item->getData(),
            ];
        }

        return $list;
    }
}
