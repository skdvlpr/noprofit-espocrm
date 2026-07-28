<?php

declare(strict_types=1);


require __DIR__ . '/lib/refuse-production.php';


include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Contact;

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

$metadata = $app->getContainer()->get('metadata');
$ok(
    'Contact.relatedPayments link',
    ($metadata->get(['entityDefs', 'Contact', 'links', 'relatedPayments', 'entity']) === 'PrimaNota')
        && ($metadata->get(['entityDefs', 'Contact', 'links', 'relatedPayments', 'foreign']) === 'subjectParty')
);
$ok(
    'Account.relatedPayments link',
    ($metadata->get(['entityDefs', 'Account', 'links', 'relatedPayments', 'entity']) === 'PrimaNota')
        && ($metadata->get(['entityDefs', 'Account', 'links', 'relatedPayments', 'foreign']) === 'subjectParty')
);
$ok(
    'PrimaNota.subjectParty foreign',
    $metadata->get(['entityDefs', 'PrimaNota', 'links', 'subjectParty', 'foreign']) === 'relatedPayments'
);
$ok(
    'PrimaNota.beneficiaryParty foreign',
    $metadata->get(['entityDefs', 'PrimaNota', 'links', 'beneficiaryParty', 'foreign']) === 'relatedPaymentsAsBeneficiary'
);
$ok(
    'Contact bottomPanelsDetail layout module registered',
    $metadata->get(['app', 'layouts', 'Contact', 'bottomPanelsDetail', 'module']) === 'NonprofitEspocrm'
);
$ok(
    'PrimaNota textFilter includes donationPaymentReference',
    in_array(
        'donationPaymentReference',
        $metadata->get(['entityDefs', 'PrimaNota', 'collection', 'textFilterFields']) ?? [],
        true
    )
);

$layoutProvider = $app->getContainer()->get('injectableFactory')
    ->create(\Espo\Tools\Layout\LayoutProvider::class);
$contactBottom = json_decode((string) $layoutProvider->get('Contact', 'bottomPanelsDetail'), true);
$ok(
    'Contact bottomPanelsDetail includes relatedPayments',
    is_array($contactBottom) && isset($contactBottom['relatedPayments']),
    is_string($layoutProvider->get('Contact', 'bottomPanelsDetail'))
        ? substr($layoutProvider->get('Contact', 'bottomPanelsDetail'), 0, 80)
        : gettype($contactBottom)
);

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

$linkedAccount = $em->getNewEntity('PrimaNota');
$linkedAccount->set([
    'description' => 'Smoke linked account subject',
    'entryType' => 'Income',
    'amountGross' => 10,
    'amountGrossCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
    'subjectPartyId' => $account->getId(),
    'subjectPartyType' => Account::ENTITY_TYPE,
]);
$em->saveEntity($linkedAccount);
$ok(
    'subjectName from account',
    $linkedAccount->get('subjectName') === 'Smoke Subject Account',
    (string) $linkedAccount->get('subjectName')
);

$linkedContact = $em->getNewEntity('PrimaNota');
$linkedContact->set([
    'description' => 'Smoke linked contact subject',
    'entryType' => 'Income',
    'amountGross' => 11,
    'amountGrossCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
    'subjectPartyId' => $contact->getId(),
    'subjectPartyType' => Contact::ENTITY_TYPE,
]);
$em->saveEntity($linkedContact);
$ok(
    'subjectName from contact',
    $linkedContact->get('subjectName') === 'Smoke Subject',
    (string) $linkedContact->get('subjectName')
);

$createdAccount = $em->getNewEntity('PrimaNota');
$createdAccount->set([
    'description' => 'Smoke create account subject',
    'entryType' => 'Income',
    'amountGross' => 12,
    'amountGrossCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
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

$createdContact = $em->getNewEntity('PrimaNota');
$createdContact->set([
    'description' => 'Smoke create contact subject',
    'entryType' => 'Income',
    'amountGross' => 13,
    'amountGrossCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
    'subjectName' => 'Anna Verdi',
    'subjectEmailAddress' => 'anna.verdi@example.com',
    'subjectPhoneNumber' => '+390555502222',
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
        && $createdContactParty->get('emailAddress') === 'anna.verdi@example.com'
        && $createdContactParty->get('phoneNumber') === '+390555502222'
);

$manual = $em->getNewEntity('PrimaNota');
$manual->set([
    'description' => 'Smoke manual subject',
    'entryType' => 'Expense',
    'amountGross' => 14,
    'amountGrossCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
    'subjectName' => 'Manual Payer',
]);
$em->saveEntity($manual);
$ok(
    'manual subjectName preserved',
    $manual->get('subjectName') === 'Manual Payer'
        && !$manual->get('subjectPartyId'),
    (string) $manual->get('subjectName')
);

$linkedBeneficiaryAccount = $em->getNewEntity('PrimaNota');
$linkedBeneficiaryAccount->set([
    'description' => 'Smoke linked account beneficiary',
    'entryType' => 'Expense',
    'amountGross' => 15,
    'amountGrossCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
    'beneficiaryPartyId' => $beneficiaryAccount->getId(),
    'beneficiaryPartyType' => Account::ENTITY_TYPE,
]);
$em->saveEntity($linkedBeneficiaryAccount);
$ok(
    'beneficiaryName from account',
    $linkedBeneficiaryAccount->get('beneficiaryName') === 'Smoke Beneficiary Account',
    (string) $linkedBeneficiaryAccount->get('beneficiaryName')
);

$linkedBeneficiaryContact = $em->getNewEntity('PrimaNota');
$linkedBeneficiaryContact->set([
    'description' => 'Smoke linked contact beneficiary',
    'entryType' => 'Expense',
    'amountGross' => 16,
    'amountGrossCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
    'beneficiaryPartyId' => $beneficiaryContact->getId(),
    'beneficiaryPartyType' => Contact::ENTITY_TYPE,
]);
$em->saveEntity($linkedBeneficiaryContact);
$ok(
    'beneficiaryName from contact',
    $linkedBeneficiaryContact->get('beneficiaryName') === 'Smoke Beneficiary',
    (string) $linkedBeneficiaryContact->get('beneficiaryName')
);

$createdBeneficiaryAccount = $em->getNewEntity('PrimaNota');
$createdBeneficiaryAccount->set([
    'description' => 'Smoke create account beneficiary',
    'entryType' => 'Expense',
    'amountGross' => 17,
    'amountGrossCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
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

$createdBeneficiaryContact = $em->getNewEntity('PrimaNota');
$createdBeneficiaryContact->set([
    'description' => 'Smoke create contact beneficiary',
    'entryType' => 'Expense',
    'amountGross' => 18,
    'amountGrossCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
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

$manualBeneficiary = $em->getNewEntity('PrimaNota');
$manualBeneficiary->set([
    'description' => 'Smoke manual beneficiary',
    'entryType' => 'Expense',
    'amountGross' => 19,
    'amountGrossCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
    'beneficiaryName' => 'Manual Beneficiary',
]);
$em->saveEntity($manualBeneficiary);
$ok(
    'manual beneficiaryName preserved',
    $manualBeneficiary->get('beneficiaryName') === 'Manual Beneficiary'
        && !$manualBeneficiary->get('beneficiaryPartyId'),
    (string) $manualBeneficiary->get('beneficiaryName')
);

$bothParties = $em->getNewEntity('PrimaNota');
$bothParties->set([
    'description' => 'Smoke both parties',
    'entryType' => 'Expense',
    'amountGross' => 20,
    'amountGrossCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
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


$relatedFromContact = $em->getRDBRepository(Contact::ENTITY_TYPE)
    ->getRelation($contact, 'relatedPayments')
    ->find();
$ok(
    'Contact.relatedPayments relation returns linked PrimaNota',
    count($relatedFromContact) >= 1
);

$relatedFromAccount = $em->getRDBRepository(Account::ENTITY_TYPE)
    ->getRelation($account, 'relatedPayments')
    ->find();
$ok(
    'Account.relatedPayments relation returns linked PrimaNota',
    count($relatedFromAccount) >= 1
);

$rows = [
    $linkedAccount,
    $linkedContact,
    $createdAccount,
    $createdContact,
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

$em->removeEntity($beneficiaryContact);
$em->removeEntity($beneficiaryAccount);
$em->removeEntity($contact);
$em->removeEntity($account);

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
