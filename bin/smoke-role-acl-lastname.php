<?php

declare(strict_types=1);

require __DIR__ . '/lib/refuse-production.php';

/**
 * Smoke: optional lastName + core-only roles after ProvisionRoleAcl.
 *
 *   ddev exec php bin/smoke-role-acl-lastname.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Modules\NonprofitEspocrm\Tools\RoleSetup;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
$metadata = $container->get('metadata');
$em = $container->get('entityManager');

$fail = 0;
$ok = static function (string $label, bool $pass, string $detail = '') use (&$fail): void {
    echo ($pass ? 'OK  ' : 'FAIL ') . $label . ($detail !== '' ? " — $detail" : '') . PHP_EOL;
    if (!$pass) {
        $fail++;
    }
};

$ok(
    'Contact.lastName required=false',
    $metadata->get(['entityDefs', 'Contact', 'fields', 'lastName', 'required']) === false
);
$ok(
    'User.lastName required=false',
    $metadata->get(['entityDefs', 'User', 'fields', 'lastName', 'required']) === false
);

$roleSetup = $container->get('injectableFactory')->create(RoleSetup::class);
$roleSetup->provisionRoles(true);
$prune = $roleSetup->pruneNonCoreRoles();

$names = [];
foreach ($em->getRDBRepository('Role')->find() as $role) {
    $names[] = (string) $role->get('name');
}
sort($names);

$ok('Core role Admin present', in_array('Admin', $names, true));
$ok('Core role Volunteer present', in_array('Volunteer', $names, true));
$ok('Core role Member present', in_array('Member', $names, true));

$extras = array_values(array_diff($names, RoleSetup::CORE_ROLES));
$ok(
    'No non-core roles remain',
    $extras === [] || getenv('SAFEHOUSE_EXTRA_ROLES') === '1',
    $extras === [] ? 'only core' : 'extras=' . implode(',', $extras)
);

echo 'prune=' . json_encode($prune) . PHP_EOL;
echo ($fail === 0 ? "Passed\n" : "Failed: $fail\n");
exit($fail === 0 ? 0 : 1);
