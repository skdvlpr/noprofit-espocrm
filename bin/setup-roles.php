<?php
/**
 * Provision the canonical SafehouseCrm roles (Admin, Dipendente, Volontario,
 * Associato) and three test users (test_dipendente, test_volontario,
 * test_associato), all idempotently.
 *
 * - Test password for all three users: Test1234!
 * - Volontario gets field-level ACL hiding ConteggioPasti.foodCost and
 *   ConteggioPasti.foodUnitPrice (Task 2.1 security requirement).
 *
 * Usage:
 *   php bin/setup-roles.php
 *   ddev exec php bin/setup-roles.php
 *
 * Run again any time to reapply the canonical permission matrix in case
 * something drifted in Admin → Roles UI.
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Modules\SafehouseCrm\Tools\RoleSetup;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);

$setup = $injectableFactory->create(RoleSetup::class);

echo "Provisioning roles...\n";
foreach ($setup->provisionRoles() as $name => $status) {
    echo "  - $name: $status\n";
}

echo "\nProvisioning test users (password: " . RoleSetup::TEST_PASSWORD . ")...\n";
foreach ($setup->provisionTestUsers() as $userName => $status) {
    echo "  - $userName: $status\n";
}

echo "\nProvisioning linked domain records for test users...\n";
foreach ($setup->provisionTestProfiles() as $userName => $status) {
    echo "  - $userName: $status\n";
}

echo "\nDone. Admin -> Repair -> Rebuild -> Clear Cache recommended.\n";
