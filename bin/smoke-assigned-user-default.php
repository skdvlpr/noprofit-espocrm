<?php
/**
 * Smoke: assignedUser defaults to current user on record create.
 *
 * Usage: ddev exec php bin/smoke-assigned-user-default.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Metadata;
use Espo\Modules\NonprofitEspocrm\Tools\Record\AssignedUserDefaultApplier;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
$em = $container->get('entityManager');
/** @var Metadata $metadata */
$metadata = $container->get('metadata');
/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $mark = $pass ? 'PASS' : 'FAIL';
    echo "  [$mark] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$hook = 'Espo\\Modules\\NonprofitEspocrm\\Classes\\RecordHooks\\AssignedUser\\BeforeCreate';
$populator = 'Espo\\Modules\\NonprofitEspocrm\\Core\\Record\\Defaults\\AssignedUserPopulator';

$contactHooks = $metadata->get(['recordDefs', 'Contact', 'beforeCreateHookClassNameList']) ?? [];
$ok('Contact beforeCreate hook registered', in_array($hook, $contactHooks, true));
$ok(
    'Contact defaults populator registered',
    ($metadata->get(['recordDefs', 'Contact', 'defaultsPopulatorClassName']) ?? '') === $populator
);

$mealCountHooks = $metadata->get(['recordDefs', 'MealCount', 'beforeCreateHookClassNameList']) ?? [];
$ok('MealCount beforeCreate hook registered', in_array($hook, $mealCountHooks, true));

$applier = $injectableFactory->create(AssignedUserDefaultApplier::class);
$systemUserId = $container->get('user')->getId();

$applierContact = $em->getNewEntity('Contact');
$applierContact->set([
    'firstName' => 'Assigned',
    'lastName' => 'Applier' . date('His'),
]);
$applier->applyIfEmpty($applierContact);
$ok('applier sets assignedUser on empty Contact', $applierContact->get('assignedUserId') === $systemUserId);

$applierContact->set([
    'assignedUserId' => 'some-other-user-id',
    'assignedUserName' => 'Other User',
]);
$applier->applyIfEmpty($applierContact);
$ok('applier keeps explicit assignedUser', $applierContact->get('assignedUserId') === 'some-other-user-id');

$contact = $em->getNewEntity('Contact');
$contact->set([
    'firstName' => 'Assigned',
    'lastName' => 'Default' . date('His'),
]);
try {
    $em->saveEntity($contact);
    $ok('Contact create persists assignedUser', $contact->get('assignedUserId') === $systemUserId);
} catch (Throwable $e) {
    $ok('Contact create persists assignedUser', false, $e->getMessage());
}

if ($contact->getId()) {
    $em->removeEntity($contact);
}

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
