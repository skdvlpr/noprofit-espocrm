<?php
/**
 * Provision a full-access Admin role + smoke_api_catalog API user on fresh Espo
 * installs that have no Safehouse roles yet (vanilla 9.3.8 clean instances).
 *
 * Usage:
 *   ddev exec php bin/provision-smoke-api-user.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

const SMOKE_ROLE_ADMIN = 'Admin';
const SMOKE_USER_ADMIN = 'smoke_api_catalog';

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);
$metadata->init(true);

$full = static fn (): array => [
    'create' => 'yes',
    'read' => 'all',
    'edit' => 'all',
    'delete' => 'all',
    'stream' => 'all',
];

$roleData = [];
foreach ($metadata->get('scopes', []) as $entityType => $scope) {
    if (!is_string($entityType) || $entityType === '') {
        continue;
    }

    if (!is_array($scope) || !($scope['entity'] ?? false)) {
        continue;
    }

    if (($scope['acl'] ?? true) === false) {
        continue;
    }

    $roleData[$entityType] = $full();
}

$role = $em->getRDBRepository('Role')
    ->where(['name' => SMOKE_ROLE_ADMIN, 'deleted' => false])
    ->findOne();

if ($role === null) {
    $role = $em->createEntity('Role', [
        'name' => SMOKE_ROLE_ADMIN,
        'assignmentPermission' => 'all',
        'userPermission' => 'all',
        'messagePermission' => 'all',
        'portalPermission' => 'yes',
        'exportPermission' => 'yes',
        'massUpdatePermission' => 'yes',
        'data' => (object) $roleData,
    ]);
    $em->saveEntity($role);
    echo "Created role " . SMOKE_ROLE_ADMIN . "\n";
} else {
    $role->set('data', (object) $roleData);
    $role->set('assignmentPermission', 'all');
    $role->set('userPermission', 'all');
    $role->set('messagePermission', 'all');
    $role->set('portalPermission', 'yes');
    $role->set('exportPermission', 'yes');
    $role->set('massUpdatePermission', 'yes');
    $em->saveEntity($role);
    echo "Updated role " . SMOKE_ROLE_ADMIN . "\n";
}

$roleId = $role->getId();

/** @var ?User $user */
$user = $em->getRDBRepository('User')
    ->where(['userName' => SMOKE_USER_ADMIN, 'deleted' => false])
    ->findOne();

if ($user === null) {
    $user = $em->createEntity(User::ENTITY_TYPE, [
        'userName' => SMOKE_USER_ADMIN,
        'type' => User::TYPE_API,
        'authMethod' => ApiKeyLogin::NAME,
        'rolesIds' => [$roleId],
        'firstName' => 'Smoke',
        'lastName' => 'ApiCatalog',
        'apiKey' => Util::generateApiKey(),
    ]);
    $em->saveEntity($user);
    echo "Created API user " . SMOKE_USER_ADMIN . "\n";
} else {
    $user->set('authMethod', ApiKeyLogin::NAME);
    $user->set('rolesIds', [$roleId]);
    if (!is_string($user->get('apiKey')) || $user->get('apiKey') === '') {
        $user->set('apiKey', Util::generateApiKey());
    }
    $em->saveEntity($user);
    echo "Updated API user " . SMOKE_USER_ADMIN . "\n";
}

echo "Done.\n";
