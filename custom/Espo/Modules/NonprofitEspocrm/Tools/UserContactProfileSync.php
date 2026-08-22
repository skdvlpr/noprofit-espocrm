<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;

/**
 * Sync User volunteering / member / employee profile fields ↔ linked Contact.
 * Contact is source of truth after save; User form fields are staging mirrors.
 */
class UserContactProfileSync
{
    public const ROLE_VOLUNTEER = 'Volunteer';
    public const ROLE_EMPLOYEE = 'Employee';
    public const ROLE_MEMBER = 'Member';

    /** @var list<string> */
    public const MEMBER_FIELDS = [
        'taxCode',
        'birthDate',
        'birthPlace',
        'birthProvince',
        'joinDate',
        'leaveDate',
        'positionsHeld',
        'memberNotes',
    ];

    /** @var list<string> */
    public const VOLUNTEER_FIELDS = [
        'isOccasional',
        'startDate',
        'endDate',
        'weeklyHours',
        'monthlyHours',
        'extra',
        'taxCode',
        'birthDate',
        'birthPlace',
        'birthProvince',
    ];

    /** User attribute → Contact attribute (when names differ). */
    private const FIELD_MAP = [
        'memberNotes' => 'notes',
    ];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @return array{hasVolunteerRole: bool, hasEmployeeRole: bool, hasMemberRole: bool}
     */
    public function resolveRoleFlags(Entity $user): array
    {
        $names = $this->roleNames($user);

        return [
            'hasVolunteerRole' => in_array(self::ROLE_VOLUNTEER, $names, true),
            'hasEmployeeRole' => in_array(self::ROLE_EMPLOYEE, $names, true),
            'hasMemberRole' => in_array(self::ROLE_MEMBER, $names, true),
        ];
    }

    /**
     * @return list<string>
     */
    public function roleNames(Entity $user): array
    {
        $namesMap = $user->get('rolesNames');

        if (is_array($namesMap) || $namesMap instanceof \stdClass) {
            $values = array_values((array) $namesMap);
            $out = [];

            foreach ($values as $name) {
                if (is_string($name) && $name !== '') {
                    $out[] = $name;
                }
            }

            if ($out !== []) {
                return array_values(array_unique($out));
            }
        }

        $ids = $user->getLinkMultipleIdList('roles');

        if ($ids === []) {
            return [];
        }

        $roles = $this->entityManager
            ->getRDBRepository('Role')
            ->where(['id' => $ids])
            ->find();

        $out = [];

        foreach ($roles as $role) {
            $name = (string) $role->get('name');

            if ($name !== '') {
                $out[] = $name;
            }
        }

        return $out;
    }

    public function applyRoleFlags(Entity $user): void
    {
        $flags = $this->resolveRoleFlags($user);
        $user->set('hasVolunteerRole', $flags['hasVolunteerRole']);
        $user->set('hasEmployeeRole', $flags['hasEmployeeRole']);
        $user->set('hasMemberRole', $flags['hasMemberRole']);
    }

    public function loadFromContact(Entity $user): void
    {
        $contact = $this->findPrimaryContact($user);

        if (!$contact) {
            return;
        }

        foreach (array_unique(array_merge(self::VOLUNTEER_FIELDS, self::MEMBER_FIELDS)) as $field) {
            $contactField = self::FIELD_MAP[$field] ?? $field;

            if (!$contact->hasAttribute($contactField) && !$contact->has($contactField)) {
                continue;
            }

            $user->set($field, $contact->get($contactField));
        }
    }

    public function syncFromUser(Entity $user): void
    {
        $flags = $this->resolveRoleFlags($user);
        $hasVolunteer = $flags['hasVolunteerRole'];
        $hasEmployee = $flags['hasEmployeeRole'];
        $hasMember = $flags['hasMemberRole'];

        if (!$hasVolunteer && !$hasEmployee && !$hasMember) {
            return;
        }

        $contacts = $this->findLinkedContacts($user);
        $found = false;

        foreach ($contacts as $contact) {
            $found = true;
            $this->writeProfileToContact($user, $contact, $hasVolunteer, $hasEmployee, $hasMember);
            $this->entityManager->saveEntity($contact, [
                SaveOption::SKIP_ALL => true,
            ]);
        }

        if ($found) {
            return;
        }

        $contact = $this->entityManager->getNewEntity('Contact');
        $contact->set([
            'firstName' => $user->get('firstName'),
            'lastName' => $user->get('lastName'),
            'personnelStatus' => 'Active',
            'linkedUserId' => $user->getId(),
            'assignedUserId' => $user->getId(),
            'contactType' => $this->resolveContactType($hasVolunteer, $hasEmployee, $hasMember),
        ]);

        $email = $user->get('emailAddress');
        if (is_string($email) && $email !== '') {
            $contact->set('emailAddress', $email);
        }

        $phone = $user->get('phoneNumber');
        if (is_string($phone) && $phone !== '') {
            $contact->set('phoneNumber', $phone);
        }

        $this->writeProfileToContact($user, $contact, $hasVolunteer, $hasEmployee, $hasMember);
        $this->entityManager->saveEntity($contact);
    }

    private function resolveContactType(bool $hasVolunteer, bool $hasEmployee, bool $hasMember): string
    {
        if ($hasVolunteer) {
            return 'Volunteer';
        }

        if ($hasEmployee) {
            return 'Employee';
        }

        if ($hasMember) {
            return 'MemberContact';
        }

        return 'Other';
    }

    private function writeProfileToContact(
        Entity $user,
        Entity $contact,
        bool $hasVolunteer,
        bool $hasEmployee,
        bool $hasMember
    ): void {
        $type = trim((string) ($contact->get('contactType') ?? ''));
        $desired = $this->resolveContactType($hasVolunteer, $hasEmployee, $hasMember);

        if ($type === '') {
            $contact->set('contactType', $desired);
            $type = $desired;
        } elseif ($hasVolunteer && in_array($type, ['MemberContact', 'Employee'], true)) {
            $contact->set('contactType', 'Volunteer');
            $type = 'Volunteer';
        } elseif ($hasEmployee && !$hasVolunteer && $type === 'MemberContact') {
            $contact->set('contactType', 'Employee');
            $type = 'Employee';
        } elseif ($hasMember && !$hasVolunteer && !$hasEmployee && $type !== 'MemberContact') {
            $contact->set('contactType', 'MemberContact');
            $type = 'MemberContact';
        }

        if ($hasVolunteer || $hasEmployee || in_array($type, ['Volunteer', 'Employee'], true)) {
            foreach (self::VOLUNTEER_FIELDS as $field) {
                if (!$user->has($field)) {
                    continue;
                }

                // Occasional flag is Volunteer-only.
                if ($field === 'isOccasional' && !$hasVolunteer && $type !== 'Volunteer') {
                    continue;
                }

                $contactField = self::FIELD_MAP[$field] ?? $field;
                $value = $user->get($field);

                if ($field === 'monthlyHours' && $user->get('weeklyHours') !== null && $user->get('weeklyHours') !== '') {
                    $value = round((float) $user->get('weeklyHours') * 4.33, 1);
                }

                $this->setContactField($contact, $contactField, $value);
            }
        }

        if ($hasMember || $type === 'MemberContact') {
            foreach (self::MEMBER_FIELDS as $field) {
                if (!$user->has($field)) {
                    continue;
                }

                $contactField = self::FIELD_MAP[$field] ?? $field;
                $this->setContactField($contact, $contactField, $user->get($field));
            }
        }
    }

    private function setContactField(Entity $contact, string $contactField, mixed $value): void
    {
        if ($contactField === 'taxCode') {
            $value = ItalianTaxCodeNormalizer::normalize($value);
        }

        $contact->set($contactField, $value);
    }

    /**
     * @return iterable<Entity>
     */
    private function findLinkedContacts(Entity $user): iterable
    {
        return $this->entityManager
            ->getRDBRepository('Contact')
            ->where(['linkedUserId' => $user->getId()])
            ->find();
    }

    private function findPrimaryContact(Entity $user): ?Entity
    {
        foreach ($this->findLinkedContacts($user) as $contact) {
            return $contact;
        }

        return null;
    }
}
