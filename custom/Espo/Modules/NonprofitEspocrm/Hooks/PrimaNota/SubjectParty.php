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
 * Syncs subjectName from linkParent subjectParty and optionally creates Account/Contact.
 */
class SubjectParty implements BeforeSave
{
    public static int $order = 4;

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        $createAccount = (bool) $entity->get('createSubjectAccount');
        $createContact = (bool) $entity->get('createSubjectContact');

        if ($createAccount && $createContact) {
            throw new BadRequest('Select either create Account or create Contact, not both.');
        }

        $subjectPartyId = $entity->get('subjectPartyId');
        $subjectPartyType = $entity->get('subjectPartyType');
        $subjectName = trim((string) ($entity->get('subjectName') ?? ''));

        if ($subjectPartyId && ($createAccount || $createContact)) {
            throw new BadRequest('Clear create flags when a linked subject is selected.');
        }

        if (!$subjectPartyId && $subjectName !== '' && ($createAccount || $createContact)) {
            if ($createAccount) {
                $this->createAndLinkAccount($entity, $subjectName);
            } else {
                $this->createAndLinkContact($entity, $subjectName);
            }

            $entity->set('createSubjectAccount', false);
            $entity->set('createSubjectContact', false);

            $subjectPartyId = $entity->get('subjectPartyId');
            $subjectPartyType = $entity->get('subjectPartyType');
        }

        if ($subjectPartyId && $subjectPartyType) {
            $displayName = $this->resolveDisplayName($subjectPartyType, (string) $subjectPartyId);

            if ($displayName !== null) {
                $entity->set('subjectName', $displayName);
                $entity->set('subjectPartyName', $displayName);
            }
        } elseif ($createAccount || $createContact) {
            throw new BadRequest('Enter payer/beneficiary name before creating a linked record.');
        }
    }

    private function createAndLinkAccount(Entity $entity, string $subjectName): void
    {
        $account = $this->entityManager->getNewEntity(Account::ENTITY_TYPE);
        $account->set(Field::NAME, $subjectName);
        $this->copyAssignment($entity, $account);

        $this->entityManager->saveEntity($account, [
            SaveOption::SKIP_ALL => true,
        ]);

        $entity->set('subjectPartyId', $account->getId());
        $entity->set('subjectPartyType', Account::ENTITY_TYPE);
        $entity->set('subjectPartyName', $account->get(Field::NAME));
    }

    private function createAndLinkContact(Entity $entity, string $subjectName): void
    {
        [$firstName, $lastName] = $this->splitPersonName($subjectName);

        $contact = $this->entityManager->getNewEntity(Contact::ENTITY_TYPE);
        $contact->set('firstName', $firstName);
        $contact->set('lastName', $lastName);
        $this->copyAssignment($entity, $contact);

        $this->entityManager->saveEntity($contact, [
            SaveOption::SKIP_ALL => true,
        ]);

        $entity->set('subjectPartyId', $contact->getId());
        $entity->set('subjectPartyType', Contact::ENTITY_TYPE);
        $entity->set('subjectPartyName', $contact->get(Field::NAME));
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
    private function splitPersonName(string $subjectName): array
    {
        $subjectName = trim(preg_replace('/\s+/u', ' ', $subjectName) ?? $subjectName);

        if ($subjectName === '') {
            return ['', ''];
        }

        $parts = explode(' ', $subjectName, 2);

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
