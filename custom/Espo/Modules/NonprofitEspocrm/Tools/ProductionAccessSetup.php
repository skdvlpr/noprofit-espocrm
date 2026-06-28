<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Entities\Role;
use Espo\Entities\Team;
use Espo\ORM\EntityManager;

/**
 * Production ACL model:
 *
 *   - Roles Admin, Employee, Member, Volunteer: transparent read (read=all) on CRM
 *     entities; no create/edit/delete by default.
 *   - Capability roles Can create / Can edit / Can delete: grant write actions via
 *     team membership (Espo merges team-linked roles with the user's base role).
 *   - Org teams Volontari, Dipendenti, Consiglio direttivo, Associati: assignment
 *     groups (no linked roles).
 *
 * Role names stay English (AGENTS.md). Italian UI labels come from i18n.
 * Volunteer — not "Volonteur"; IT UI: Volontario.
 */
class ProductionAccessSetup
{
    public const ROLE_ADMIN = 'Admin';
    public const ROLE_EMPLOYEE = 'Employee';
    public const ROLE_MEMBER = 'Member';
    public const ROLE_VOLUNTEER = 'Volunteer';

    public const ROLE_CAN_CREATE = 'Can create';
    public const ROLE_CAN_EDIT = 'Can edit';
    public const ROLE_CAN_DELETE = 'Can delete';

    public const TEAM_CAN_CREATE = 'Can create';
    public const TEAM_CAN_EDIT = 'Can edit';
    public const TEAM_CAN_DELETE = 'Can delete';

    public const TEAM_VOLONTARI = 'Volontari';
    public const TEAM_DIPENDENTI = 'Dipendenti';
    public const TEAM_CONSIGLIO = 'Consiglio direttivo';
    public const TEAM_ASSOCIATI = 'Associati';

    /** @var string[] */
    private const DOMAIN_ENTITIES = [
        'Account', 'AccountWebsite', 'Contact', 'Lead', 'Opportunity',
        'VolunteerEmployee', 'Member', 'MealCount', 'AssociationMealCount', 'Document',
        'Meeting', 'Call', 'Task', 'Email', 'Case',
        'Intervention', 'PrimaNota', 'FoodParcelRegistration', 'FoodParcelDateLog',
        'KnowledgeBaseArticle',
        'GCalSmokeAllDay', 'GCalSmokeDateTime', 'GCalSmokeTwinDate',
    ];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @return array{roles: array<string, string>, teams: array<string, string>}
     */
    public function provision(): array
    {
        $roleReport = [];
        foreach ($this->roleSpecs() as $name => $spec) {
            $roleReport[$name] = $this->upsertRole($name, $spec['data'], $spec['fieldData'], $spec['perms']);
        }

        $teamReport = [];
        foreach ($this->teamSpecs() as $name => $spec) {
            $teamReport[$name] = $this->upsertTeam($name, $spec['description'], $spec['roles']);
        }

        return ['roles' => $roleReport, 'teams' => $teamReport];
    }

    /**
     * @param string[] $roleNames
     */
    private function upsertTeam(string $name, string $description, array $roleNames): string
    {
        $em = $this->entityManager;

        /** @var ?Team $team */
        $team = $em->getRDBRepositoryByClass(Team::class)
            ->where(['name' => $name])
            ->findOne();

        $isNew = $team === null;
        $changed = false;

        if ($isNew) {
            $team = $em->getRDBRepositoryByClass(Team::class)->getNew();
            $team->set(['name' => $name, 'description' => $description]);
            $em->saveEntity($team);
            $changed = true;
        } elseif ($team->get('description') !== $description) {
            $team->set('description', $description);
            $em->saveEntity($team);
            $changed = true;
        }

        $rolesRelation = $em->getRDBRepository('Team')->getRelation($team, 'roles');
        foreach ($roleNames as $roleName) {
            /** @var ?Role $role */
            $role = $em->getRDBRepositoryByClass(Role::class)
                ->where(['name' => $roleName])
                ->findOne();
            if ($role === null) {
                continue;
            }
            if (!$rolesRelation->isRelated($role)) {
                $rolesRelation->relate($role);
                $changed = true;
            }
        }

        if ($isNew) {
            return 'created';
        }

        return $changed ? 'updated' : 'unchanged';
    }

    /**
     * @return array<string, array{description: string, roles: string[]}>
     */
    private function teamSpecs(): array
    {
        return [
            self::TEAM_CAN_CREATE => [
                'description' => 'Members may create CRM records (merged with base read-only role).',
                'roles'       => [self::ROLE_CAN_CREATE],
            ],
            self::TEAM_CAN_EDIT => [
                'description' => 'Members may edit CRM records.',
                'roles'       => [self::ROLE_CAN_EDIT],
            ],
            self::TEAM_CAN_DELETE => [
                'description' => 'Members may delete CRM records.',
                'roles'       => [self::ROLE_CAN_DELETE],
            ],
            self::TEAM_VOLONTARI => [
                'description' => 'Volunteers — record assignment / visibility group.',
                'roles'       => [],
            ],
            self::TEAM_DIPENDENTI => [
                'description' => 'Employees — record assignment / visibility group.',
                'roles'       => [],
            ],
            self::TEAM_CONSIGLIO => [
                'description' => 'Board (Consiglio direttivo) — assignment group.',
                'roles'       => [],
            ],
            self::TEAM_ASSOCIATI => [
                'description' => 'Members (associati) — assignment group.',
                'roles'       => [],
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     data: array<string, array<string, string>|bool>,
     *     fieldData: array<string, array<string, array<string, string>>>,
     *     perms: array<string, string>
     * }>
     */
    private function roleSpecs(): array
    {
        $readOnlyAll = static fn (): array => [
            'create' => 'no',
            'read'   => 'all',
            'edit'   => 'no',
            'delete' => 'no',
            'stream' => 'all',
        ];

        $allFull = static fn (): array => [
            'create' => 'yes',
            'read'   => 'all',
            'edit'   => 'all',
            'delete' => 'all',
            'stream' => 'all',
        ];

        $baseReadOnly = [];
        foreach (self::DOMAIN_ENTITIES as $entityType) {
            $baseReadOnly[$entityType] = $readOnlyAll();
        }

        $capabilityCreate = [];
        $capabilityEdit = [];
        $capabilityDelete = [];
        foreach (self::DOMAIN_ENTITIES as $entityType) {
            $capabilityCreate[$entityType] = [
                'create' => 'yes', 'read' => 'no', 'edit' => 'no', 'delete' => 'no', 'stream' => 'no',
            ];
            $capabilityEdit[$entityType] = [
                'create' => 'no', 'read' => 'no', 'edit' => 'all', 'delete' => 'no', 'stream' => 'no',
            ];
            $capabilityDelete[$entityType] = [
                'create' => 'no', 'read' => 'no', 'edit' => 'no', 'delete' => 'all', 'stream' => 'no',
            ];
        }

        $adminData = [];
        foreach (self::DOMAIN_ENTITIES as $entityType) {
            $adminData[$entityType] = $allFull();
        }

        $basePerms = [
            'assignmentPermission'         => 'no',
            'userPermission'               => 'team',
            'messagePermission'            => 'team',
            'exportPermission'             => 'yes',
            'massUpdatePermission'         => 'no',
            'auditPermission'              => 'no',
            'mentionPermission'            => 'all',
            'userCalendarPermission'       => 'team',
            'followerManagementPermission' => 'no',
        ];

        return [
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
                    'mentionPermission'            => 'all',
                    'userCalendarPermission'       => 'all',
                    'followerManagementPermission' => 'all',
                ],
            ],
            self::ROLE_EMPLOYEE => [
                'data'      => $baseReadOnly,
                'fieldData' => [],
                'perms'     => $basePerms,
            ],
            self::ROLE_MEMBER => [
                'data'      => $baseReadOnly,
                'fieldData' => [],
                'perms'     => $basePerms,
            ],
            self::ROLE_VOLUNTEER => [
                'data'      => $baseReadOnly,
                'fieldData' => [],
                'perms'     => $basePerms,
            ],
            self::ROLE_CAN_CREATE => [
                'data'      => $capabilityCreate,
                'fieldData' => [],
                'perms'     => [
                    'assignmentPermission'         => 'no',
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
            self::ROLE_CAN_EDIT => [
                'data'      => $capabilityEdit,
                'fieldData' => [],
                'perms'     => [
                    'assignmentPermission'         => 'team',
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
            self::ROLE_CAN_DELETE => [
                'data'      => $capabilityDelete,
                'fieldData' => [],
                'perms'     => [
                    'assignmentPermission'         => 'no',
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

        if ($role === null) {
            $role = $em->getRDBRepositoryByClass(Role::class)->getNew();
            $role->set([
                'name'      => $name,
                'data'      => (object) $data,
                'fieldData' => (object) $fieldData,
            ]);
            foreach ($perms as $key => $value) {
                $role->set($key, $value);
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

        foreach ($perms as $key => $value) {
            if ($role->get($key) !== $value) {
                $role->set($key, $value);
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
