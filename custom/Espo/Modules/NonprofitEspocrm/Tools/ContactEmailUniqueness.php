<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Entities\EmailAddress;
use Espo\Modules\Crm\Entities\Contact;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;

/**
 * One email per Contact. The same address MAY be on that Contact's linked User.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 */
class ContactEmailUniqueness
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @return list<string>
     */
    public function collectAddresses(Entity $contact): array
    {
        $out = [];
        $primary = $contact->get('emailAddress');

        if (is_string($primary) && trim($primary) !== '') {
            $out[] = strtolower(trim($primary));
        }

        $data = $contact->get('emailAddressData');

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

    public function anotherContactOwnsAddress(Entity $contact, string $lower): bool
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

        $ignoreId = $contact->hasId() ? $contact->getId() : '';

        $items = $this->entityManager
            ->getRDBRepository(EmailAddress::RELATION_ENTITY_EMAIL_ADDRESS)
            ->where([
                'emailAddressId' => $emailAddress->getId(),
                'entityType' => Contact::ENTITY_TYPE,
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
                ->getRDBRepository(Contact::ENTITY_TYPE)
                ->select([Attribute::ID])
                ->where([Attribute::ID => $entityId])
                ->findOne();

            if ($existing !== null) {
                return true;
            }
        }

        return false;
    }

    public function hasConflict(Entity $contact): bool
    {
        foreach ($this->collectAddresses($contact) as $lower) {
            if ($this->anotherContactOwnsAddress($contact, $lower)) {
                return true;
            }
        }

        return false;
    }
}
