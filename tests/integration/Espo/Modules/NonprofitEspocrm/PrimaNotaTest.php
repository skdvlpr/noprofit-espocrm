<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\Crm\Entities\Account;
use Espo\Modules\Crm\Entities\Contact;
use Espo\ORM\Repository\Option\SaveOption;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Prima Nota hooks: ValidateAmounts, SubjectParty, Stripe protection, DonationPresentation.
 * Ported from bin/smoke-prima-nota-subject.php and bin/smoke-prima-nota-stripe-commission.php.
 */
class PrimaNotaTest extends SafehouseBaseTestCase
{
    public function testValidateAmountsRejectsMissingGrossOnCreate(): void
    {
        $em = $this->getEntityManager();

        $this->assertBadRequest(function () use ($em): void {
            $row = $em->getNewEntity('PrimaNota');
            $row->set([
                'description' => 'PHPUnit no gross',
                'entryType' => 'Income',
                'amount' => 10.0,
                'amountCurrency' => 'EUR',
                'transactionDate' => date('Y-m-d'),
            ]);
            $em->saveEntity($row);
        }, 'Gross amount', 'importo lordo', 'grossRequired');
    }

    public function testValidateAmountsRejectsCommissionExceedingGross(): void
    {
        $em = $this->getEntityManager();

        $this->assertBadRequest(function () use ($em): void {
            $row = $this->newPrimaNotaManual([
                'amountGross' => 10.0,
                'commissionAmount' => 20.0,
                'commissionAmountCurrency' => 'EUR',
            ]);
            $em->saveEntity($row);
        }, 'cannot exceed', 'non può superare', 'commissionExceedsGross');
    }

    public function testValidateAmountsRejectsMissingTransactionDate(): void
    {
        $em = $this->getEntityManager();

        $this->assertBadRequest(function () use ($em): void {
            $row = $em->getNewEntity('PrimaNota');
            $row->set([
                'description' => 'PHPUnit no date',
                'entryType' => 'Income',
                'internalClassification' => 'Other',
                'amountGross' => 10.0,
                'amountGrossCurrency' => 'EUR',
            ]);
            $em->saveEntity($row);
        }, 'transactionDateRequired', 'Date is required', "La data e'", 'movement date');
    }

    public function testValidateAmountsDerivesNetFromCommissionPercent(): void
    {
        $em = $this->getEntityManager();

        $row = $this->newPrimaNotaManual(['commissionPercent' => 2.9]);
        $em->saveEntity($row);

        $this->assertEqualsWithDelta(97.1, (float) $row->get('amount'), 0.001);
        $this->assertEqualsWithDelta(2.9, (float) $row->get('commissionAmount'), 0.001);
    }

    public function testSubjectPartySyncsNameFromLinkedAccount(): void
    {
        $em = $this->getEntityManager();

        $account = $em->getNewEntity(Account::ENTITY_TYPE);
        $account->set('name', 'PHPUnit Subject Account ' . $this->uniqueMarker());
        $em->saveEntity($account);

        $row = $em->getNewEntity('PrimaNota');
        $row->set([
            'description' => 'PHPUnit linked account subject',
            'entryType' => 'Income',
            'amountGross' => 10,
            'amountGrossCurrency' => 'EUR',
            'transactionDate' => date('Y-m-d'),
            'subjectPartyId' => $account->getId(),
            'subjectPartyType' => Account::ENTITY_TYPE,
        ]);
        $em->saveEntity($row);

        $this->assertSame($account->get('name'), $row->get('subjectName'));
    }

    public function testSubjectPartySyncsNameFromLinkedContact(): void
    {
        $em = $this->getEntityManager();

        $contact = $em->getNewEntity(Contact::ENTITY_TYPE);
        $contact->set([
            'firstName' => 'PHPUnit',
            'lastName' => 'Subject',
            'emailAddress' => $this->uniqueMarker() . '@example.com',
        ]);
        $em->saveEntity($contact);

        $row = $em->getNewEntity('PrimaNota');
        $row->set([
            'description' => 'PHPUnit linked contact subject',
            'entryType' => 'Income',
            'amountGross' => 11,
            'amountGrossCurrency' => 'EUR',
            'transactionDate' => date('Y-m-d'),
            'subjectPartyId' => $contact->getId(),
            'subjectPartyType' => Contact::ENTITY_TYPE,
        ]);
        $em->saveEntity($row);

        $this->assertSame('PHPUnit Subject', $row->get('subjectName'));
    }

    public function testSubjectPartyCreatesAccountFromFlags(): void
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $row = $em->getNewEntity('PrimaNota');
        $row->set([
            'description' => 'PHPUnit create account subject',
            'entryType' => 'Income',
            'amountGross' => 12,
            'amountGrossCurrency' => 'EUR',
            'transactionDate' => date('Y-m-d'),
            'subjectName' => 'New PHPUnit Org ' . $marker,
            'subjectEmailAddress' => 'org-' . $marker . '@example.com',
            'subjectPhoneNumber' => '+390555501111',
            'createSubjectAccount' => true,
        ]);
        $em->saveEntity($row);

        $this->assertSame(Account::ENTITY_TYPE, $row->get('subjectPartyType'));
        $this->assertNotEmpty($row->get('subjectPartyId'));

        $party = $em->getEntityById(Account::ENTITY_TYPE, (string) $row->get('subjectPartyId'));
        $this->assertNotNull($party);
        $this->assertSame('org-' . $marker . '@example.com', $party->get('emailAddress'));
        $this->assertSame('+390555501111', $party->get('phoneNumber'));
    }

    public function testSubjectPartyCreatesContactFromFlags(): void
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $row = $em->getNewEntity('PrimaNota');
        $row->set([
            'description' => 'PHPUnit create contact subject',
            'entryType' => 'Income',
            'amountGross' => 13,
            'amountGrossCurrency' => 'EUR',
            'transactionDate' => date('Y-m-d'),
            'subjectName' => 'Anna Verdi',
            'subjectEmailAddress' => 'anna-' . $marker . '@example.com',
            'subjectPhoneNumber' => '+390555502222',
            'createSubjectContact' => true,
        ]);
        $em->saveEntity($row);

        $this->assertSame(Contact::ENTITY_TYPE, $row->get('subjectPartyType'));

        $party = $em->getEntityById(Contact::ENTITY_TYPE, (string) $row->get('subjectPartyId'));
        $this->assertNotNull($party);
        $this->assertSame('anna-' . $marker . '@example.com', $party->get('emailAddress'));
    }

    public function testSubjectPartyPhoneMatchBackfillsEmailOnExistingContact(): void
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();
        $phone = '+39' . str_pad((string) (crc32($marker) % 1000000000), 9, '0', STR_PAD_LEFT);

        $contact = $em->getNewEntity(Contact::ENTITY_TYPE);
        $contact->set([
            'firstName' => 'Phone',
            'lastName' => 'Only ' . $marker,
            'phoneNumber' => $phone,
        ]);
        $em->saveEntity($contact);

        $this->assertSame($phone, $contact->get('phoneNumber'));
        $this->assertSame('', trim((string) ($contact->get('emailAddress') ?? '')));

        $row = $em->getNewEntity('PrimaNota');
        $row->set([
            'description' => 'PHPUnit phone-match email backfill',
            'entryType' => 'Income',
            'amountGross' => 15,
            'amountGrossCurrency' => 'EUR',
            'transactionDate' => date('Y-m-d'),
            'subjectName' => 'Phone Only ' . $marker,
            'subjectEmailAddress' => 'phone-backfill-' . $marker . '@example.com',
            'subjectPhoneNumber' => $phone,
            'createSubjectContact' => true,
        ]);
        $em->saveEntity($row);

        $this->assertSame($contact->getId(), $row->get('subjectPartyId'));

        $fresh = $em->getEntityById(Contact::ENTITY_TYPE, $contact->getId());
        $this->assertNotNull($fresh);
        $this->assertSame('phone-backfill-' . $marker . '@example.com', $fresh->get('emailAddress'));
        $this->assertSame($phone, $fresh->get('phoneNumber'));
    }

    public function testSubjectPartyPhoneMatchDoesNotOverwriteExistingEmail(): void
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();
        $phone = '+39' . str_pad((string) ((crc32($marker) + 1) % 1000000000), 9, '0', STR_PAD_LEFT);
        $existingEmail = 'keep-' . $marker . '@example.com';

        $contact = $em->getNewEntity(Contact::ENTITY_TYPE);
        $contact->set([
            'firstName' => 'Keep',
            'lastName' => 'Email ' . $marker,
            'emailAddress' => $existingEmail,
            'phoneNumber' => $phone,
        ]);
        $em->saveEntity($contact);

        $row = $em->getNewEntity('PrimaNota');
        $row->set([
            'description' => 'PHPUnit phone-match keep email',
            'entryType' => 'Income',
            'amountGross' => 16,
            'amountGrossCurrency' => 'EUR',
            'transactionDate' => date('Y-m-d'),
            'subjectName' => 'Keep Email ' . $marker,
            'subjectEmailAddress' => 'overwrite-' . $marker . '@example.com',
            'subjectPhoneNumber' => $phone,
            'createSubjectContact' => true,
        ]);
        $em->saveEntity($row);

        $this->assertSame($contact->getId(), $row->get('subjectPartyId'));

        $fresh = $em->getEntityById(Contact::ENTITY_TYPE, $contact->getId());
        $this->assertNotNull($fresh);
        $this->assertSame($existingEmail, $fresh->get('emailAddress'));
    }

    public function testSubjectPartyPreservesManualNameWithoutLink(): void
    {
        $em = $this->getEntityManager();

        $row = $em->getNewEntity('PrimaNota');
        $row->set([
            'description' => 'PHPUnit manual subject',
            'entryType' => 'Expense',
            'amountGross' => 14,
            'amountGrossCurrency' => 'EUR',
            'transactionDate' => date('Y-m-d'),
            'subjectName' => 'Manual Payer',
        ]);
        $em->saveEntity($row);

        $this->assertSame('Manual Payer', $row->get('subjectName'));
        $this->assertEmpty($row->get('subjectPartyId'));
    }

    public function testDonationPresentationPrefillsDescriptionForOnlineDonation(): void
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $row = $em->getNewEntity('PrimaNota');
        $row->set([
            'entryType' => 'Income',
            'internalClassification' => 'Donation',
            'donationPaymentProvider' => 'SatispayDirect',
            'donationPaymentReference' => 'ORD-' . $marker,
            'amountGross' => 25.0,
            'amountGrossCurrency' => 'EUR',
            'transactionDate' => date('Y-m-d'),
        ]);
        $em->saveEntity($row);

        $description = (string) $row->get('description');
        $this->assertStringContainsString('Donazione SatispayDirect ordine ORD-' . $marker, $description);
    }

    public function testProtectDonationPaymentProviderBlocksManualStripeCreate(): void
    {
        $em = $this->getEntityManager();

        $this->assertBadRequest(function () use ($em): void {
            $row = $em->getNewEntity('PrimaNota');
            $row->set([
                'description' => 'PHPUnit block manual Stripe',
                'entryType' => 'Income',
                'internalClassification' => 'Donation',
                'donationPaymentProvider' => 'Stripe',
                'donationPaymentReference' => 'PHPUNIT-BLOCK-' . $this->uniqueMarker(),
                'amountGross' => 10.0,
                'amountGrossCurrency' => 'EUR',
                'transactionDate' => date('Y-m-d'),
            ]);
            $em->saveEntity($row);
        }, 'Stripe platform can only be set', 'può essere impostata solo', 'stripeManualCreateBlocked');
    }

    public function testProtectDonationPaymentProviderBlocksPlatformChange(): void
    {
        $em = $this->getEntityManager();

        $row = $this->newPrimaNotaManual();
        $em->saveEntity($row);

        $row = $em->getEntityById('PrimaNota', $row->getId());

        $this->assertBadRequest(function () use ($em, $row): void {
            $row->set('donationPaymentProvider', 'Cash');
            $em->saveEntity($row);
        }, 'cannot be changed', 'non può essere modificata', 'platformImmutable');
    }

    public function testProtectStripeSourcedFieldsBlocksInteractiveMoneyEdit(): void
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $stripe = $em->getNewEntity('PrimaNota');
        $stripe->set([
            'description' => 'PHPUnit Stripe lock',
            'entryType' => 'Income',
            'internalClassification' => 'Donation',
            'donationPaymentProvider' => 'Stripe',
            'donationPaymentReference' => 'PHPUNIT-STRIPE-' . $marker,
            'amountGross' => 100.0,
            'amountGrossCurrency' => 'EUR',
            'commissionPercent' => 2.9,
            'commissionAmount' => 2.9,
            'commissionAmountCurrency' => 'EUR',
            'amount' => 97.1,
            'amountCurrency' => 'EUR',
            'subjectName' => 'Donor From Stripe',
            'transactionDate' => date('Y-m-d'),
            'stripeChargeId' => 'ch_phpunit_lock',
        ]);
        $em->saveEntity($stripe, [SaveOption::SKIP_ALL => true]);
        $stripeId = $stripe->getId();
        $this->assertNotEmpty($stripeId);

        $this->assertBadRequest(function () use ($em, $stripeId): void {
            $row = $em->getEntityById('PrimaNota', $stripeId);
            $row->set('commissionAmount', 9.0);
            $row->set('commissionAmountCurrency', 'EUR');
            $em->saveEntity($row);
        }, 'Stripe', 'stripeSourcedReadOnly');
    }

    public function testProtectStripeSourcedFieldsBlocksInteractiveSubjectEdit(): void
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $stripe = $em->getNewEntity('PrimaNota');
        $stripe->set([
            'description' => 'PHPUnit Stripe subject lock',
            'entryType' => 'Income',
            'internalClassification' => 'Donation',
            'donationPaymentProvider' => 'Stripe',
            'donationPaymentReference' => 'PHPUNIT-SUBJ-' . $marker,
            'amountGross' => 50.0,
            'amountGrossCurrency' => 'EUR',
            'commissionAmount' => 0,
            'commissionAmountCurrency' => 'EUR',
            'amount' => 50.0,
            'amountCurrency' => 'EUR',
            'subjectName' => 'Original Donor',
            'transactionDate' => date('Y-m-d'),
            'stripeChargeId' => 'ch_phpunit_subj',
        ]);
        $em->saveEntity($stripe, [SaveOption::SKIP_ALL => true]);
        $stripeId = $stripe->getId();

        $this->assertBadRequest(function () use ($em, $stripeId): void {
            $row = $em->getEntityById('PrimaNota', $stripeId);
            $row->set('subjectName', 'Hacker Rename');
            $em->saveEntity($row);
        }, 'Stripe', 'stripeSourcedReadOnly');
    }

    public function testProtectStripeSourcedFieldsAllowsOperationalFieldEdit(): void
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $stripe = $em->getNewEntity('PrimaNota');
        $stripe->set([
            'description' => 'PHPUnit Stripe model D',
            'entryType' => 'Income',
            'internalClassification' => 'Donation',
            'donationPaymentProvider' => 'Stripe',
            'donationPaymentReference' => 'PHPUNIT-MODELD-' . $marker,
            'amountGross' => 30.0,
            'amountGrossCurrency' => 'EUR',
            'commissionAmount' => 0,
            'commissionAmountCurrency' => 'EUR',
            'amount' => 30.0,
            'amountCurrency' => 'EUR',
            'transactionDate' => date('Y-m-d'),
            'stripeChargeId' => 'ch_phpunit_modeld',
        ]);
        $em->saveEntity($stripe, [SaveOption::SKIP_ALL => true]);

        $row = $em->getEntityById('PrimaNota', $stripe->getId());
        $row->set('modelDClassification', 'C');
        $em->saveEntity($row);

        $saved = $em->getEntityById('PrimaNota', $stripe->getId());
        $this->assertSame('C', $saved->get('modelDClassification'));
    }

    public function testIncompleteStripeIngestAllowsOneTimeBackfill(): void
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $row = $em->getNewEntity('PrimaNota');
        $row->set([
            'description' => 'PHPUnit Stripe incomplete',
            'entryType' => 'Income',
            'internalClassification' => 'Donation',
            'donationPaymentProvider' => 'Stripe',
            'donationPaymentReference' => 'PHPUNIT-INCOMPLETE-' . $marker,
            'amountGross' => 5.0,
            'amountGrossCurrency' => 'EUR',
            'commissionAmount' => 0,
            'commissionAmountCurrency' => 'EUR',
            'commissionPercent' => 0,
            'amount' => 5.0,
            'amountCurrency' => 'EUR',
            'transactionDate' => date('Y-m-d'),
        ]);
        $em->saveEntity($row, [SaveOption::SKIP_ALL => true]);

        $row = $em->getEntityById('PrimaNota', $row->getId());
        $row->set([
            'commissionAmount' => 0.33,
            'commissionAmountCurrency' => 'EUR',
            'commissionPercent' => 6.6,
            'amount' => 4.67,
            'amountCurrency' => 'EUR',
            'stripeChargeId' => 'ch_phpunit_backfill',
            'stripeBalanceTransactionId' => 'txn_phpunit_backfill',
        ]);
        $em->saveEntity($row);

        $saved = $em->getEntityById('PrimaNota', $row->getId());
        $this->assertEqualsWithDelta(0.33, (float) $saved->get('commissionAmount'), 0.001);
        $this->assertSame('ch_phpunit_backfill', $saved->get('stripeChargeId'));

        $this->assertBadRequest(function () use ($em, $saved): void {
            $entity = $em->getEntityById('PrimaNota', $saved->getId());
            $entity->set('commissionAmount', 1.0);
            $em->saveEntity($entity);
        }, 'Stripe', 'stripeSourcedReadOnly');
    }
}
