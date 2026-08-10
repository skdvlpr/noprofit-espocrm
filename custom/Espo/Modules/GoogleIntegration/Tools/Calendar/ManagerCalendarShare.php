<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\Name\Field;
use Espo\Entities\Role as RoleEntity;
use Espo\Entities\Team;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Throwable;

/**
 * Admin/Manager fan-out targets for Google Calendar export (opt-in External Account).
 */
class ManagerCalendarShare
{
    public const ROLE_ADMIN = 'Admin';
    public const ROLE_MANAGER = 'Manager';

    public const FIELD_USERS = 'googleCalendarShareUsers';
    public const FIELD_TEAMS = 'googleCalendarShareTeams';

    public const CONSENT_ATTRIBUTE = 'allowManagerGoogleCalendarWrite';

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function actorCanShare(User $user): bool
    {
        if (!$user->getId() || $user->isApi() || $user->isSystem() || $user->isPortal()) {
            return false;
        }

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        foreach ($this->roleNamesForUser($user) as $name) {
            if ($name === self::ROLE_ADMIN || $name === self::ROLE_MANAGER) {
                return true;
            }
        }

        return false;
    }

    /**
     * Active users selected via Users / Teams who connected Google and opted in.
     *
     * @return list<string>
     */
    public function resolveEligibleTargetUserIds(Entity $entity): array
    {
        $candidateIds = $this->collectCandidateUserIds($entity);

        if ($candidateIds === []) {
            return [];
        }

        $eligible = [];

        foreach ($candidateIds as $userId) {
            if ($this->isEligibleShareTarget($userId)) {
                $eligible[] = $userId;
            }
        }

        return $eligible;
    }

    public function userHasManagerWriteConsent(string $userId): bool
    {
        $account = $this->findGoogleExternalAccount($userId);

        if ($account === null || !$account->get('enabled')) {
            return false;
        }

        return (bool) $account->get(self::CONSENT_ATTRIBUTE);
    }

    public function userHasGoogleConnected(string $userId): bool
    {
        $account = $this->findGoogleExternalAccount($userId);

        if ($account === null || !$account->get('enabled')) {
            return false;
        }

        $access = $account->get('accessToken');
        $refresh = $account->get('refreshToken');

        return (is_string($access) && $access !== '')
            || (is_string($refresh) && $refresh !== '');
    }

    /**
     * Active internal users with Google Calendar External Account connected.
     *
     * @return list<string>
     */
    public function listConnectedUserIds(): array
    {
        $prefix = Installer::INTEGRATION_ID . '__';
        $ids = [];

        $accounts = $this->entityManager
            ->getRDBRepository('ExternalAccount')
            ->where([
                Attribute::DELETED => false,
                'enabled' => true,
            ])
            ->find();

        foreach ($accounts as $account) {
            $accountId = $account->getId();

            if (!is_string($accountId) || !str_starts_with($accountId, $prefix)) {
                continue;
            }

            $userId = substr($accountId, strlen($prefix));

            if ($userId === '' || !$this->userHasGoogleConnected($userId)) {
                continue;
            }

            /** @var ?User $user */
            $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

            if ($user === null || $user->isPortal() || $user->isApi() || $user->isSystem()) {
                continue;
            }

            if ($user->get('isActive') === false) {
                continue;
            }

            $ids[] = $userId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Teams with member Google connection status (for share picker UI).
     *
     * @return list<array{
     *     id: string,
     *     name: string,
     *     memberCount: int,
     *     googleConnectedCount: int,
     *     members: list<array{
     *         id: string,
     *         name: string,
     *         userName: string,
     *         googleConnected: bool,
     *         hasConsent: bool
     *     }>
     * }>
     */
    public function listTeamsWithGoogleMembers(): array
    {
        $connectedSet = array_fill_keys($this->listConnectedUserIds(), true);
        $rows = [];

        /** @var iterable<Team> $teams */
        $teams = $this->entityManager
            ->getRDBRepository(Team::ENTITY_TYPE)
            ->where([Attribute::DELETED => false])
            ->order('name')
            ->find();

        foreach ($teams as $team) {
            $teamId = $team->getId();

            if (!is_string($teamId) || $teamId === '') {
                continue;
            }

            $members = [];
            $googleCount = 0;

            /** @var iterable<User> $users */
            $users = $this->entityManager
                ->getRelation($team, 'users')
                ->find();

            foreach ($users as $user) {
                if ($user->isPortal() || $user->isApi() || $user->isSystem()) {
                    continue;
                }

                if ($user->get('isActive') === false) {
                    continue;
                }

                $userId = $user->getId();

                if (!is_string($userId) || $userId === '') {
                    continue;
                }

                $connected = isset($connectedSet[$userId]);
                $consent = $connected && $this->userHasManagerWriteConsent($userId);

                if ($connected) {
                    $googleCount++;
                }

                $members[] = [
                    'id' => $userId,
                    'name' => (string) ($user->get('name') ?: $user->get('userName') ?: $userId),
                    'userName' => (string) ($user->get('userName') ?: ''),
                    'googleConnected' => $connected,
                    'hasConsent' => $consent,
                ];
            }

            usort(
                $members,
                static function (array $a, array $b): int {
                    if ($a['googleConnected'] !== $b['googleConnected']) {
                        return $a['googleConnected'] ? -1 : 1;
                    }

                    return strcasecmp($a['name'], $b['name']);
                }
            );

            $rows[] = [
                'id' => $teamId,
                'name' => (string) ($team->get('name') ?: $teamId),
                'memberCount' => count($members),
                'googleConnectedCount' => $googleCount,
                'members' => $members,
            ];
        }

        return $rows;
    }

    private function findGoogleExternalAccount(string $userId): ?Entity
    {
        return $this->entityManager
            ->getRDBRepository('ExternalAccount')
            ->where([
                Attribute::ID => Installer::INTEGRATION_ID . '__' . $userId,
                Attribute::DELETED => false,
            ])
            ->findOne();
    }

    /**
     * @return list<string>
     */
    private function collectCandidateUserIds(Entity $entity): array
    {
        $ids = [];

        foreach ($this->readLinkMultipleIds($entity, self::FIELD_USERS) as $userId) {
            $ids[$userId] = true;
        }

        foreach ($this->readLinkMultipleIds($entity, self::FIELD_TEAMS) as $teamId) {
            /** @var ?Team $team */
            $team = $this->entityManager->getEntityById(Team::ENTITY_TYPE, $teamId);

            if ($team === null) {
                continue;
            }

            /** @var iterable<User> $users */
            $users = $this->entityManager
                ->getRelation($team, 'users')
                ->find();

            foreach ($users as $user) {
                $id = $user->getId();

                if (is_string($id) && $id !== '') {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * @return list<string>
     */
    public function readLinkMultipleIds(Entity $entity, string $field): array
    {
        if (!$entity->hasRelation($field)) {
            return [];
        }

        try {
            $raw = $entity->getLinkMultipleIdList($field);
        } catch (Throwable) {
            return [];
        }

        $ids = [];

        foreach ($raw as $id) {
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function isEligibleShareTarget(string $userId): bool
    {
        /** @var ?User $user */
        $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

        if ($user === null) {
            return false;
        }

        if ($user->isPortal() || $user->isApi() || $user->isSystem()) {
            return false;
        }

        if ($user->get('isActive') === false) {
            return false;
        }

        return $this->userHasManagerWriteConsent($userId);
    }

    /**
     * @return list<string>
     */
    private function roleNamesForUser(User $user): array
    {
        $names = [];

        /** @var iterable<RoleEntity> $userRoles */
        $userRoles = $this->entityManager
            ->getRelation($user, User::LINK_ROLES)
            ->find();

        foreach ($userRoles as $role) {
            $name = $role->get('name');

            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        /** @var iterable<Team> $teams */
        $teams = $this->entityManager
            ->getRelation($user, Field::TEAMS)
            ->find();

        foreach ($teams as $team) {
            /** @var iterable<RoleEntity> $teamRoles */
            $teamRoles = $this->entityManager
                ->getRelation($team, Team::LINK_ROLES)
                ->find();

            foreach ($teamRoles as $role) {
                $name = $role->get('name');

                if (is_string($name) && $name !== '') {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }
}
