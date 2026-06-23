<?php
/**
 * Provision the canonical SafehouseCrm roles (Admin, Employee, Manager,
 * Volunteer, Member) and test users (test_dipendente, test_manager,
 * test_volontario, test_associato), all idempotently.
 *
 * - Test password for all test users: Test1234!
 * - Volunteer role gets field-level ACL hiding MealCount.foodCost and
 *   MealCount.foodUnitPrice (Task 2.1 security requirement).
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
use Espo\Modules\NonprofitEspocrm\Tools\RoleSetup;

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

echo "\nProvisioning teams...\n";
foreach ($setup->provisionTeams() as $name => $status) {
    echo "  - $name: $status\n";
}

echo "\nProvisioning test users, team memberships, and linked profiles (password: "
    . RoleSetup::TEST_PASSWORD . ")...\n";
$testReport = $setup->provisionTestUsersTeamsAndProfiles();

echo "  Users:\n";
foreach ($testReport['users'] as $userName => $status) {
    echo "    - $userName: $status\n";
}

echo "  Team memberships:\n";
foreach ($testReport['teamMemberships'] as $userName => $status) {
    echo "    - $userName: $status\n";
}

echo "  Linked domain records:\n";
foreach ($testReport['profiles'] as $userName => $status) {
    echo "    - $userName: $status\n";
}

echo "\nDone. Admin -> Repair -> Rebuild -> Clear Cache recommended.\n";
