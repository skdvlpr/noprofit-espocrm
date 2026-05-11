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
 *   - Admin       : full all / not used by the bootstrap admin user (which has
 *                   isAdmin=true and bypasses roles), but available for
 *                   secondary super-users.
 *   - Dipendente  : create=yes, read=all, edit=team, delete=own, stream=yes.
 *   - Volontario  : read-mostly; grants/funding (Espo entity type `Opportunity`,
 *                   labelled Fondi e Finanziamenti) fully blocked;
 *                   ConteggioPasti.foodCost and ConteggioPasti.foodUnitPrice
 *                   hidden via field-level ACL.
 *   - Associato   : read/edit own only; cannot create or delete.
 */
class RoleSetup
{
    public const ROLE_ADMIN      = 'Admin';
    public const ROLE_DIPENDENTE = 'Dipendente';
    public const ROLE_VOLONTARIO = 'Volontario';
    public const ROLE_ASSOCIATO  = 'Associato';

    public const TEAM_AMMINISTRAZIONE = 'Amministrazione';

    /**
     * Map team-name => spec.
     * - `roles`: list of role names whose members should inherit access via this team
     *   (Espo links roles to teams; the team's effective role set is the union of these)
     *
     * @var array<string, array{description: string, roles: string[]}>
     */
    public const TEAMS = [
        self::TEAM_AMMINISTRAZIONE => [
            'description' => 'Personale amministrativo Safehouse: visibilità condivisa dei record amministrativi.',
            'roles'       => [self::ROLE_DIPENDENTE],
        ],
    ];

    /**
     * Map test userName => list of team names they should belong to.
     *
     * @var array<string, string[]>
     */
    public const TEAM_MEMBERSHIPS = [
        'test_dipendente' => [self::TEAM_AMMINISTRAZIONE],
    ];

    /**
     * Test users created when {@see provisionTestUsers()} is called.
     * Password is the same for all three for convenience in dev.
     *
     * @var array<string, array{role: string, firstName: string, lastName: string}>
     */
    public const TEST_USERS = [
        'test_dipendente' => [
            'role'      => self::ROLE_DIPENDENTE,
            'firstName' => 'Test',
            'lastName'  => 'Dipendente',
        ],
        'test_volontario' => [
            'role'      => self::ROLE_VOLONTARIO,
            'firstName' => 'Test',
            'lastName'  => 'Volontario',
        ],
        'test_associato' => [
            'role'      => self::ROLE_ASSOCIATO,
            'firstName' => 'Test',
            'lastName'  => 'Associato',
        ],
    ];

    public const TEST_PASSWORD = 'Test1234!';

    public function __construct(
        private EntityManager $entityManager,
        private PasswordHash $passwordHash
    ) {}

    /**
     * Create the canonical teams (currently just `Amministrazione`) and ensure
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
     * Create or update all four canonical roles.
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
     * For each test user, ensure there is a linked domain record assigned to
     * them so the `read=own` ACL has at least one row to return.
     *
     *   - test_volontario  -> VolontarioDipendente (tipo=Volontario)
     *   - test_dipendente  -> VolontarioDipendente (tipo=Dipendente)
     *   - test_associato   -> Associati
     *
     * Idempotent: skipped if a record already exists with assignedUserId =
     * the test user's id.
     *
     * @return array<string, string>
     */
    public function provisionTestProfiles(): array
    {
        $em = $this->entityManager;
        $report = [];

        $profiles = [
            'test_volontario' => [
                'entityType' => 'VolontarioDipendente',
                'attributes' => [
                    'tipo'          => 'Volontario',
                    'nome'          => 'Test',
                    'cognome'       => 'Volontario',
                    'email'         => 'test_volontario@example.com',
                    'oreSettimanali'=> 8,
                ],
            ],
            'test_dipendente' => [
                'entityType' => 'VolontarioDipendente',
                'attributes' => [
                    'tipo'          => 'Dipendente',
                    'nome'          => 'Test',
                    'cognome'       => 'Dipendente',
                    'email'         => 'test_dipendente@example.com',
                    'tipoContratto' => 'Tempo Determinato',
                    'oreSettimanali'=> 40,
                ],
            ],
            'test_associato' => [
                'entityType' => 'Associati',
                'attributes' => [
                    'nome'    => 'Test',
                    'cognome' => 'Associato',
                    'stato'   => 'Attivo',
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
                ->where(['assignedUserId' => $user->getId()])
                ->findOne();

            if ($existing) {
                $linked = $this->ensureTeamMembership($existing, $userName);
                $report[$userName] = $linked
                    ? 'updated (team linked)'
                    : 'unchanged';
                continue;
            }

            $entity = $em->getRDBRepository($spec['entityType'])->getNew();
            $entity->set(array_merge($spec['attributes'], [
                'assignedUserId' => $user->getId(),
            ]));
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
                    'userName'  => $userName,
                    'firstName' => $spec['firstName'],
                    'lastName'  => $spec['lastName'],
                    'type'      => User::TYPE_REGULAR,
                    'isActive'  => true,
                    'password'  => $this->passwordHash->hash(self::TEST_PASSWORD),
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

            $report[$userName] = $isNew ? 'created' : 'unchanged';
        }

        return $report;
    }

    /**
     * Link the given domain entity (VolontarioDipendente / Associati) to every
     * team the corresponding test user is a member of, via the entity's
     * `teams` linkMultiple field. Idempotent.
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

        $own = static fn(): array => [
            'create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'own', 'stream' => 'own',
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
            'VolontarioDipendente', 'Associati', 'ConteggioPasti', 'Document',
            'Meeting', 'Call', 'Task', 'Email',
        ];

        $adminData = [];
        foreach ($domainEntities as $e) {
            $adminData[$e] = $allFull();
        }

        $dipendenteData = [];
        foreach ($domainEntities as $e) {
            $dipendenteData[$e] = $teamCreateOwnDelete();
        }
        $dipendenteData['ConteggioPasti'] = [
            'create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'own', 'stream' => 'own',
        ];

        $volontarioData = [];
        foreach ($domainEntities as $e) {
            $volontarioData[$e] = $readOnlyAll();
        }
        // Business name FondiSovvenzioni — still `Opportunity` as entity type in EspoCRM.
        $volontarioData['Opportunity'] = $blocked();
        $volontarioData['Associati'] = $blocked();
        $volontarioData['VolontarioDipendente'] = [
            'create' => 'no', 'read' => 'own', 'edit' => 'own', 'delete' => 'no', 'stream' => 'own',
        ];
        $volontarioData['ConteggioPasti'] = [
            'create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'no', 'stream' => 'own',
        ];
        $volontarioData['Account'] = $readOnlyAll();
        $volontarioData['Contact'] = $readOnlyAll();
        $volontarioData['Document'] = [
            'create' => 'no', 'read' => 'team', 'edit' => 'own', 'delete' => 'no', 'stream' => 'team',
        ];

        $volontarioFieldData = [
            'ConteggioPasti' => [
                'foodCost'      => ['read' => 'no', 'edit' => 'no'],
                'foodUnitPrice' => ['read' => 'no', 'edit' => 'no'],
            ],
        ];

        $associatoData = [];
        foreach ($domainEntities as $e) {
            $associatoData[$e] = $blocked();
        }
        $associatoData['Account']   = ['create' => 'no', 'read' => 'own', 'edit' => 'own', 'delete' => 'no', 'stream' => 'own'];
        $associatoData['Contact']   = ['create' => 'no', 'read' => 'own', 'edit' => 'own', 'delete' => 'no', 'stream' => 'own'];
        $associatoData['Document']  = ['create' => 'no', 'read' => 'own', 'edit' => 'no',  'delete' => 'no', 'stream' => 'own'];
        $associatoData['Associati'] = ['create' => 'no', 'read' => 'own', 'edit' => 'own', 'delete' => 'no', 'stream' => 'own'];

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
            self::ROLE_DIPENDENTE => [
                'data'      => $dipendenteData,
                'fieldData' => [],
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
            self::ROLE_VOLONTARIO => [
                'data'      => $volontarioData,
                'fieldData' => $volontarioFieldData,
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
            self::ROLE_ASSOCIATO => [
                'data'      => $associatoData,
                'fieldData' => [],
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
