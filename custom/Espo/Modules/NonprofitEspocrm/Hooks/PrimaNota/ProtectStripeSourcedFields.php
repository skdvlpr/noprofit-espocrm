<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * When donationPaymentProvider is Stripe, every Stripe-sourced attribute is locked
 * after create. Only Espo operational fields (assignedUser, teams, modelDClassification)
 * remain editable. Ingest create still works (isNew). System retries: SaveOption::SKIP_ALL.
 */
class ProtectStripeSourcedFields implements BeforeSave
{
    public static int $order = 5;

    /**
     * Stripe ingest / PaymentIntent-sourced attributes (money + donor + donation meta).
     *
     * @var list<string>
     */
    private const STRIPE_SOURCED_ATTRIBUTES = [
        // Money triangle + derived
        'amount',
        'amountCurrency',
        'amountGross',
        'amountGrossCurrency',
        'commissionAmount',
        'commissionAmountCurrency',
        'commissionPercent',
        'amountIn',
        'amountInCurrency',
        'amountOut',
        'amountOutCurrency',
        // Movement / donation payload
        'entryType',
        'transactionDate',
        'internalClassification',
        'description',
        'name',
        'donationPaymentProvider',
        'donationPaymentReference',
        'donationDonorCategory',
        'donationComment',
        'financingId',
        // Subject (donor) from Stripe / site form
        'subjectName',
        'subjectPartyId',
        'subjectPartyType',
        'subjectEmailAddress',
        'subjectPhoneNumber',
        'createSubjectAccount',
        'createSubjectContact',
        // Beneficiary defaults from ingest
        'beneficiaryName',
        'beneficiaryPartyId',
        'beneficiaryPartyType',
        'beneficiaryEmailAddress',
        'beneficiaryPhoneNumber',
        'createBeneficiaryAccount',
        'createBeneficiaryContact',
    ];

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if ($entity->isNew()) {
            return;
        }

        if (!$this->isStripeProvider($entity)) {
            return;
        }

        foreach (self::STRIPE_SOURCED_ATTRIBUTES as $attribute) {
            if ($entity->isAttributeChanged($attribute)) {
                throw new BadRequest(
                    'This ledger entry comes from Stripe. Stripe-sourced fields are read-only; '
                    .'you can only change Espo fields (assigned user, teams, Model D classification).'
                );
            }
        }
    }

    private function isStripeProvider(Entity $entity): bool
    {
        $provider = strtolower(trim((string) ($entity->getFetched('donationPaymentProvider')
            ?? $entity->get('donationPaymentProvider')
            ?? '')));

        return $provider === 'stripe';
    }
}
