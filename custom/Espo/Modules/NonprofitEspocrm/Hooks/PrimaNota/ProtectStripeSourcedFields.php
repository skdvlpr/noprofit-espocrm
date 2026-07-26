<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Language;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * When donationPaymentProvider is Stripe, every Stripe-sourced attribute is locked
 * after create. Operational fields remain editable: assignedUser, teams,
 * modelDClassification, paymentStatus. Ingest create still works (isNew).
 *
 * Incomplete ingest window: if stripeChargeId is still empty, allow one-time
 * backfill of Stripe-sourced attributes (thank-you raced ahead of BalanceTransaction).
 * System retries: SaveOption::SKIP_ALL.
 */
class ProtectStripeSourcedFields implements BeforeSave
{
    use TranslatesPrimaNotaMessages;

    public static int $order = 5;

    /**
     * @var list<string>
     */
    private const STRIPE_SOURCED_ATTRIBUTES = [
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
        'entryType',
        'transactionDate',
        'internalClassification',
        'description',
        'name',
        'donationPaymentProvider',
        'donationPaymentReference',
        'donationDonorCategory',
        'donationFrequency',
        'donationComment',
        'financingId',
        'subjectName',
        'subjectPartyId',
        'subjectPartyType',
        'subjectEmailAddress',
        'subjectPhoneNumber',
        'createSubjectAccount',
        'createSubjectContact',
        'beneficiaryName',
        'beneficiaryPartyId',
        'beneficiaryPartyType',
        'beneficiaryEmailAddress',
        'beneficiaryPhoneNumber',
        'createBeneficiaryAccount',
        'createBeneficiaryContact',
        'stripePaymentCreatedAt',
        'stripeChargeId',
        'stripeBalanceTransactionId',
        'stripePaymentMethodType',
        'stripeCardBrand',
        'stripeCardLast4',
        'stripeReceiptUrl',
        'stripeReceiptEmail',
        'stripeBillingEmail',
        'stripeBillingPhone',
        'stripeFeeDetailsJson',
        'stripeLivemode',
        'stripeRadarRiskLevel',
        'stripeStatementDescriptor',
        'stripeCustomerId',
        'stripeSubscriptionId',
    ];

    public function __construct(Language $language)
    {
        $this->language = $language;
    }

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

        // Incomplete Stripe ingest (no charge id yet) — allow settlement/enrichment backfill.
        if ($this->isIncompleteStripeIngest($entity)) {
            return;
        }

        foreach (self::STRIPE_SOURCED_ATTRIBUTES as $attribute) {
            if ($entity->isAttributeChanged($attribute)) {
                throw new BadRequest($this->msg('stripeSourcedReadOnly'));
            }
        }
    }

    private function isIncompleteStripeIngest(Entity $entity): bool
    {
        // Fetched (DB) value only — do not use get(), or a backfill that sets
        // stripeChargeId in the same save would look "complete" and lock itself out.
        $chargeId = trim((string) ($entity->getFetched('stripeChargeId') ?? ''));

        return $chargeId === '';
    }

    private function isStripeProvider(Entity $entity): bool
    {
        $provider = strtolower(trim((string) ($entity->getFetched('donationPaymentProvider')
            ?? $entity->get('donationPaymentProvider')
            ?? '')));

        return $provider === 'stripe';
    }
}
