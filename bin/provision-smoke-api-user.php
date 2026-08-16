<?php

declare(strict_types=1);

require __DIR__ . '/lib/refuse-production.php';

/**
 * Attach smoke_api_catalog API user to an existing Admin role.
 * Does NOT create or rewrite Roles — create Admin via Administration → Roles first.
 *
 * Usage:
 *   ddev exec php bin/provision-smoke-api-user.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
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

$role = $em->getRDBRepository('Role')
    ->where(['name' => SMOKE_ROLE_ADMIN, 'deleted' => false])
    ->findOne();

if ($role === null) {
    fwrite(STDERR, "FAIL: Role '" . SMOKE_ROLE_ADMIN . "' not found. Create it in Administration → Roles first.\n");
    exit(1);
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
