<?php
/**
 * Smoke test for FoodParcelRegistration + PDF template (post-launch epic C).
 *
 * Usage: ddev exec php bin/smoke-food-parcel.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Metadata;
use Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\FoodParcelPdfService;

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

echo "FoodParcel metadata\n";
$ok('FoodParcelRegistration scopes.entity', ($metadata->get(['scopes', 'FoodParcelRegistration', 'entity']) ?? false) === true);
$ok('contact required', ($metadata->get(['entityDefs', 'FoodParcelRegistration', 'fields', 'contact', 'required']) ?? false) === true);
$ok('address field', $metadata->get(['entityDefs', 'FoodParcelRegistration', 'fields', 'address', 'type']) === 'address');
$ok('FoodParcelDateLog link', $metadata->get(['entityDefs', 'FoodParcelDateLog', 'fields', 'foodParcelRegistration', 'type']) === 'link');

$pdfService = $injectableFactory->create(FoodParcelPdfService::class);
$pdfService->provisionTemplate();
$ok('PDF template provisioned', true);

$contact = $em->getNewEntity('Contact');
$contact->set([
    'firstName' => 'Smoke',
    'lastName' => 'FoodParcel' . date('His'),
]);
$em->saveEntity($contact);

$registration = $em->getNewEntity('FoodParcelRegistration');
$registration->set([
    'contactId' => $contact->getId(),
    'taxCode' => 'SMKFP0000000000',
    'notes' => 'SMOKE food parcel',
]);
$em->saveEntity($registration);
$ok('registration create', $registration->getId() !== null);

$log = $em->getNewEntity('FoodParcelDateLog');
$log->set([
    'foodParcelRegistrationId' => $registration->getId(),
    'entryDate' => date('Y-m-d'),
]);
$em->saveEntity($log);
$ok('date log create', $log->getId() !== null);

$registration = $em->getEntityById('FoodParcelRegistration', $registration->getId());
$ok('dateLogsText synced', is_string($registration->get('dateLogsText')) && str_contains((string) $registration->get('dateLogsText'), date('Y-m-d')));

if ($registration->getId()) {
    try {
        $pdf = $pdfService->generateForRecord($registration->getId());
        $ok('PDF generate', str_starts_with($pdf['contents'], '%PDF'));
    } catch (Throwable $e) {
        $ok('PDF generate', false, $e->getMessage());
    }

    $em->removeEntity($log);
    $em->removeEntity($registration);
}

$em->removeEntity($contact);

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
