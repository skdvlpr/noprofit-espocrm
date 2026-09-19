<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Core\Exceptions\Conflict;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;

/**
 * Load User volunteering / member / employee fields from linked Contact.
 * Contact is the stored source of truth; User fields are a read-only reflection.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
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
        'activityCompetences',
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
            $contactField = self::mapContactField($field);

            if (!$contact->hasAttribute($contactField) && !$contact->has($contactField)) {
                continue;
            }

            $user->set($field, $contact->get($contactField));
        }

        $user->set('linkedContactId', $contact->getId());
        $name = trim((string) ($contact->get('name') ?? ''));

        if ($name === '') {
            $name = trim(
                trim((string) ($contact->get('firstName') ?? '')) . ' ' .
                trim((string) ($contact->get('lastName') ?? ''))
            );
        }

        if ($name !== '') {
            $user->set('linkedContactName', $name);
        }
    }

    public function syncFromUser(Entity $user): void
    {
        $flags = $this->resolveRoleFlags($user);
        $hasVolunteer = $flags['hasVolunteerRole'];
        $hasEmployee = $flags['hasEmployeeRole'];
        $hasMember = $flags['hasMemberRole'];

        if ($this->linkFromSourceContact($user)) {
            return;
        }

        if (!$hasVolunteer && !$hasEmployee && !$hasMember) {
            return;
        }

        $contacts = $this->findLinkedContacts($user);
        $found = false;

        foreach ($contacts as $contact) {
            $found = true;
            $this->writeProfileToContact($contact, $hasVolunteer, $hasEmployee, $hasMember);
            $this->entityManager->saveEntity($contact, [
                SaveOption::SKIP_ALL => true,
            ]);
        }

        if ($found) {
            return;
        }

        if (!self::mayAutoCreateContact($hasVolunteer, $hasEmployee, $hasMember)) {
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

        $this->writeProfileToContact($contact, $hasVolunteer, $hasEmployee, $hasMember);
        $this->entityManager->saveEntity($contact);
    }

    /**
     * Volunteer/Employee person records start on Contact. Member User-first
     * Contact create may remain until a later spec.
     */
    public static function mayAutoCreateContact(
        bool $hasVolunteer,
        bool $hasEmployee,
        bool $hasMember
    ): bool {
        if ($hasVolunteer || $hasEmployee) {
            return false;
        }

        return $hasMember;
    }

    /**
     * Link an existing Contact after Contact-first User create.
     * MUST NOT run on update (sourceContactId is a create handshake).
     * MUST NOT steal a Contact already linked to another User.
     * MUST NOT create a Contact and MUST NOT steal Assigned User.
     *
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
     */
    public function linkFromSourceContact(Entity $user): bool
    {
        // afterSave still sees isNew(); setAsNotNew runs after afterSave.
        if (!$user->isNew()) {
            return false;
        }

        $sourceId = trim((string) ($user->get('sourceContactId') ?? ''));

        if ($sourceId === '') {
            return false;
        }

        $contact = $this->entityManager->getEntityById('Contact', $sourceId);

        if (!$contact) {
            return false;
        }

        $existing = trim((string) ($contact->get('linkedUserId') ?? ''));
        $userId = (string) $user->getId();

        if ($existing !== '' && $existing !== $userId) {
            throw new Conflict('This contact is already linked to another CRM user.');
        }

        $contact->set('linkedUserId', $user->getId());
        $this->entityManager->saveEntity($contact, [
            SaveOption::SKIP_ALL => true,
        ]);

        return true;
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

    /**
     * Contact type may follow User roles. Personnel profile fields stay on
     * Contact; User is a read-only reflection (ContactProfileLoader).
     */
    private function writeProfileToContact(
        Entity $contact,
        bool $hasVolunteer,
        bool $hasEmployee,
        bool $hasMember
    ): void {
        $type = trim((string) ($contact->get('contactType') ?? ''));
        $desired = $this->resolveContactType($hasVolunteer, $hasEmployee, $hasMember);

        if ($type === '') {
            $contact->set('contactType', $desired);
        } elseif ($hasVolunteer && in_array($type, ['MemberContact', 'Employee'], true)) {
            $contact->set('contactType', 'Volunteer');
        } elseif ($hasEmployee && !$hasVolunteer && $type === 'MemberContact') {
            $contact->set('contactType', 'Employee');
        } elseif ($hasMember && !$hasVolunteer && !$hasEmployee && $type !== 'MemberContact') {
            $contact->set('contactType', 'MemberContact');
        }
    }

    private static function mapContactField(string $userField): string
    {
        return $userField === 'memberNotes' ? 'notes' : $userField;
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
