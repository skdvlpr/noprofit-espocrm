<?php

namespace Espo\Modules\SafehouseCrm\Tools;

use Espo\Core\Utils\PasswordHash;
use Espo\Entities\Role;
use Espo\Entities\Team;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/**
 * Reusable role / test-user provisioning for the SafehouseCrm module.
 *
 * Used from:
 *   - custom/Espo/Modules/SafehouseCrm/AfterInstall.php (fresh extension install)
 *   - bin/setup-roles.php (one-shot maintenance for an existing dev instance)
 *
 * All public methods are idempotent: re-running them updates existing records
 * in place rather than creating duplicates.
 *
 * Permission matrix (Task 2.1):
 *   - Admin     : full all / not used by the bootstrap admin user (which has
 *                 isAdmin=true and bypasses roles), but available for
 *                 secondary super-users.
 *   - Employee  : create=yes, read=all, edit=team, delete=own, stream=yes;
 *                 VolunteerEmployee / Member: read only, no create/edit/delete
 *                 (personnel changes go to Manager).
 *   - Manager   : same operational envelope as Employee, plus create/edit
 *                 team-level on VolunteerEmployee and Member; delete=no
 *                 on those entities (lifecycle via status / dates).
 *   - Volunteer : read-mostly; grants/funding (Espo entity type `Opportunity`)
 *                 fully blocked; MealCount.foodCost and MealCount.foodUnitPrice
 *                 hidden via field-level ACL.
 *   - Member    : read/edit own only; cannot create or delete.
 */
class RoleSetup
{
    public const ROLE_ADMIN     = 'Admin';
    public const ROLE_EMPLOYEE  = 'Employee';
    /** HR / personnel: create & edit VolunteerEmployee / Member (no hard delete on those). */
    public const ROLE_MANAGER   = 'Manager';
    public const ROLE_VOLUNTEER = 'Volunteer';
    public const ROLE_MEMBER    = 'Member';

    public const TEAM_ADMINISTRATION = 'Administration';

    /**
     * Map team-name => spec.
     * - `roles`: list of role names whose members should inherit access via this team
     *   (Espo links roles to teams; the team's effective role set is the union of these)
     *
     * @var array<string, array{description: string, roles: string[]}>
     */
    public const TEAMS = [
        self::TEAM_ADMINISTRATION => [
            'description' => 'Safehouse administrative personnel: shared visibility of administrative records.',
            'roles'       => [self::ROLE_EMPLOYEE, self::ROLE_MANAGER],
        ],
    ];

    /**
     * Map test userName => list of team names they should belong to.
     *
     * @var array<string, string[]>
     */
    public const TEAM_MEMBERSHIPS = [
        'test_dipendente' => [self::TEAM_ADMINISTRATION],
        'test_manager'    => [self::TEAM_ADMINISTRATION],
    ];

    /**
     * Test users created when {@see provisionTestUsers()} is called.
     * Password is the same for all of them for convenience in dev.
     *
     * Note: the test user names are kept as-is for continuity with existing
     * fixtures and credentials documented elsewhere; only the linked role
     * constants moved to English.
     *
     * @var array<string, array{role: string, firstName: string, lastName: string}>
     */
    public const TEST_USERS = [
        'test_dipendente' => [
            'role'      => self::ROLE_EMPLOYEE,
            'firstName' => 'Test',
            'lastName'  => 'Employee',
        ],
        'test_manager' => [
            'role'      => self::ROLE_MANAGER,
            'firstName' => 'Test',
            'lastName'  => 'Manager',
        ],
        'test_volontario' => [
            'role'      => self::ROLE_VOLUNTEER,
            'firstName' => 'Test',
            'lastName'  => 'Volunteer',
        ],
        'test_associato' => [
            'role'      => self::ROLE_MEMBER,
            'firstName' => 'Test',
            'lastName'  => 'Member',
        ],
    ];

    public const TEST_PASSWORD = 'Test1234!';

    public function __construct(
        private EntityManager $entityManager,
        private PasswordHash $passwordHash
    ) {}

    /**
     * Create the canonical teams (currently just `Administration`) and ensure
     * each team is linked to its specified default roles. Idempotent.
     *
     * @return array<string, string> map team-name => 'created' | 'updated' | 'unchanged'
     */
    public function provisionTeams(): array
    {
        $em = $this->entityManager;
        $report = [];

        foreach (self::TEAMS as $name => $spec) {
            /** @var ?Team $team */
            $team = $em->getRDBRepositoryByClass(Team::class)
                ->where(['name' => $name])
                ->findOne();

            $isNew = $team === null;
            $changed = false;

            if ($isNew) {
                $team = $em->getRDBRepositoryByClass(Team::class)->getNew();
                $team->set([
                    'name'        => $name,
                    'description' => $spec['description'],
                ]);
                $em->saveEntity($team);
                $changed = true;
            } elseif ($team->get('description') !== $spec['description']) {
                $team->set('description', $spec['description']);
                $em->saveEntity($team);
                $changed = true;
            }

            $rolesRelation = $em->getRDBRepository('Team')->getRelation($team, 'roles');
            foreach ($spec['roles'] as $roleName) {
                /** @var ?Role $role */
                $role = $em->getRDBRepositoryByClass(Role::class)
                    ->where(['name' => $roleName])
                    ->findOne();
                if (!$role) {
                    continue;
                }
                if (!$rolesRelation->isRelated($role)) {
                    $rolesRelation->relate($role);
                    $changed = true;
                }
            }

            if ($isNew) {
                $report[$name] = 'created';
            } elseif ($changed) {
                $report[$name] = 'updated';
            } else {
                $report[$name] = 'unchanged';
            }
        }

        return $report;
    }

    /**
     * Add test users to the teams declared in {@see TEAM_MEMBERSHIPS}.
     * Idempotent.
     *
     * @return array<string, string>
     */
    public function provisionTeamMemberships(): array
    {
        $em = $this->entityManager;
        $report = [];

        foreach (self::TEAM_MEMBERSHIPS as $userName => $teams) {
            /** @var ?User $user */
            $user = $em->getRDBRepositoryByClass(User::class)
                ->where(['userName' => $userName])
                ->findOne();
            if (!$user) {
                $report[$userName] = 'skipped (user not found)';
                continue;
            }

            $userTeams = $em->getRDBRepository('User')->getRelation($user, 'teams');
            $added = [];
            foreach ($teams as $teamName) {
                /** @var ?Team $team */
                $team = $em->getRDBRepositoryByClass(Team::class)
                    ->where(['name' => $teamName])
                    ->findOne();
                if (!$team) {
                    continue;
                }
                if (!$userTeams->isRelated($team)) {
                    $userTeams->relate($team);
                    $added[] = $teamName;
                }
            }

            $report[$userName] = $added === []
                ? 'unchanged'
                : 'added to: ' . implode(', ', $added);
        }

        return $report;
    }

    /**
     * Create or update all canonical roles (Admin, Employee, Manager, Volunteer, Member).
     *
     * @return array<string, string> map role-name => 'created' | 'updated' | 'unchanged'
     */
    public function provisionRoles(): array
    {
        $report = [];

        foreach ($this->roleSpecs() as $name => $spec) {
            $report[$name] = $this->upsertRole($name, $spec['data'], $spec['fieldData'], $spec['perms']);
        }

        return $report;
    }

    /**
     * Runs {@see provisionTestUsers()}, {@see provisionTeamMemberships()}, then
     * {@see provisionTestProfiles()} in a fixed order so profile creation never
     * runs against missing test users.
     *
     * @return array{
     *     users: array<string, string>,
     *     teamMemberships: array<string, string>,
     *     profiles: array<string, string>
     * }
     */
    public function provisionTestUsersTeamsAndProfiles(): array
    {
        return [
            'users'             => $this->provisionTestUsers(),
            'teamMemberships'   => $this->provisionTeamMemberships(),
            'profiles'          => $this->provisionTestProfiles(),
        ];
    }

    /**
     * For each test user, ensure there is a linked domain record assigned to
     * them so the `read=own` ACL has at least one row to return.
     *
     *   - test_volontario  -> VolunteerEmployee (type=Volunteer)
     *   - test_dipendente  -> VolunteerEmployee (type=Employee)
     *   - test_associato   -> Member
     *
     * Idempotent: skipped if a record already exists with the same
     * `assignedUserId` **or** `userId` (unique per linked User on personnel entities).
     *
     * Must run after {@see provisionTestUsers()} (use {@see provisionTestUsersTeamsAndProfiles()}
     * from scripts so order is guaranteed).
     *
     * @return array<string, string>
     */
    public function provisionTestProfiles(): array
    {
        $em = $this->entityManager;
        $report = [];

        $profiles = [
            'test_volontario' => [
                'entityType' => 'VolunteerEmployee',
                'attributes' => [
                    'type'        => 'Volunteer',
                    'firstName'   => 'Test',
                    'lastName'    => 'Volunteer',
                    'weeklyHours' => 8,
                ],
            ],
            'test_dipendente' => [
                'entityType' => 'VolunteerEmployee',
                'attributes' => [
                    'type'         => 'Employee',
                    'firstName'    => 'Test',
                    'lastName'     => 'Employee',
                    'contractType' => 'FixedTerm',
                    'weeklyHours'  => 40,
                ],
            ],
            'test_manager' => [
                'entityType' => 'VolunteerEmployee',
                'attributes' => [
                    'type'         => 'Employee',
                    'firstName'    => 'Test',
                    'lastName'     => 'Manager',
                    'contractType' => 'Permanent',
                    'weeklyHours'  => 40,
                ],
            ],
            'test_associato' => [
                'entityType' => 'Member',
                'attributes' => [
                    'firstName' => 'Test',
                    'lastName'  => 'Member',
                    'status'    => 'Active',
                ],
            ],
        ];

        foreach ($profiles as $userName => $spec) {
            /** @var ?User $user */
            $user = $em->getRDBRepositoryByClass(User::class)
                ->where(['userName' => $userName])
                ->findOne();

            if (!$user) {
                $report[$userName] = 'skipped (user not found)';
                continue;
            }

            $existing = $em->getRDBRepository($spec['entityType'])
                ->where([
                    'OR' => [
                        ['assignedUserId' => $user->getId()],
                        ['userId' => $user->getId()],
                    ],
                ])
                ->findOne();

            if ($existing) {
                $linked = $this->ensureTeamMembership($existing, $userName);
                $report[$userName] = $linked
                    ? 'updated (team linked)'
                    : 'unchanged';
                continue;
            }

            $entity = $em->getRDBRepository($spec['entityType'])->getNew();
            $payload = array_merge($spec['attributes'], [
                'assignedUserId' => $user->getId(),
                'userId'         => $user->getId(),
            ]);
            $email = $user->get('emailAddress');
            if (!is_string($email) || trim($email) === '') {
                $email = $userName . '@example.com';
            } else {
                $email = trim($email);
            }
            if (in_array($spec['entityType'], ['VolunteerEmployee', 'Member'], true)) {
                $payload['emailAddress'] = $email;
            }
            $entity->set($payload);
            $em->saveEntity($entity);

            $this->ensureTeamMembership($entity, $userName);

            $report[$userName] = sprintf('created (%s id=%s)', $spec['entityType'], $entity->getId());
        }

        return $report;
    }

    /**
     * Create or update test users and link them to their roles.
     *
     * @return array<string, string> map userName => 'created' | 'updated' | 'unchanged'
     */
    public function provisionTestUsers(): array
    {
        $report = [];
        $em = $this->entityManager;

        foreach (self::TEST_USERS as $userName => $spec) {
            /** @var ?User $user */
            $user = $em->getRDBRepositoryByClass(User::class)
                ->where(['userName' => $userName])
                ->findOne();

            $isNew = $user === null;
            if ($isNew) {
                $user = $em->getRDBRepositoryByClass(User::class)->getNew();
                $user->set([
                    'userName'     => $userName,
                    'firstName'    => $spec['firstName'],
                    'lastName'     => $spec['lastName'],
                    'type'         => User::TYPE_REGULAR,
                    'isActive'     => true,
                    'password'     => $this->passwordHash->hash(self::TEST_PASSWORD),
                    'emailAddress' => $userName . '@example.com',
                ]);
                $em->saveEntity($user);
            }

            /** @var ?Role $role */
            $role = $em->getRDBRepositoryByClass(Role::class)
                ->where(['name' => $spec['role']])
                ->findOne();

            if (!$role) {
                $report[$userName] = $isNew ? 'created (role missing)' : 'unchanged (role missing)';
                continue;
            }

            $linked = $em->getRDBRepository('User')
                ->getRelation($user, 'roles')
                ->isRelated($role);

            if (!$linked) {
                $em->getRDBRepository('User')
                    ->getRelation($user, 'roles')
                    ->relate($role);
                $report[$userName] = $isNew ? 'created' : 'updated (role linked)';
                continue;
            }

            $expectedEmail = $userName . '@example.com';
            if ($user->get('emailAddress') !== $expectedEmail) {
                $user->set('emailAddress', $expectedEmail);
                $em->saveEntity($user);
                $report[$userName] = $isNew ? 'created' : 'updated (email)';
                continue;
            }

            $report[$userName] = $isNew ? 'created' : 'unchanged';
        }

        return $report;
    }

    /**
     * Link the given domain entity (VolunteerEmployee / Member) to every team
     * the corresponding test user is a member of, via the entity's `teams`
     * linkMultiple field. Idempotent.
     */
    private function ensureTeamMembership(\Espo\ORM\Entity $entity, string $userName): bool
    {
        $teams = self::TEAM_MEMBERSHIPS[$userName] ?? [];
        if ($teams === []) {
            return false;
        }

        $em = $this->entityManager;
        $relation = $em->getRDBRepository($entity->getEntityType())->getRelation($entity, 'teams');

        $changed = false;
        foreach ($teams as $teamName) {
            /** @var ?Team $team */
            $team = $em->getRDBRepositoryByClass(Team::class)
                ->where(['name' => $teamName])
                ->findOne();
            if (!$team) {
                continue;
            }
            if (!$relation->isRelated($team)) {
                $relation->relate($team);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * Build role specs.
     *
     * @return array<string, array{
     *     data: array<string, array<string, string>|bool>,
     *     fieldData: array<string, array<string, array<string, string>>>,
     *     perms: array<string, string>
     * }>
     */
    private function roleSpecs(): array
    {
        $allFull = static fn(): array => [
            'create' => 'yes', 'read' => 'all', 'edit' => 'all', 'delete' => 'all', 'stream' => 'all',
        ];

        $readOnlyAll = static fn(): array => [
            'create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no', 'stream' => 'all',
        ];

        $blocked = static fn(): array => [
            'create' => 'no', 'read' => 'no', 'edit' => 'no', 'delete' => 'no', 'stream' => 'no',
        ];

        $teamCreateOwnDelete = static fn(): array => [
            'create' => 'yes', 'read' => 'all', 'edit' => 'team', 'delete' => 'own', 'stream' => 'all',
        ];

        $domainEntities = [
            'Account', 'AccountWebsite', 'Contact', 'Opportunity',
            'VolunteerEmployee', 'Member', 'MealCount', 'Document',
            'Meeting', 'Call', 'Task', 'Email',
        ];

        $adminData = [];
        foreach ($domainEntities as $e) {
            $adminData[$e] = $allFull();
        }

        $employeeData = [];
        foreach ($domainEntities as $e) {
            $employeeData[$e] = $teamCreateOwnDelete();
        }
        $employeeData['MealCount'] = [
            'create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'own', 'stream' => 'own',
        ];
        // Personnel: ordinary Employee sees directory but does not create/edit/delete rows.
        $employeeData['VolunteerEmployee'] = [
            'create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no', 'stream' => 'all',
        ];
        $employeeData['Member'] = [
            'create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no', 'stream' => 'all',
        ];

        /** @var array<string, array<string, string>> $managerData */
        $managerData = json_decode(json_encode($employeeData), true);
        // Manager (HR): maintain personnel on team scope; no hard delete.
        $managerData['VolunteerEmployee'] = [
            'create' => 'yes', 'read' => 'all', 'edit' => 'team', 'delete' => 'no', 'stream' => 'all',
        ];
        $managerData['Member'] = [
            'create' => 'yes', 'read' => 'all', 'edit' => 'team', 'delete' => 'no', 'stream' => 'all',
        ];

        $volunteerData = [];
        foreach ($domainEntities as $e) {
            $volunteerData[$e] = $readOnlyAll();
        }
        // Grants & Funding entity type is still `Opportunity` (Espo core).
        $volunteerData['Opportunity'] = $blocked();
        $volunteerData['Member'] = $blocked();
        $volunteerData['VolunteerEmployee'] = [
            'create' => 'no', 'read' => 'own', 'edit' => 'own', 'delete' => 'no', 'stream' => 'own',
        ];
        $volunteerData['MealCount'] = [
            'create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'no', 'stream' => 'own',
        ];
        $volunteerData['Account'] = $readOnlyAll();
        $volunteerData['Contact'] = $readOnlyAll();
        $volunteerData['Document'] = [
            'create' => 'no', 'read' => 'team', 'edit' => 'own', 'delete' => 'no', 'stream' => 'team',
        ];

        $personContactFieldLocks = [
            'User' => [
                'emailAddress' => ['read' => 'yes', 'edit' => 'no'],
                'phoneNumber'  => ['read' => 'yes', 'edit' => 'no'],
            ],
            'VolunteerEmployee' => [
                'emailAddress' => ['read' => 'yes', 'edit' => 'no'],
                'phoneNumber'  => ['read' => 'yes', 'edit' => 'no'],
            ],
            'Member' => [
                'emailAddress' => ['read' => 'yes', 'edit' => 'no'],
                'phoneNumber'  => ['read' => 'yes', 'edit' => 'no'],
            ],
        ];

        $volunteerFieldData = array_merge($personContactFieldLocks, [
            'MealCount' => [
                'foodCost'      => ['read' => 'no', 'edit' => 'no'],
                'foodUnitPrice' => ['read' => 'no', 'edit' => 'no'],
            ],
        ]);

        $memberData = [];
        foreach ($domainEntities as $e) {
            $memberData[$e] = $blocked();
        }
        $memberData['Account']  = ['create' => 'no', 'read' => 'own', 'edit' => 'own', 'delete' => 'no', 'stream' => 'own'];
        $memberData['Contact']  = ['create' => 'no', 'read' => 'own', 'edit' => 'own', 'delete' => 'no', 'stream' => 'own'];
        $memberData['Document'] = ['create' => 'no', 'read' => 'own', 'edit' => 'no',  'delete' => 'no', 'stream' => 'own'];
        $memberData['Member']   = ['create' => 'no', 'read' => 'own', 'edit' => 'own', 'delete' => 'no', 'stream' => 'own'];

        return [
            self::ROLE_ADMIN => [
                'data'      => $adminData,
                'fieldData' => [],
                'perms'     => [
                    'assignmentPermission'        => 'all',
                    'userPermission'              => 'all',
                    'messagePermission'           => 'all',
                    'exportPermission'            => 'yes',
                    'massUpdatePermission'        => 'yes',
                    'auditPermission'             => 'yes',
                    'mentionPermission'           => 'yes',
                    'userCalendarPermission'      => 'all',
                    'followerManagementPermission'=> 'all',
                ],
            ],
            self::ROLE_EMPLOYEE => [
                'data'      => $employeeData,
                'fieldData' => $personContactFieldLocks,
                'perms'     => [
                    'assignmentPermission'        => 'team',
                    'userPermission'              => 'team',
                    'messagePermission'           => 'team',
                    'exportPermission'            => 'yes',
                    'massUpdatePermission'        => 'no',
                    'auditPermission'             => 'no',
                    'mentionPermission'           => 'all',
                    'userCalendarPermission'      => 'team',
                    'followerManagementPermission'=> 'team',
                ],
            ],
            self::ROLE_MANAGER => [
                'data'      => $managerData,
                'fieldData' => $personContactFieldLocks,
                'perms'     => [
                    'assignmentPermission'        => 'team',
                    'userPermission'              => 'team',
                    'messagePermission'           => 'team',
                    'exportPermission'            => 'yes',
                    'massUpdatePermission'        => 'no',
                    'auditPermission'             => 'no',
                    'mentionPermission'           => 'all',
                    'userCalendarPermission'      => 'team',
                    'followerManagementPermission'=> 'team',
                ],
            ],
            self::ROLE_VOLUNTEER => [
                'data'      => $volunteerData,
                'fieldData' => $volunteerFieldData,
                'perms'     => [
                    'assignmentPermission'        => 'no',
                    'userPermission'              => 'team',
                    'messagePermission'           => 'team',
                    'exportPermission'            => 'no',
                    'massUpdatePermission'        => 'no',
                    'auditPermission'             => 'no',
                    'mentionPermission'           => 'team',
                    'userCalendarPermission'      => 'team',
                    'followerManagementPermission'=> 'no',
                ],
            ],
            self::ROLE_MEMBER => [
                'data'      => $memberData,
                'fieldData' => $personContactFieldLocks,
                'perms'     => [
                    'assignmentPermission'        => 'no',
                    'userPermission'              => 'no',
                    'messagePermission'           => 'no',
                    'exportPermission'            => 'no',
                    'massUpdatePermission'        => 'no',
                    'auditPermission'             => 'no',
                    'mentionPermission'           => 'no',
                    'userCalendarPermission'      => 'no',
                    'followerManagementPermission'=> 'no',
                ],
            ],
        ];
    }

    /**
     * @param array<string, array<string, string>|bool> $data
     * @param array<string, array<string, array<string, string>>> $fieldData
     * @param array<string, string> $perms
     */
    private function upsertRole(string $name, array $data, array $fieldData, array $perms): string
    {
        $em = $this->entityManager;

        /** @var ?Role $role */
        $role = $em->getRDBRepositoryByClass(Role::class)
            ->where(['name' => $name])
            ->findOne();

        if (!$role) {
            $role = $em->getRDBRepositoryByClass(Role::class)->getNew();
            $role->set([
                'name'      => $name,
                'data'      => (object) $data,
                'fieldData' => (object) $fieldData,
            ]);
            foreach ($perms as $k => $v) {
                $role->set($k, $v);
            }
            $em->saveEntity($role);
            return 'created';
        }

        $changed = false;

        $currentData = json_decode(json_encode($role->get('data') ?? new \stdClass()), true) ?? [];
        if ($currentData !== $data) {
            $role->set('data', (object) $data);
            $changed = true;
        }

        $currentFieldData = json_decode(json_encode($role->get('fieldData') ?? new \stdClass()), true) ?? [];
        if ($currentFieldData !== $fieldData) {
            $role->set('fieldData', (object) $fieldData);
            $changed = true;
        }

        foreach ($perms as $k => $v) {
            if ($role->get($k) !== $v) {
                $role->set($k, $v);
                $changed = true;
            }
        }

        if ($changed) {
            $em->saveEntity($role);
            return 'updated';
        }

        return 'unchanged';
    }
}
