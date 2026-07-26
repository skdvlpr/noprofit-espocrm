<?php

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\EmailAddress as EmailAddressEntity;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Repositories\EmailAddress as EmailAddressRepository;

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    echo '  [' . ($pass ? 'PASS' : 'FAIL') . '] ' . $name . ($detail !== '' ? " — $detail" : '') . "\n";
};

$primaNotaBase = static function (array $extra) use ($em): \Espo\ORM\Entity {
    $row = $em->getNewEntity('PrimaNota');
    $row->set(array_merge([
        'entryType' => 'Income',
        'amountGross' => 10.0,
        'amountGrossCurrency' => 'EUR',
        'transactionDate' => date('Y-m-d'),
        'donationPaymentProvider' => 'Other',
    ], $extra));

    return $row;
};

$account = $em->getNewEntity(Account::ENTITY_TYPE);
$account->set('name', 'Smoke Subject Account');
$em->saveEntity($account);

$contact = $em->getNewEntity(Contact::ENTITY_TYPE);
$contact->set('firstName', 'Smoke');
$contact->set('lastName', 'Subject');
$em->saveEntity($contact);

$beneficiaryAccount = $em->getNewEntity(Account::ENTITY_TYPE);
$beneficiaryAccount->set('name', 'Smoke Beneficiary Account');
$em->saveEntity($beneficiaryAccount);

$beneficiaryContact = $em->getNewEntity(Contact::ENTITY_TYPE);
$beneficiaryContact->set('firstName', 'Smoke');
$beneficiaryContact->set('lastName', 'Beneficiary');
$em->saveEntity($beneficiaryContact);

$linkedAccount = $primaNotaBase([
    'description' => 'Smoke linked account subject',
    'amountGross' => 10.0,
    'subjectPartyId' => $account->getId(),
    'subjectPartyType' => Account::ENTITY_TYPE,
]);
$em->saveEntity($linkedAccount);
$ok(
    'subjectName from account',
    $linkedAccount->get('subjectName') === 'Smoke Subject Account',
    (string) $linkedAccount->get('subjectName')
);

$linkedContact = $primaNotaBase([
    'description' => 'Smoke linked contact subject',
    'amountGross' => 11.0,
    'subjectPartyId' => $contact->getId(),
    'subjectPartyType' => Contact::ENTITY_TYPE,
]);
$em->saveEntity($linkedContact);
$ok(
    'subjectName from contact',
    $linkedContact->get('subjectName') === 'Smoke Subject',
    (string) $linkedContact->get('subjectName')
);

$createdAccount = $primaNotaBase([
    'description' => 'Smoke create account subject',
    'amountGross' => 12.0,
    'subjectName' => 'New Smoke Org',
    'subjectEmailAddress' => 'org-smoke@example.com',
    'subjectPhoneNumber' => '+390555501111',
    'createSubjectAccount' => true,
]);
$em->saveEntity($createdAccount);
$ok(
    'create account link',
    $createdAccount->get('subjectPartyType') === Account::ENTITY_TYPE
        && $createdAccount->get('subjectName') === 'New Smoke Org',
    (string) $createdAccount->get('subjectPartyId')
);
$createdAccountParty = $em->getEntityById(Account::ENTITY_TYPE, (string) $createdAccount->get('subjectPartyId'));
$ok(
    'create account copies email/phone',
    $createdAccountParty !== null
        && $createdAccountParty->get('emailAddress') === 'org-smoke@example.com'
        && $createdAccountParty->get('phoneNumber') === '+390555501111'
);

$createdContactEmail = 'smoke-prima-nota-subject-' . bin2hex(random_bytes(4)) . '@example.com';
$createdContactPhone = '+3906' . random_int(1000000, 9999999);
$createdContact = $primaNotaBase([
    'description' => 'Smoke create contact subject',
    'amountGross' => 13.0,
    'subjectName' => 'Anna Verdi',
    'subjectEmailAddress' => $createdContactEmail,
    'subjectPhoneNumber' => $createdContactPhone,
    'createSubjectContact' => true,
]);
$em->saveEntity($createdContact);
$ok(
    'create contact link',
    $createdContact->get('subjectPartyType') === Contact::ENTITY_TYPE
        && $createdContact->get('subjectName') === 'Anna Verdi',
    (string) $createdContact->get('subjectPartyId')
);
$createdContactParty = $em->getEntityById(Contact::ENTITY_TYPE, (string) $createdContact->get('subjectPartyId'));
$ok(
    'create contact copies email/phone',
    $createdContactParty !== null
        && $createdContactParty->get('emailAddress') === $createdContactEmail
        && $createdContactParty->get('phoneNumber') === $createdContactPhone
);

/** @var EmailAddressRepository $emailAddressRepository */
$emailAddressRepository = $em->getRepository(EmailAddressEntity::ENTITY_TYPE);
$contactByEmail = $emailAddressRepository->getEntityByAddress(
    $createdContactEmail,
    Contact::ENTITY_TYPE
);
$ok(
    'created contact stores email relation (not dropped by SKIP_ALL)',
    $contactByEmail !== null
        && $contactByEmail->getId() === $createdContact->get('subjectPartyId'),
    $contactByEmail ? 'id=' . $contactByEmail->getId() : 'email relation missing'
);

$reuseContact = $primaNotaBase([
    'description' => 'Smoke reuse contact by email',
    'amountGross' => 13.5,
    'subjectName' => 'Different Name Should Not Duplicate',
    'subjectEmailAddress' => $createdContactEmail,
    'createSubjectContact' => true,
]);
$em->saveEntity($reuseContact);
$ok(
    'create with existing email reuses Contact instead of duplicating',
    $reuseContact->get('subjectPartyId') === $createdContact->get('subjectPartyId'),
    (string) $reuseContact->get('subjectPartyId')
);

// Regression: phone-matched Contact with missing email must persist backfilled email
// (SKIP_ALL previously skipped EmailAddress FieldProcessing afterSave).
$phoneOnlyContactPhone = '+3907' . random_int(1000000, 9999999);
$phoneOnlyContactEmail = 'smoke-prima-nota-backfill-' . bin2hex(random_bytes(4)) . '@example.com';
$phoneOnlyContact = $em->getNewEntity(Contact::ENTITY_TYPE);
$phoneOnlyContact->set([
    'firstName' => 'Phone',
    'lastName' => 'Only',
    'phoneNumber' => $phoneOnlyContactPhone,
]);
$em->saveEntity($phoneOnlyContact);

$backfillRow = $primaNotaBase([
    'description' => 'Smoke phone match email backfill',
    'amountGross' => 13.75,
    'subjectName' => 'Phone Only Should Backfill Email',
    'subjectEmailAddress' => $phoneOnlyContactEmail,
    'subjectPhoneNumber' => $phoneOnlyContactPhone,
    'createSubjectContact' => true,
]);
$em->saveEntity($backfillRow);
$ok(
    'phone match reuses existing Contact',
    $backfillRow->get('subjectPartyId') === $phoneOnlyContact->getId(),
    (string) $backfillRow->get('subjectPartyId')
);
$backfilledByEmail = $emailAddressRepository->getEntityByAddress(
    $phoneOnlyContactEmail,
    Contact::ENTITY_TYPE
);
$ok(
    'phone-match backfill persists email relation (not SKIP_ALL)',
    $backfilledByEmail !== null
        && $backfilledByEmail->getId() === $phoneOnlyContact->getId(),
    $backfilledByEmail ? 'id=' . $backfilledByEmail->getId() : 'email relation missing after backfill'
);

$manual = $primaNotaBase([
    'description' => 'Smoke manual subject',
    'entryType' => 'Expense',
    'amountGross' => 14.0,
    'subjectName' => 'Manual Payer',
]);
$em->saveEntity($manual);
$ok(
    'manual subjectName preserved',
    $manual->get('subjectName') === 'Manual Payer'
        && !$manual->get('subjectPartyId'),
    (string) $manual->get('subjectName')
);

$linkedBeneficiaryAccount = $primaNotaBase([
    'description' => 'Smoke linked account beneficiary',
    'entryType' => 'Expense',
    'amountGross' => 15.0,
    'beneficiaryPartyId' => $beneficiaryAccount->getId(),
    'beneficiaryPartyType' => Account::ENTITY_TYPE,
]);
$em->saveEntity($linkedBeneficiaryAccount);
$ok(
    'beneficiaryName from account',
    $linkedBeneficiaryAccount->get('beneficiaryName') === 'Smoke Beneficiary Account',
    (string) $linkedBeneficiaryAccount->get('beneficiaryName')
);

$linkedBeneficiaryContact = $primaNotaBase([
    'description' => 'Smoke linked contact beneficiary',
    'entryType' => 'Expense',
    'amountGross' => 16.0,
    'beneficiaryPartyId' => $beneficiaryContact->getId(),
    'beneficiaryPartyType' => Contact::ENTITY_TYPE,
]);
$em->saveEntity($linkedBeneficiaryContact);
$ok(
    'beneficiaryName from contact',
    $linkedBeneficiaryContact->get('beneficiaryName') === 'Smoke Beneficiary',
    (string) $linkedBeneficiaryContact->get('beneficiaryName')
);

$createdBeneficiaryAccount = $primaNotaBase([
    'description' => 'Smoke create account beneficiary',
    'entryType' => 'Expense',
    'amountGross' => 17.0,
    'beneficiaryName' => 'New Beneficiary Org',
    'beneficiaryEmailAddress' => 'beneficiary-org@example.com',
    'beneficiaryPhoneNumber' => '+390555503333',
    'createBeneficiaryAccount' => true,
]);
$em->saveEntity($createdBeneficiaryAccount);
$ok(
    'create beneficiary account link',
    $createdBeneficiaryAccount->get('beneficiaryPartyType') === Account::ENTITY_TYPE
        && $createdBeneficiaryAccount->get('beneficiaryName') === 'New Beneficiary Org',
    (string) $createdBeneficiaryAccount->get('beneficiaryPartyId')
);
$createdBeneficiaryAccountParty = $em->getEntityById(
    Account::ENTITY_TYPE,
    (string) $createdBeneficiaryAccount->get('beneficiaryPartyId')
);
$ok(
    'create beneficiary account copies email/phone',
    $createdBeneficiaryAccountParty !== null
        && $createdBeneficiaryAccountParty->get('emailAddress') === 'beneficiary-org@example.com'
        && $createdBeneficiaryAccountParty->get('phoneNumber') === '+390555503333'
);

$createdBeneficiaryContact = $primaNotaBase([
    'description' => 'Smoke create contact beneficiary',
    'entryType' => 'Expense',
    'amountGross' => 18.0,
    'beneficiaryName' => 'Luca Bianchi',
    'beneficiaryEmailAddress' => 'luca.bianchi@example.com',
    'beneficiaryPhoneNumber' => '+390555504444',
    'createBeneficiaryContact' => true,
]);
$em->saveEntity($createdBeneficiaryContact);
$ok(
    'create beneficiary contact link',
    $createdBeneficiaryContact->get('beneficiaryPartyType') === Contact::ENTITY_TYPE
        && $createdBeneficiaryContact->get('beneficiaryName') === 'Luca Bianchi',
    (string) $createdBeneficiaryContact->get('beneficiaryPartyId')
);
$createdBeneficiaryContactParty = $em->getEntityById(
    Contact::ENTITY_TYPE,
    (string) $createdBeneficiaryContact->get('beneficiaryPartyId')
);
$ok(
    'create beneficiary contact copies email/phone',
    $createdBeneficiaryContactParty !== null
        && $createdBeneficiaryContactParty->get('emailAddress') === 'luca.bianchi@example.com'
        && $createdBeneficiaryContactParty->get('phoneNumber') === '+390555504444'
);

$manualBeneficiary = $primaNotaBase([
    'description' => 'Smoke manual beneficiary',
    'entryType' => 'Expense',
    'amountGross' => 19.0,
    'beneficiaryName' => 'Manual Beneficiary',
]);
$em->saveEntity($manualBeneficiary);
$ok(
    'manual beneficiaryName preserved',
    $manualBeneficiary->get('beneficiaryName') === 'Manual Beneficiary'
        && !$manualBeneficiary->get('beneficiaryPartyId'),
    (string) $manualBeneficiary->get('beneficiaryName')
);

$bothParties = $primaNotaBase([
    'description' => 'Smoke both parties',
    'entryType' => 'Expense',
    'amountGross' => 20.0,
    'subjectPartyId' => $account->getId(),
    'subjectPartyType' => Account::ENTITY_TYPE,
    'beneficiaryPartyId' => $beneficiaryContact->getId(),
    'beneficiaryPartyType' => Contact::ENTITY_TYPE,
]);
$em->saveEntity($bothParties);
$ok(
    'subject and beneficiary independent',
    $bothParties->get('subjectName') === 'Smoke Subject Account'
        && $bothParties->get('beneficiaryName') === 'Smoke Beneficiary',
    $bothParties->get('subjectName') . ' / ' . $bothParties->get('beneficiaryName')
);

$rows = [
    $linkedAccount,
    $linkedContact,
    $createdAccount,
    $createdContact,
    $reuseContact,
    $backfillRow,
    $manual,
    $linkedBeneficiaryAccount,
    $linkedBeneficiaryContact,
    $createdBeneficiaryAccount,
    $createdBeneficiaryContact,
    $manualBeneficiary,
    $bothParties,
];

foreach ($rows as $row) {
    $em->removeEntity($row);
}

$em->removeEntity($phoneOnlyContact);
$em->removeEntity($beneficiaryContact);
$em->removeEntity($beneficiaryAccount);
$em->removeEntity($contact);
$em->removeEntity($account);

if ($createdContactParty) {
    $em->removeEntity($createdContactParty);
}
if ($createdAccountParty) {
    $em->removeEntity($createdAccountParty);
}
if ($createdBeneficiaryContactParty) {
    $em->removeEntity($createdBeneficiaryContactParty);
}
if ($createdBeneficiaryAccountParty) {
    $em->removeEntity($createdBeneficiaryAccountParty);
}

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
