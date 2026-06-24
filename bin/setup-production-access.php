<?php
/**
 * Production ACL: roles + teams for transparent read, write via groups.
 *
 * Roles (assign ONE base role per user):
 *   Admin, Employee, Member, Volunteer  (English names; IT UI: Volontario for Volunteer)
 *
 * Teams:
 *   Can create / Can edit / Can delete  — grant write via team-linked roles
 *   Volontari / Dipendenti / Consiglio direttivo / Associati — org groups
 *
 * Usage:
 *   php bin/setup-production-access.php
 *   ddev exec php bin/setup-production-access.php
 *
 * Assign users:
 *   1. User → Role: Employee | Member | Volunteer (read-all baseline)
 *   2. User → Teams: add Can create / Can edit / Can delete as needed
 *   3. User → Teams: Volontari / Dipendenti / … for assignment scope
 *
 * Do NOT assign Can create/edit/delete roles directly to users — only via teams.
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Modules\NonprofitEspocrm\Tools\ProductionAccessSetup;

$app = new Application();
$app->setupSystemUser();

/** @var InjectableFactory $injectableFactory */
$injectableFactory = $app->getContainer()->getByClass(InjectableFactory::class);
$setup = $injectableFactory->create(ProductionAccessSetup::class);

echo "Production access provisioning (roles + teams)...\n\n";

$report = $setup->provision();

echo "Roles:\n";
foreach ($report['roles'] as $name => $status) {
    echo "  - $name: $status\n";
}

echo "\nTeams:\n";
foreach ($report['teams'] as $name => $status) {
    echo "  - $name: $status\n";
}

echo "\nDone. Run: php command.php rebuild\n";
echo "Assign each user one base role (Employee/Member/Volunteer) + capability/org teams.\n";
