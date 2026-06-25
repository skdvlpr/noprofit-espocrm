<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\AclManager;
use Espo\Core\Exceptions\Error;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google as GoogleClient;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\Modules\GoogleIntegration\Tools\IntegrationState;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Removes Google Calendar events and local link rows (idempotent, no calendarId guessing).
 */
class EventRemover
{
    private const LINK_ENTITY_TYPE = 'GoogleCalendarEventLink';

    public function __construct(
        private EntityManager $entityManager,
        private ClientManager $clientManager,
        private IntegrationState $integrationState,
        private AclManager $aclManager,
        private Log $log,
        private DateSourceProvider $dateSourceProvider
    ) {}

    /**
     * @param ?string $userId When set, only links for this user; when null, all users (CRM record delete).
     */
    public function removeAllLinksForEntity(Entity $entity, ?string $userId = null): void
    {
        if (!$this->integrationState->isGoogleIntegrationEnabled()) {
            return;
        }

        if ($entity->getId() === null || $entity->getId() === '') {
            return;
        }

        $where = [
            'sourceEntityType' => $entity->getEntityType(),
            'sourceEntityId' => $entity->getId(),
            'deleted' => false,
        ];

        if ($userId !== null && $userId !== '') {
            $where['userId'] = $userId;
        }

        $links = $this->entityManager
            ->getRDBRepository(self::LINK_ENTITY_TYPE)
            ->where($where)
            ->find();

        foreach ($links as $link) {
            $this->removeLink($link);
        }
    }

    /**
     * @param array<int, string> $activeDateTypes
     */
    public function removeStaleDateSourceLinks(
        Entity $entity,
        User $user,
        array $activeDateTypes
    ): void {
        if (!$this->integrationState->isGoogleIntegrationEnabled()) {
            return;
        }

        if ($entity->getId() === null || $entity->getId() === '') {
            return;
        }

        if (!$this->canRemoveForUser($entity, $user)) {
            return;
        }

        $client = $this->resolveGoogleClient($user);

        if ($client === null) {
            return;
        }

        $allowedTypes = $this->getAllowedSourceDateTypes($entity->getEntityType());

        $links = $this->entityManager
            ->getRDBRepository(self::LINK_ENTITY_TYPE)
            ->where([
                'sourceEntityType' => $entity->getEntityType(),
                'sourceEntityId' => $entity->getId(),
                'userId' => $user->getId(),
                'deleted' => false,
            ])
            ->find();

        foreach ($links as $link) {
            $sourceDateType = (string) ($link->get('sourceDateType') ?? '');
            $effectiveDateType = $this->dateSourceProvider->canonicalSourceDateType(
                $entity->getEntityType(),
                $sourceDateType
            );

            if (
                !in_array($effectiveDateType, $allowedTypes, true)
                || in_array($effectiveDateType, $activeDateTypes, true)
            ) {
                continue;
            }

            $this->deleteGoogleEventAndRemoveLink($client, $link);
        }
    }

    public function removeLink(Entity $link): void
    {
        $userId = $link->get('userId');

        if (!is_string($userId) || $userId === '') {
            $this->entityManager->removeEntity($link);

            return;
        }

        $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

        if ($user === null) {
            $this->entityManager->removeEntity($link);

            return;
        }

        $client = $this->resolveGoogleClient($user);

        if ($client === null) {
            $this->entityManager->removeEntity($link);

            return;
        }

        $this->deleteGoogleEventAndRemoveLink($client, $link);
    }

    private function deleteGoogleEventAndRemoveLink(GoogleClient $client, Entity $link): void
    {
        $googleEventId = $link->get('googleEventId');
        $calendarId = trim((string) ($link->get('calendarId') ?? ''));

        if ($calendarId === '') {
            $this->log->warning(
                'Google Calendar delete skipped: missing calendarId on link '
                . (string) $link->getId()
            );
            $this->entityManager->removeEntity($link);

            return;
        }

        if (is_string($googleEventId) && $googleEventId !== '') {
            try {
                $client->deleteCalendarEvent($googleEventId, $calendarId);
            } catch (Error $e) {
                $isGone = $e->getCode() === 404
                    || $e->getCode() === 410
                    || stripos($e->getMessage(), 'has been deleted') !== false
                    || stripos($e->getMessage(), 'not found') !== false;

                if (!$isGone) {
                    throw $e;
                }
            }
        }

        $this->entityManager->removeEntity($link);
    }

    private function resolveGoogleClient(User $user): ?GoogleClient
    {
        if (!$user->getId() || $user->isApi()) {
            return null;
        }

        $client = $this->clientManager->create(Installer::INTEGRATION_ID, $user->getId());

        return $client instanceof GoogleClient ? $client : null;
    }

    private function canRemoveForUser(Entity $entity, User $user): bool
    {
        if (!$user->getId() || $user->isApi() || $user->isSystem()) {
            return false;
        }

        return $this->aclManager->checkEntityEdit($user, $entity);
    }

    /**
     * @return array<int, string>
     */
    private function getAllowedSourceDateTypes(string $entityType): array
    {
        return array_values(array_filter(array_map(
            static fn (array $source): string => (string) ($source['sourceDateType'] ?? 'main'),
            $this->dateSourceProvider->getActiveSourcesForEntityType($entityType)
        )));
    }

}
