<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarProvisioner;
use Espo\Modules\GoogleIntegration\Tools\Calendar\ManagerCalendarShare;
use Espo\ORM\EntityManager;

/**
 * Share-target picker payload for Admin/Manager (connected users + teams with members).
 */
class GetSharePickerData implements Action
{
    public function __construct(
        private ManagerCalendarShare $managerCalendarShare,
        private User $user,
        private EntityManager $entityManager,
        private CalendarProvisioner $calendarProvisioner,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->managerCalendarShare->actorCanShare($this->user)) {
            throw new Forbidden();
        }

        $connectedIds = $this->managerCalendarShare->listConnectedUserIds();
        $users = [];

        foreach ($connectedIds as $userId) {
            /** @var ?User $u */
            $u = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

            if ($u === null) {
                continue;
            }

            $users[] = [
                'id' => $userId,
                'name' => (string) ($u->get('name') ?: $u->get('userName') ?: $userId),
                'userName' => (string) ($u->get('userName') ?: ''),
                'hasConsent' => $this->managerCalendarShare->userHasManagerWriteConsent($userId),
            ];
        }

        usort(
            $users,
            static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name'])
        );

        [$namePrefix, $nameSuffix] = $this->calendarProvisioner->getPrefixSuffix();

        return ResponseComposer::json([
            'users' => $users,
            'connectedUserIds' => $connectedIds,
            'teams' => $this->managerCalendarShare->listTeamsWithGoogleMembers(),
            'namePrefix' => $namePrefix,
            'nameSuffix' => $nameSuffix,
        ]);
    }
}
