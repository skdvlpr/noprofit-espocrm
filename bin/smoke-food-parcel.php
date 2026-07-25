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
use Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\FoodParcelContactSync;
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
$ok('stream enabled', ($metadata->get(['scopes', 'FoodParcelRegistration', 'stream']) ?? false) === true);
$ok('OCC disabled for volunteer edits', ($metadata->get(['entityDefs', 'FoodParcelRegistration', 'optimisticConcurrencyControl']) ?? true) === false);
$entryDatesField = $metadata->get(['entityDefs', 'FoodParcelRegistration', 'fields', 'entryDates']) ?? [];
$exitDatesField = $metadata->get(['entityDefs', 'FoodParcelRegistration', 'fields', 'exitDates']) ?? [];
$ok(
    'entryDates has no optimisticConcurrencyControlIgnore',
    is_array($entryDatesField) && !array_key_exists('optimisticConcurrencyControlIgnore', $entryDatesField)
);
$ok(
    'exitDates has no optimisticConcurrencyControlIgnore',
    is_array($exitDatesField) && !array_key_exists('optimisticConcurrencyControlIgnore', $exitDatesField)
);
$ok('contact required', ($metadata->get(['entityDefs', 'FoodParcelRegistration', 'fields', 'contact', 'required']) ?? false) === true);
$ok('taxCode readOnly on registration', ($metadata->get(['entityDefs', 'FoodParcelRegistration', 'fields', 'taxCode', 'readOnly']) ?? false) === true);
$ok('phone readOnly on registration', ($metadata->get(['entityDefs', 'FoodParcelRegistration', 'fields', 'phone', 'readOnly']) ?? false) === true);
$ok('address readOnly on registration', ($metadata->get(['entityDefs', 'FoodParcelRegistration', 'fields', 'address', 'readOnly']) ?? false) === true);
$ok('Contact taxCode field', $metadata->get(['entityDefs', 'Contact', 'fields', 'taxCode', 'type']) === 'varchar');
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
$ok('PDF template uses Indirizzo label', str_contains($templateBody, 'Indirizzo:'));
$ok('PDF template uses phonePdf block', str_contains($templateBody, '{{{phonePdf}}}'));
$ok('PDF template uses notesPdf block', str_contains($templateBody, '{{{notesPdf}}}'));
$ok('PDF template provisioned', $template !== null);

$contact = $em->getNewEntity('Contact');
$contact->set([
    'firstName' => 'Smoke',
    'lastName' => 'FoodParcel' . date('His'),
    'taxCode' => 'PLLLRT80A01H501Z',
    'phoneNumber' => '+39 055 1234567',
    'phoneNumberData' => [
        (object) [
            'phoneNumber' => '+39 055 1234567',
            'type' => 'Mobile',
            'primary' => true,
            'optOut' => false,
            'invalid' => false,
        ],
        (object) [
            'phoneNumber' => '+39 055 7654321',
            'type' => 'Mobile',
            'primary' => false,
            'optOut' => false,
            'invalid' => false,
        ],
    ],
    'addressStreet' => 'Via Smoke 12',
    'addressCity' => 'Firenze',
    'addressPostalCode' => '50100',
    'addressCountry' => 'Italy',
]);
$em->saveEntity($contact);

$today = date('Y-m-d');
$registration = $em->getNewEntity('FoodParcelRegistration');
$registration->set([
    'contactId' => $contact->getId(),
    'household' => 3,
    'notes' => 'SMOKE food parcel<br />second line',
    'entryDates' => [$today],
    'exitDates' => [$today],
]);
$em->saveEntity($registration);
$ok('registration create', $registration->getId() !== null);

$registration = $em->getEntityById('FoodParcelRegistration', $registration->getId());
$ok('taxCode synced from contact', $registration->get('taxCode') === 'PLLLRT80A01H501Z');
$phoneText = (string) $registration->get('phone');
$ok('phone synced all contact numbers', str_contains($phoneText, '+39 055 1234567') && str_contains($phoneText, '+39 055 7654321'));
$ok('addressStreet synced from contact', $registration->get('addressStreet') === 'Via Smoke 12');
$ok('addressLine synced from contact', str_contains((string) $registration->get('addressLine'), 'Via Smoke 12'));
$ok('phonePdf synced from contact', str_contains((string) $registration->get('phonePdf'), '+39 055 7654321'));
$notesPdf = (string) $registration->get('notesPdf');
$ok('notesPdf strips raw br tag', !str_contains($notesPdf, '<br />') && !str_contains($notesPdf, '&lt;br'));
$ok('notesPdf keeps note text', str_contains($notesPdf, 'SMOKE food parcel') && str_contains($notesPdf, 'second line'));
$ok('notesPdf uses compact div layout', str_contains($notesPdf, 'line-height:1.35') && !str_contains($notesPdf, '<br'));

$entryText = (string) $registration->get('entryDatesText');
$exitText = (string) $registration->get('exitDatesText');
$displayToday = date('d.m.Y', strtotime($today));
$ok('entryDatesText synced', str_contains($entryText, $displayToday));
$ok('exitDatesText synced', str_contains($exitText, $displayToday));

$contact->set([
    'phoneNumberData' => [
        (object) [
            'phoneNumber' => '+39 055 1234567',
            'type' => 'Mobile',
            'primary' => true,
            'optOut' => false,
            'invalid' => false,
        ],
        (object) [
            'phoneNumber' => '+39 055 9999999',
            'type' => 'Office',
            'primary' => false,
            'optOut' => false,
            'invalid' => false,
        ],
    ],
    'addressCity' => 'Prato',
]);
$em->saveEntity($contact);

$registration = $em->getEntityById('FoodParcelRegistration', $registration->getId());
$phoneText = (string) $registration->get('phone');
$ok('registration phones updated after contact save', str_contains($phoneText, '+39 055 9999999'));
$ok('registration city updated after contact save', $registration->get('addressCity') === 'Prato');

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
