<?php

declare(strict_types=1);

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

$account = $em->getNewEntity(Account::ENTITY_TYPE);
$account->set('name', 'Smoke Subject Account');
$em->saveEntity($account);

$contact = $em->getNewEntity(Contact::ENTITY_TYPE);
$contact->set('firstName', 'Smoke');
$contact->set('lastName', 'Subject');
$em->saveEntity($contact);

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

$createdContact = $em->getNewEntity('PrimaNota');
$createdContact->set([
    'description' => 'Smoke create contact subject',
    'entryType' => 'Income',
    'amount' => 13,
    'transactionDate' => date('Y-m-d'),
    'subjectName' => 'Anna Verdi',
    'createSubjectContact' => true,
]);
$em->saveEntity($createdContact);
$ok(
    'create contact link',
    $createdContact->get('subjectPartyType') === Contact::ENTITY_TYPE
        && $createdContact->get('subjectName') === 'Anna Verdi',
    (string) $createdContact->get('subjectPartyId')
);

$manual = $em->getNewEntity('PrimaNota');
$manual->set([
    'description' => 'Smoke manual subject',
    'entryType' => 'Expense',
    'amount' => 14,
    'transactionDate' => date('Y-m-d'),
    'subjectName' => 'Manual Beneficiary',
]);
$em->saveEntity($manual);
$ok(
    'manual subjectName preserved',
    $manual->get('subjectName') === 'Manual Beneficiary'
        && !$manual->get('subjectPartyId'),
    (string) $manual->get('subjectName')
);

foreach ([$linkedAccount, $linkedContact, $createdAccount, $createdContact, $manual] as $row) {
    $em->removeEntity($row);
}

$em->removeEntity($contact);
$em->removeEntity($account);

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
