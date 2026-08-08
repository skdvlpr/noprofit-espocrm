<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Core\Utils\PasswordHash;
use Espo\Entities\Role;
use Espo\Entities\Team;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/**
 * Reusable role / team provisioning for the SafehouseCrm module.
 *
 * Used from:
 *   - Extension AfterInstall / Tools\Installer (fresh install only)
 *   - Dev/smoke helpers that call specific methods explicitly
 *
 * There is NO maintenance CLI to mass-reset roles/users on an existing instance.
 * Never invent one for production.
 *
 * All public methods are idempotent for creates; existing Role rows are left
 * unchanged unless SAFEHOUSE_ALLOW_ROLE_OVERWRITE=1 (dev only).
 *
 * Permission matrix (2026-08-04):
 *   - Admin     : full all
 *   - Volunteer : read all domain + reporting; field-level hide personal data on
 *                 Contact/User/VE/Member (name/status/competences/positionsHeld
 *                 remain visible); write only own Task + ActivityInvite
 *   - Employee  : same ACL as Volunteer (IT label Dipendente); Contact sync → contactType=Employee
 *   - Member    : read all; write nowhere
 *   - Manager / Desk : only when SAFEHOUSE_EXTRA_ROLES=1 (local/dev)
 *
 * Existing roles are left unchanged unless SAFEHOUSE_ALLOW_ROLE_OVERWRITE=1
 * or versioned rebuild ProvisionRoleAcl forces overwrite.
 */
class RoleSetup
{
    public const ROLE_ADMIN     = 'Admin';
    public const ROLE_EMPLOYEE  = 'Employee';
    /** HR / personnel: create & edit VolunteerEmployee / Member (no hard delete on those). */
    public const ROLE_MANAGER   = 'Manager';
    public const ROLE_VOLUNTEER = 'Volunteer';
    public const ROLE_MEMBER    = 'Member';
    /** Sportello desk staff: Case / Lead / Email intake with team-scoped group mailboxes. */
    public const ROLE_DESK      = 'Desk';
    /**
     * Public website API user (`site_safehouse.community`): create + read + edit
     * donation/party entities (settlement backfill, paymentStatus); no delete.
     */
    public const ROLE_WEBSITE   = 'Website';

    public const TEAM_ADMINISTRATION = 'Administration';
    public const TEAM_DIGITAL_DESK = 'Sportello digitale';
    public const TEAM_LEGAL_DESK   = 'Sportello legale';

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
            'roles'       => [self::ROLE_ADMIN],
        ],
        self::TEAM_DIGITAL_DESK => [
            'description' => 'Digital desk (Sportello digitale): group inbox, segnalazioni and leads.',
            'roles'       => [self::ROLE_DESK],
        ],
        self::TEAM_LEGAL_DESK => [
            'description' => 'Legal desk (Sportello legale): group inbox, segnalazioni and leads.',
            'roles'       => [self::ROLE_DESK],
        ],
    ];

    /** Roles always provisioned (production default). */
    public const CORE_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_VOLUNTEER,
        self::ROLE_EMPLOYEE,
        self::ROLE_MEMBER,
    ];

    /** Optional staff roles — only when SAFEHOUSE_EXTRA_ROLES=1 (local/dev). */
    public const EXTRA_ROLES = [
        self::ROLE_MANAGER,
        self::ROLE_DESK,
    ];

    /** Bump when Volunteer/Member/Admin/Employee ACL must be rewritten on rebuild (prod-safe). */
    /**
     * Bump when role specs change so ProvisionRoleAcl rewrites matrices on rebuild.
     * 2026-08-08-v3: Website must override baseline Volunteer field locks on
     * Contact/Account email+phone (baselineRoleId=Volunteer otherwise blocks
     * Stripe party match → payment_intent.succeeded 502).
     */
    public const ACL_MATRIX_VERSION = '2026-08-08-website-contact-channels-v3';
    public const ACL_MATRIX_CONFIG_KEY = 'safehouseRoleAclVersion';

    /**
     * API userNames that must receive the Website role (donation site sync).
     *
     * @var list<string>
     */
    public const WEBSITE_API_USER_NAMES = [
        'website',
        'site_safehouse.community',
    ];

    /**
     * Trusted Stripe sync actors (ProtectStripeSourcedFields bypass).
     *
     * @var list<string>
     */
    public const STRIPE_SYNC_USER_NAMES = [
        'website',
        'site_safehouse.community',
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
     * Create or update canonical roles.
     *
     * @param bool $forceOverwrite When true, rewrite existing role matrices
     *        (used by versioned rebuild ProvisionRoleAcl). Otherwise existing
     *        roles stay untouched unless SAFEHOUSE_ALLOW_ROLE_OVERWRITE=1.
     *
     * @return array<string, string> map role-name => 'created' | 'updated' | 'unchanged' | 'unchanged-existing'
     */
    public function provisionRoles(bool $forceOverwrite = false): array
    {
        $force = $forceOverwrite || getenv('SAFEHOUSE_ALLOW_ROLE_OVERWRITE') === '1';
        $report = [];

        foreach ($this->roleSpecs() as $name => $spec) {
            $report[$name] = $this->upsertRole(
                $name,
                $spec['data'],
                $spec['fieldData'],
                $spec['perms'],
                $force
            );
        }

        foreach ($this->ensureWebsiteApiRoleAssignments() as $userName => $status) {
            $report['website-api:' . $userName] = $status;
        }

        return $report;
    }

    /**
     * Attach Website role to known donation-site API users (create/read/edit PrimaNota).
     * Without this, Stripe refresh/webhooks get "No edit access" and status stays Planned.
     *
     * @return array<string, string> userName => created|already|missing-user|missing-role
     */
    public function ensureWebsiteApiRoleAssignments(): array
    {
        $em = $this->entityManager;
        $report = [];

        $websiteRole = $em->getRDBRepositoryByClass(Role::class)
            ->where(['name' => self::ROLE_WEBSITE])
            ->findOne();

        if (!$websiteRole) {
            foreach (self::WEBSITE_API_USER_NAMES as $userName) {
                $report[$userName] = 'missing-role';
            }

            return $report;
        }

        foreach (self::WEBSITE_API_USER_NAMES as $userName) {
            $user = $em->getRDBRepositoryByClass(User::class)
                ->where([
                    'userName' => $userName,
                    'type' => 'api',
                    'deleted' => false,
                ])
                ->findOne();

            if (!$user) {
                $report[$userName] = 'missing-user';
                continue;
            }

            $roleIds = $user->getLinkMultipleIdList('roles');

            if (in_array($websiteRole->getId(), $roleIds, true)) {
                $report[$userName] = 'already';
                continue;
            }

            $em->getRDBRepository('User')->getRelation($user, 'roles')->relate($websiteRole);
            $report[$userName] = 'linked';
        }

        return $report;
    }

    /**
     * Soft-delete every Role not in {@see CORE_ROLES}.
     * Unlinks users first so Admin/Volunteer/Member remain the only selectable roles.
     * Skipped when SAFEHOUSE_EXTRA_ROLES=1 (local staff-role sandbox).
     *
     * @return array<string, string>
     */
    public function pruneNonCoreRoles(): array
    {
        $em = $this->entityManager;
        $report = [];

        if (getenv('SAFEHOUSE_EXTRA_ROLES') === '1') {
            return ['skipped' => 'SAFEHOUSE_EXTRA_ROLES=1'];
        }

        $roles = $em->getRDBRepositoryByClass(Role::class)->find();

        foreach ($roles as $role) {
            $roleName = (string) $role->get('name');

            if (in_array($roleName, self::CORE_ROLES, true)) {
                $report[$roleName] = 'kept-core';
                continue;
            }

            // Website is an API integration role (site sync / Stripe refresh), not a UI staff role.
            if ($roleName === self::ROLE_WEBSITE) {
                $report[$roleName] = 'kept-api';
                continue;
            }

            // Keep any role still linked to a type=api user.
            $linkedToApi = false;

            foreach ($em->getRDBRepository('User')->where(['type' => 'api', 'deleted' => false])->find() as $apiUser) {
                if (in_array($role->getId(), $apiUser->getLinkMultipleIdList('roles'), true)) {
                    $linkedToApi = true;
                    break;
                }
            }

            if ($linkedToApi) {
                $report[$roleName] = 'kept-api-user';
                continue;
            }

            $unlinked = 0;

            foreach ($em->getRDBRepository('User')->where(['deleted' => false])->find() as $user) {
                $roleIds = $user->getLinkMultipleIdList('roles');

                if (!in_array($role->getId(), $roleIds, true)) {
                    continue;
                }

                $em->getRDBRepository('User')->getRelation($user, 'roles')->unrelate($role);
                $unlinked++;
            }

            $em->removeEntity($role);
            $report[$roleName] = $unlinked > 0
                ? "removed (unlinked {$unlinked} users)"
                : 'removed';
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
        if (getenv('SAFEHOUSE_ALLOW_TEST_USERS') !== '1') {
            throw new \RuntimeException(
                'Refusing to provision test users. Set SAFEHOUSE_ALLOW_TEST_USERS=1 for local/dev only.'
            );
        }

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

        $teamCreateOwnDelete = static fn(): array => [
            'create' => 'yes', 'read' => 'all', 'edit' => 'team', 'delete' => 'own', 'stream' => 'all',
        ];

        $domainEntities = [
            'Account', 'AccountWebsite', 'Contact', 'Opportunity', 'Lead',
            'VolunteerEmployee', 'Member', 'MealCount', 'AssociationMealCount', 'PrimaNota',
            'Intervention', 'FoodParcelRegistration', 'FoodParcelDateLog',
            'Document', 'Meeting', 'Call', 'Task', 'Email', 'Case',
            'ActivityOffer', 'ActivityOfferSlot', 'ActivityInvite',
            'GCalSmokeAllDay', 'GCalSmokeDateTime', 'GCalSmokeTwinDate',
        ];

        $adminData = [];
        foreach ($domainEntities as $e) {
            $adminData[$e] = $allFull();
        }

        // Volunteer: read everything; write only own Task + ActivityInvite.
        $volunteerData = [];
        foreach ($domainEntities as $e) {
            $volunteerData[$e] = $readOnlyAll();
        }
        $volunteerData['Email'] = [
            'create' => 'no', 'read' => 'own', 'edit' => 'no', 'delete' => 'no', 'stream' => 'own',
        ];
        $volunteerData['Task'] = [
            'create' => 'no', 'read' => 'all', 'edit' => 'own', 'delete' => 'no', 'stream' => 'all',
        ];
        $volunteerData['ActivityOffer'] = [
            'create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no', 'stream' => 'all',
        ];
        $volunteerData['ActivityOfferSlot'] = [
            'create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no',
        ];
        $volunteerData['ActivityInvite'] = [
            'create' => 'no', 'read' => 'own', 'edit' => 'own', 'delete' => 'no',
        ];

        // Member: read everything; change nothing.
        $memberData = [];
        foreach ($domainEntities as $e) {
            $memberData[$e] = $readOnlyAll();
        }
        $memberData['Email'] = [
            'create' => 'no', 'read' => 'own', 'edit' => 'no', 'delete' => 'no', 'stream' => 'own',
        ];
        $memberData['ActivityInvite'] = [
            'create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no',
        ];

        $pdHide = $this->personalDataFieldLocks();

        $volunteerFieldData = [
            'Contact' => array_merge($pdHide, [
                'positionsHeld' => ['read' => 'yes', 'edit' => 'no'],
            ]),
            'User' => array_merge($pdHide, [
                'activityCompetences' => ['read' => 'yes', 'edit' => 'no'],
            ]),
            'VolunteerEmployee' => $pdHide,
            'Member' => array_merge($pdHide, [
                'positionsHeld' => ['read' => 'yes', 'edit' => 'no'],
            ]),
        ];

        $readOnlyPerms = [
            'assignmentPermission'         => 'no',
            'userPermission'               => 'all',
            'messagePermission'            => 'no',
            'exportPermission'             => 'no',
            'massUpdatePermission'         => 'no',
            'auditPermission'              => 'no',
            'mentionPermission'            => 'team',
            'userCalendarPermission'       => 'team',
            'followerManagementPermission' => 'no',
        ];

        $blocked = static fn(): array => [
            'create' => 'no', 'read' => 'no', 'edit' => 'no', 'delete' => 'no', 'stream' => 'no',
        ];
        $websiteCreateReadEdit = static fn(): array => [
            'create' => 'yes', 'read' => 'all', 'edit' => 'all', 'delete' => 'no', 'stream' => 'no',
        ];
        $websiteData = [];
        foreach ($domainEntities as $e) {
            $websiteData[$e] = $blocked();
        }
        foreach (['Account', 'Contact', 'Opportunity', 'PrimaNota', 'Lead', 'Case'] as $e) {
            $websiteData[$e] = $websiteCreateReadEdit();
        }

        $specs = [
            self::ROLE_ADMIN => [
                'data'      => $adminData,
                'fieldData' => [],
                'perms'     => [
                    'assignmentPermission'         => 'all',
                    'userPermission'               => 'all',
                    'messagePermission'            => 'all',
                    'exportPermission'             => 'yes',
                    'massUpdatePermission'         => 'yes',
                    'auditPermission'              => 'yes',
                    'mentionPermission'            => 'yes',
                    'userCalendarPermission'       => 'all',
                    'followerManagementPermission' => 'all',
                ],
            ],
            self::ROLE_VOLUNTEER => [
                'data'      => $volunteerData,
                'fieldData' => $volunteerFieldData,
                'perms'     => $readOnlyPerms,
            ],
            // Employee (Dipendente): identical ACL to Volunteer.
            self::ROLE_EMPLOYEE => [
                'data'      => $volunteerData,
                'fieldData' => $volunteerFieldData,
                'perms'     => $readOnlyPerms,
            ],
            self::ROLE_MEMBER => [
                'data'      => $memberData,
                'fieldData' => [],
                'perms'     => array_merge($readOnlyPerms, [
                    'mentionPermission'      => 'no',
                    'userCalendarPermission' => 'no',
                ]),
            ],
            // Always provisioned: donation-site API sync (Stripe refresh / PrimaNota ingest).
            // fieldData must explicitly allow Contact/Account channels: Espo baselineRole
            // (Volunteer) hides personal fields with read/edit=no, and empty Website
            // fieldData does not override that merge — party email where then 400s.
            self::ROLE_WEBSITE => [
                'data'      => $websiteData,
                'fieldData' => $this->websiteContactChannelFieldAllows(),
                'perms'     => [
                    'assignmentPermission'         => 'all',
                    'userPermission'               => 'no',
                    'messagePermission'            => 'no',
                    'exportPermission'             => 'no',
                    'massUpdatePermission'         => 'no',
                    'auditPermission'              => 'no',
                    'mentionPermission'            => 'no',
                    'userCalendarPermission'       => 'no',
                    'followerManagementPermission' => 'no',
                ],
            ],
        ];

        if (getenv('SAFEHOUSE_EXTRA_ROLES') !== '1') {
            return $specs;
        }

        $employeeStaffData = [];
        foreach ($domainEntities as $e) {
            $employeeStaffData[$e] = $teamCreateOwnDelete();
        }
        $employeeStaffData['MealCount'] = [
            'create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'own', 'stream' => 'own',
        ];
        $employeeStaffData['AssociationMealCount'] = [
            'create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'own', 'stream' => 'own',
        ];
        $employeeStaffData['PrimaNota'] = [
            'create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'own', 'stream' => 'own',
        ];
        $employeeStaffData['VolunteerEmployee'] = $readOnlyAll();
        $employeeStaffData['Member'] = $readOnlyAll();
        $employeeStaffData['ActivityOffer'] = [
            'create' => 'yes', 'read' => 'all', 'edit' => 'own', 'delete' => 'own', 'stream' => 'all',
        ];
        $employeeStaffData['ActivityOfferSlot'] = [
            'create' => 'yes', 'read' => 'all', 'edit' => 'own', 'delete' => 'own',
        ];
        $employeeStaffData['ActivityInvite'] = [
            'create' => 'no', 'read' => 'own', 'edit' => 'own', 'delete' => 'no',
        ];

        /** @var array<string, array<string, string>> $managerData */
        $managerData = json_decode(json_encode($employeeStaffData), true);
        $managerData['VolunteerEmployee'] = [
            'create' => 'yes', 'read' => 'all', 'edit' => 'team', 'delete' => 'no', 'stream' => 'all',
        ];
        $managerData['Member'] = [
            'create' => 'yes', 'read' => 'all', 'edit' => 'team', 'delete' => 'no', 'stream' => 'all',
        ];
        $managerData['Lead'] = [
            'create' => 'yes', 'read' => 'all', 'edit' => 'team', 'delete' => 'no', 'stream' => 'all',
        ];
        $managerData['ActivityOffer'] = $allFull();
        $managerData['ActivityOfferSlot'] = $allFull();
        $managerData['ActivityInvite'] = [
            'create' => 'no', 'read' => 'all', 'edit' => 'all', 'delete' => 'no',
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
            'Contact' => [
                'emailAddress' => ['read' => 'yes', 'edit' => 'no'],
                'phoneNumber'  => ['read' => 'yes', 'edit' => 'no'],
            ],
        ];

        $deskEntities = [
            'Case', 'Lead', 'Email', 'EmailTemplate', 'Contact', 'Account', 'Document', 'Task',
        ];
        $deskData = [];
        foreach ($deskEntities as $entity) {
            $deskData[$entity] = $teamCreateOwnDelete();
        }
        $deskData['EmailTemplate'] = $readOnlyAll();
        $deskData['Account'] = $readOnlyAll();
        $deskData['Contact'] = $readOnlyAll();
        $deskData['Document'] = [
            'create' => 'no', 'read' => 'team', 'edit' => 'no', 'delete' => 'no', 'stream' => 'team',
        ];

        $staffPerms = [
            'assignmentPermission'         => 'team',
            'userPermission'               => 'team',
            'messagePermission'            => 'team',
            'exportPermission'             => 'yes',
            'massUpdatePermission'         => 'no',
            'auditPermission'              => 'no',
            'mentionPermission'            => 'all',
            'userCalendarPermission'       => 'team',
            'followerManagementPermission' => 'team',
        ];

        // Employee stays Volunteer-equivalent (CORE); do not overwrite with staff ACL.
        $specs[self::ROLE_MANAGER] = [
            'data'      => $managerData,
            'fieldData' => $personContactFieldLocks,
            'perms'     => $staffPerms,
        ];
        $specs[self::ROLE_DESK] = [
            'data'      => $deskData,
            'fieldData' => [],
            'perms'     => array_merge($staffPerms, [
                'exportPermission'            => 'no',
                'groupEmailAccountPermission' => 'team',
            ]),
        ];

        return $specs;
    }

    /**
     * Field-level ACL: hide personal / sensitive attributes (Espo Role.fieldData).
     * Metadata `isPersonalData` is informational — enforcement is here.
     *
     * @return array<string, array{read: string, edit: string}>
     */
    private function personalDataFieldLocks(): array
    {
        $hide = ['read' => 'no', 'edit' => 'no'];
        $fields = [
            'emailAddress',
            'phoneNumber',
            'taxCode',
            'birthDate',
            'birthPlace',
            'birthProvince',
            'address',
            'addressStreet',
            'addressCity',
            'addressState',
            'addressCountry',
            'addressPostalCode',
            'notes',
            'extra',
            'description',
            'memberNotes',
            'weeklyHours',
            'monthlyHours',
            'contractType',
            'startDate',
            'endDate',
            'joinDate',
            'leaveDate',
        ];

        $out = [];
        foreach ($fields as $field) {
            $out[$field] = $hide;
        }

        return $out;
    }

    /**
     * Website API: allow donor channel fields used by donation ingest / party match.
     * Must be explicit so they win over baseline Volunteer personal-data locks.
     *
     * @return array<string, array<string, array{read: string, edit: string}>>
     */
    private function websiteContactChannelFieldAllows(): array
    {
        $allow = ['read' => 'yes', 'edit' => 'yes'];

        $channels = [
            'emailAddress' => $allow,
            'phoneNumber' => $allow,
        ];

        return [
            'Contact' => $channels,
            'Account' => $channels,
            'Lead' => $channels,
        ];
    }

    /**
     * @param array<string, array<string, string>|bool> $data
     * @param array<string, array<string, array<string, string>>> $fieldData
     * @param array<string, string> $perms
     */
    private function upsertRole(
        string $name,
        array $data,
        array $fieldData,
        array $perms,
        bool $forceOverwrite = false
    ): string {
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

        // Never silently overwrite live role matrices (prod incident 2026-07-26),
        // unless versioned rebuild / explicit env asks for it.
        if (!$forceOverwrite) {
            return 'unchanged-existing';
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
