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
$ok('stream disabled', ($metadata->get(['scopes', 'FoodParcelRegistration', 'stream']) ?? true) === false);
$ok('contact required', ($metadata->get(['entityDefs', 'FoodParcelRegistration', 'fields', 'contact', 'required']) ?? false) === true);
$ok('entryDates array field', $metadata->get(['entityDefs', 'FoodParcelRegistration', 'fields', 'entryDates', 'type']) === 'array');
$ok('exitDates array field', $metadata->get(['entityDefs', 'FoodParcelRegistration', 'fields', 'exitDates', 'type']) === 'array');
$ok('date-array view', str_contains(
    (string) $metadata->get(['entityDefs', 'FoodParcelRegistration', 'fields', 'entryDates', 'view']),
    'date-array'
));

$pdfService = $injectableFactory->create(FoodParcelPdfService::class);
$pdfService->provisionTemplate();

$template = $em->getRDBRepository('Template')
    ->where([
        'entityType' => 'FoodParcelRegistration',
        'name' => FoodParcelPdfService::TEMPLATE_NAME,
    ])
    ->findOne();
$templateBody = $template ? (string) $template->get('body') : '';
$ok('PDF template handlebars syntax', str_contains($templateBody, '{{{entryDatesText}}}') && str_contains($templateBody, '{{{exitDatesText}}}'));
$ok('PDF template provisioned', $template !== null);

$contact = $em->getNewEntity('Contact');
$contact->set([
    'firstName' => 'Smoke',
    'lastName' => 'FoodParcel' . date('His'),
]);
$em->saveEntity($contact);

$today = date('Y-m-d');
$registration = $em->getNewEntity('FoodParcelRegistration');
$registration->set([
    'contactId' => $contact->getId(),
    'taxCode' => 'SMKFP0000000000',
    'household' => 3,
    'notes' => 'SMOKE food parcel',
    'entryDates' => [$today],
    'exitDates' => [$today],
]);
$em->saveEntity($registration);
$ok('registration create', $registration->getId() !== null);

$registration = $em->getEntityById('FoodParcelRegistration', $registration->getId());
$entryText = (string) $registration->get('entryDatesText');
$exitText = (string) $registration->get('exitDatesText');
$displayToday = date('d.m.Y', strtotime($today));
$ok('entryDatesText synced', str_contains($entryText, $displayToday));
$ok('exitDatesText synced', str_contains($exitText, $displayToday));

if ($registration->getId()) {
    try {
        $pdf = $pdfService->generateForRecord($registration->getId());
        $ok('PDF generate', str_starts_with($pdf['contents'], '%PDF'));
    } catch (Throwable $e) {
        $ok('PDF generate', false, $e->getMessage());
    }

    $em->removeEntity($registration);
}

$em->removeEntity($contact);

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
