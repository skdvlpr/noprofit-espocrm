<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Entities\EmailAddress;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;

/**
 * One login email per User. Contact may share the address with its linked User.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 */
class UserEmailUniqueness
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @return list<string>
     */
    public function collectAddresses(Entity $user): array
    {
        $out = [];
        $primary = $user->get('emailAddress');

        if (is_string($primary) && trim($primary) !== '') {
            $out[] = strtolower(trim($primary));
        }

        $data = $user->get('emailAddressData');

        if (is_array($data) || $data instanceof \stdClass) {
            foreach ((array) $data as $row) {
                $row = (array) $row;
                $address = $row['emailAddress'] ?? $row['lower'] ?? null;

                if (is_string($address) && trim($address) !== '') {
                    $out[] = strtolower(trim($address));
                }
            }
        }

        return array_values(array_unique($out));
    }

    public function anotherUserOwnsAddress(Entity $user, string $lower): bool
    {
        $lower = strtolower(trim($lower));

        if ($lower === '') {
            return false;
        }

        $emailAddress = $this->entityManager
            ->getRDBRepository(EmailAddress::ENTITY_TYPE)
            ->where(['lower' => $lower])
            ->findOne();

        if ($emailAddress === null) {
            return false;
        }

        $ignoreId = $user->hasId() ? $user->getId() : '';

        $items = $this->entityManager
            ->getRDBRepository(EmailAddress::RELATION_ENTITY_EMAIL_ADDRESS)
            ->where([
                'emailAddressId' => $emailAddress->getId(),
                'entityType' => User::ENTITY_TYPE,
            ])
            ->find();

        foreach ($items as $item) {
            $entityId = (string) ($item->get('entityId') ?? '');

            if ($entityId === '') {
                continue;
            }

            if ($ignoreId !== '' && $entityId === $ignoreId) {
                continue;
            }

            $existing = $this->entityManager
                ->getRDBRepository(User::ENTITY_TYPE)
                ->select([Attribute::ID])
                ->where([Attribute::ID => $entityId])
                ->findOne();

            if ($existing !== null) {
                return true;
            }
        }

        return false;
    }

    public function hasConflict(Entity $user): bool
    {
        foreach ($this->collectAddresses($user) as $lower) {
            if ($this->anotherUserOwnsAddress($user, $lower)) {
                return true;
            }
        }

        return false;
    }
}
