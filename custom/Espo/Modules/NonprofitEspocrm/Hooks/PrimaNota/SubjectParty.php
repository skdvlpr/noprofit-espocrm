<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Name\Field;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Contact;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Syncs payment-subject and beneficiary names from linkParent fields
 * and optionally creates Account/Contact records.
 */
class SubjectParty implements BeforeSave
{
    public static int $order = 4;

    /** @var list<string> */
    private const PARTY_PREFIXES = ['subject', 'beneficiary'];

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        foreach (self::PARTY_PREFIXES as $prefix) {
            $this->processPartyFields($entity, $prefix);
        }
    }

    private function processPartyFields(Entity $entity, string $prefix): void
    {
        $label = $prefix === 'subject' ? 'payment subject' : 'beneficiary';

        $createAccount = (bool) $entity->get('create' . ucfirst($prefix) . 'Account');
        $createContact = (bool) $entity->get('create' . ucfirst($prefix) . 'Contact');

        if ($createAccount && $createContact) {
            throw new BadRequest("Select either create Account or create Contact for {$label}, not both.");
        }

        $partyId = $entity->get($prefix . 'PartyId');
        $partyType = $entity->get($prefix . 'PartyType');
        $partyName = trim((string) ($entity->get($prefix . 'Name') ?? ''));

        if ($partyId && ($createAccount || $createContact)) {
            throw new BadRequest("Clear create flags when a linked {$label} is selected.");
        }

        if (!$partyId && $partyName !== '' && ($createAccount || $createContact)) {
            if ($createAccount) {
                $this->createAndLinkAccount($entity, $prefix, $partyName);
            } else {
                $this->createAndLinkContact($entity, $prefix, $partyName);
            }

            $entity->set('create' . ucfirst($prefix) . 'Account', false);
            $entity->set('create' . ucfirst($prefix) . 'Contact', false);

            $partyId = $entity->get($prefix . 'PartyId');
            $partyType = $entity->get($prefix . 'PartyType');
        }

        if ($partyId && $partyType) {
            $displayName = $this->resolveDisplayName((string) $partyType, (string) $partyId);

            if ($displayName !== null) {
                $entity->set($prefix . 'Name', $displayName);
                $entity->set($prefix . 'PartyName', $displayName);
            }
        } elseif ($createAccount || $createContact) {
            throw new BadRequest("Enter {$label} name before creating a linked record.");
        }
    }

    private function createAndLinkAccount(Entity $entity, string $prefix, string $partyName): void
    {
        $account = $this->entityManager->getNewEntity(Account::ENTITY_TYPE);
        $account->set(Field::NAME, $partyName);
        $this->copyAssignment($entity, $account);

        $this->entityManager->saveEntity($account, [
            SaveOption::SKIP_ALL => true,
        ]);

        $entity->set($prefix . 'PartyId', $account->getId());
        $entity->set($prefix . 'PartyType', Account::ENTITY_TYPE);
        $entity->set($prefix . 'PartyName', $account->get(Field::NAME));
    }

    private function createAndLinkContact(Entity $entity, string $prefix, string $partyName): void
    {
        [$firstName, $lastName] = $this->splitPersonName($partyName);

        $contact = $this->entityManager->getNewEntity(Contact::ENTITY_TYPE);
        $contact->set('firstName', $firstName);
        $contact->set('lastName', $lastName);
        $this->copyAssignment($entity, $contact);

        $this->entityManager->saveEntity($contact, [
            SaveOption::SKIP_ALL => true,
        ]);

        $entity->set($prefix . 'PartyId', $contact->getId());
        $entity->set($prefix . 'PartyType', Contact::ENTITY_TYPE);
        $entity->set($prefix . 'PartyName', $contact->get(Field::NAME));
    }

    private function copyAssignment(Entity $source, Entity $target): void
    {
        if ($source->get('assignedUserId')) {
            $target->set('assignedUserId', $source->get('assignedUserId'));
        }

        if ($source->get('teamsIds')) {
            $target->set('teamsIds', $source->get('teamsIds'));
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitPersonName(string $partyName): array
    {
        $partyName = trim(preg_replace('/\s+/u', ' ', $partyName) ?? $partyName);

        if ($partyName === '') {
            return ['', ''];
        }

        $parts = explode(' ', $partyName, 2);

        return [
            $parts[0],
            $parts[1] ?? '',
        ];
    }

    private function resolveDisplayName(string $entityType, string $id): ?string
    {
        if ($entityType === Account::ENTITY_TYPE) {
            $account = $this->entityManager
                ->getRDBRepository(Account::ENTITY_TYPE)
                ->select([Attribute::ID, Field::NAME])
                ->where([Attribute::ID => $id])
                ->findOne();

            return $account?->get(Field::NAME);
        }

        if ($entityType === Contact::ENTITY_TYPE) {
            $contact = $this->entityManager
                ->getRDBRepository(Contact::ENTITY_TYPE)
                ->select([Attribute::ID, 'firstName', 'lastName', Field::NAME])
                ->where([Attribute::ID => $id])
                ->findOne();

            if (!$contact) {
                return null;
            }

            $firstName = trim((string) ($contact->get('firstName') ?? ''));
            $lastName = trim((string) ($contact->get('lastName') ?? ''));

            if ($firstName !== '' || $lastName !== '') {
                return trim($firstName . ' ' . $lastName);
            }

            return $contact->get(Field::NAME);
        }

        return null;
    }
}
