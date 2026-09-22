<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reporting;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Resolves a primary email address from a CRM record selected in the export modal.
 */
class EmailRecipientResolver
{
    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
        private Acl $acl,
    ) {}

    /**
     * @return string[]
     */
    public function listEmailCapableEntityTypes(): array
    {
        $list = [];

        foreach ($this->metadata->get('scopes', []) as $scope => $defs) {
            if (!($defs['entity'] ?? false)) {
                continue;
            }

            if (!$this->hasEmailField($scope)) {
                continue;
            }

            $list[] = $scope;
        }

        sort($list);

        return $list;
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    public function resolvePrimaryEmail(string $entityType, string $id): string
    {
        if ($entityType === '' || $id === '') {
            throw new BadRequest('Email recipient is required.');
        }

        if (!$this->hasEmailField($entityType)) {
            throw new BadRequest("Entity type '$entityType' has no email field.");
        }

        if (!$this->acl->check($entityType, Table::ACTION_READ)) {
            throw new Forbidden();
        }

        $entity = $this->entityManager->getEntityById($entityType, $id);

        if ($entity === null) {
            throw new BadRequest('Email recipient record was not found.');
        }

        // Scope-level check above is not enough: getEntityById loads the
        // primary email via the ORM email join. Without record + field ACL,
        // a team/own Contact user can resolve another record's address by id
        // through POST .../reporting/email-export (emailDelivery source=record).
        if (!$this->acl->checkEntityRead($entity)) {
            throw new Forbidden();
        }

        $emailField = $this->resolveEmailField($entityType);

        if ($emailField !== null && !$this->acl->checkField($entityType, $emailField)) {
            throw new Forbidden();
        }

        $email = $this->extractPrimaryEmail($entity);

        if ($email === null || $email === '') {
            throw new BadRequest('Selected record has no email address.');
        }

        return $email;
    }

    private function hasEmailField(string $entityType): bool
    {
        return $this->resolveEmailField($entityType) !== null;
    }

    private function resolveEmailField(string $entityType): ?string
    {
        $fields = $this->metadata->get(['entityDefs', $entityType, 'fields'], []);

        if (!is_array($fields)) {
            return null;
        }

        if (isset($fields['emailAddress']) && is_array($fields['emailAddress'])) {
            return 'emailAddress';
        }

        foreach ($fields as $name => $defs) {
            if (!is_array($defs)) {
                continue;
            }

            if (($defs['type'] ?? null) === 'email') {
                return is_string($name) ? $name : null;
            }
        }

        return null;
    }

    private function extractPrimaryEmail(Entity $entity): ?string
    {
        $emailAddressData = $entity->get('emailAddressData');

        if (is_array($emailAddressData)) {
            foreach ($emailAddressData as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $address = $row['emailAddress'] ?? null;

                if (!is_string($address) || trim($address) === '') {
                    continue;
                }

                if (!empty($row['primary'])) {
                    return trim($address);
                }
            }

            foreach ($emailAddressData as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $address = $row['emailAddress'] ?? null;

                if (is_string($address) && trim($address) !== '') {
                    return trim($address);
                }
            }
        }

        $singleton = $entity->get('emailAddress');

        if (is_string($singleton) && trim($singleton) !== '') {
            return trim($singleton);
        }

        return null;
    }
}
