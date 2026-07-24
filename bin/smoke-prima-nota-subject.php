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
    'amount' => 10,
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
    'amount' => 11,
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
    'amount' => 12,
    'transactionDate' => date('Y-m-d'),
    'subjectName' => 'New Smoke Org',
    'createSubjectAccount' => true,
]);
$em->saveEntity($createdAccount);
$ok(
    'create account link',
    $createdAccount->get('subjectPartyType') === Account::ENTITY_TYPE
        && $createdAccount->get('subjectName') === 'New Smoke Org',
    (string) $createdAccount->get('subjectPartyId')
);

$createdContactEmail = 'smoke-prima-nota-subject-' . bin2hex(random_bytes(4)) . '@example.com';
$createdContactPhone = '+3906' . random_int(1000000, 9999999);
$createdContact = $em->getNewEntity('PrimaNota');
$createdContact->set([
    'description' => 'Smoke create contact subject',
    'entryType' => 'Income',
    'amount' => 13,
    'transactionDate' => date('Y-m-d'),
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

$createdContactEntity = $em->getEntityById(
    Contact::ENTITY_TYPE,
    (string) $createdContact->get('subjectPartyId')
);
/** @var EmailAddressRepository $emailAddressRepository */
$emailAddressRepository = $em->getRepository(EmailAddressEntity::ENTITY_TYPE);
$contactByEmail = $emailAddressRepository->getEntityByAddress(
    $createdContactEmail,
    Contact::ENTITY_TYPE
);
$ok(
    'created contact stores email (not dropped by SKIP_ALL)',
    $contactByEmail !== null
        && $contactByEmail->getId() === $createdContact->get('subjectPartyId'),
    $contactByEmail ? 'id=' . $contactByEmail->getId() : 'email relation missing'
);

$reuseContact = $em->getNewEntity('PrimaNota');
$reuseContact->set([
    'description' => 'Smoke reuse contact by email',
    'entryType' => 'Income',
    'amount' => 13.5,
    'transactionDate' => date('Y-m-d'),
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

$manual = $em->getNewEntity('PrimaNota');
$manual->set([
    'description' => 'Smoke manual subject',
    'entryType' => 'Expense',
    'amount' => 14,
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
    'amount' => 15,
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
    'amount' => 16,
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
    'amount' => 17,
    'transactionDate' => date('Y-m-d'),
    'beneficiaryName' => 'New Beneficiary Org',
    'createBeneficiaryAccount' => true,
]);
$em->saveEntity($createdBeneficiaryAccount);
$ok(
    'create beneficiary account link',
    $createdBeneficiaryAccount->get('beneficiaryPartyType') === Account::ENTITY_TYPE
        && $createdBeneficiaryAccount->get('beneficiaryName') === 'New Beneficiary Org',
    (string) $createdBeneficiaryAccount->get('beneficiaryPartyId')
);

$createdBeneficiaryContact = $em->getNewEntity('PrimaNota');
$createdBeneficiaryContact->set([
    'description' => 'Smoke create contact beneficiary',
    'entryType' => 'Expense',
    'amount' => 18,
    'transactionDate' => date('Y-m-d'),
    'beneficiaryName' => 'Luca Bianchi',
    'createBeneficiaryContact' => true,
]);
$em->saveEntity($createdBeneficiaryContact);
$ok(
    'create beneficiary contact link',
    $createdBeneficiaryContact->get('beneficiaryPartyType') === Contact::ENTITY_TYPE
        && $createdBeneficiaryContact->get('beneficiaryName') === 'Luca Bianchi',
    (string) $createdBeneficiaryContact->get('beneficiaryPartyId')
);

$manualBeneficiary = $em->getNewEntity('PrimaNota');
$manualBeneficiary->set([
    'description' => 'Smoke manual beneficiary',
    'entryType' => 'Expense',
    'amount' => 19,
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
    'amount' => 20,
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

$rows = [
    $linkedAccount,
    $linkedContact,
    $createdAccount,
    $createdContact,
    $reuseContact,
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

if ($createdContactEntity) {
    $em->removeEntity($createdContactEntity);
}

$createdAccountEntity = $em->getEntityById(
    Account::ENTITY_TYPE,
    (string) $createdAccount->get('subjectPartyId')
);
if ($createdAccountEntity) {
    $em->removeEntity($createdAccountEntity);
}

$createdBeneficiaryAccountEntity = $em->getEntityById(
    Account::ENTITY_TYPE,
    (string) $createdBeneficiaryAccount->get('beneficiaryPartyId')
);
if ($createdBeneficiaryAccountEntity) {
    $em->removeEntity($createdBeneficiaryAccountEntity);
}

$createdBeneficiaryContactEntity = $em->getEntityById(
    Contact::ENTITY_TYPE,
    (string) $createdBeneficiaryContact->get('beneficiaryPartyId')
);
if ($createdBeneficiaryContactEntity) {
    $em->removeEntity($createdBeneficiaryContactEntity);
}

$em->removeEntity($beneficiaryContact);
$em->removeEntity($beneficiaryAccount);
$em->removeEntity($contact);
$em->removeEntity($account);

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
