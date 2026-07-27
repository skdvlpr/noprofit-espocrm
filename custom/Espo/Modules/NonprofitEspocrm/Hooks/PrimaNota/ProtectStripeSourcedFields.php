<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * When donationPaymentProvider is Stripe, every Stripe-sourced attribute is locked
 * after create. Operational fields remain editable: assignedUser, teams,
 * modelDClassification, paymentStatus. Ingest create still works (isNew).
 *
 * Incomplete ingest window: if stripeChargeId is still empty, allow settlement /
 * enrichment backfill only for API or system actors (website webhook / CLI smoke).
 * Interactive users stay fully locked — the incomplete race must not reopen the ledger.
 *
 * Gap fill: even after charge id is set, allow writing a non-money Stripe-sourced
 * attribute only when the fetched (DB) value is empty and the incoming value is
 * non-empty (covers email/enrichment gaps without reopening money fields).
 *
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

    /**
     * Never gap-fill money / classification ledger columns (comment promised this).
     *
     * @var list<string>
     */
    private const NO_GAP_FILL_ATTRIBUTES = [
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
        'donationPaymentProvider',
        'donationPaymentReference',
        'financingId',
        'subjectPartyId',
        'subjectPartyType',
        'beneficiaryPartyId',
        'beneficiaryPartyType',
        'createSubjectAccount',
        'createSubjectContact',
        'createBeneficiaryAccount',
        'createBeneficiaryContact',
    ];

    public function __construct(
        private User $user,
        Language $language,
    ) {
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

        // Incomplete Stripe ingest (no charge id yet) — webhook/system settlement only.
        if ($this->isIncompleteStripeIngest($entity) && $this->actorMaySettleIncompleteIngest()) {
            return;
        }

        foreach (self::STRIPE_SOURCED_ATTRIBUTES as $attribute) {
            if (!$entity->isAttributeChanged($attribute)) {
                continue;
            }

            if ($this->isEmptyToValueFill($entity, $attribute)) {
                continue;
            }

            throw new BadRequest($this->msg('stripeSourcedReadOnly'));
        }
    }

    private function actorMaySettleIncompleteIngest(): bool
    {
        // Website API user (type=api) or CLI/system jobs. Interactive staff stay locked.
        return $this->user->isApi() || $this->user->isSystem();
    }

    private function isIncompleteStripeIngest(Entity $entity): bool
    {
        // Fetched (DB) value only — do not use get(), or a backfill that sets
        // stripeChargeId in the same save would look "complete" and lock itself out.
        $chargeId = trim((string) ($entity->getFetched('stripeChargeId') ?? ''));

        return $chargeId === '';
    }

    /**
     * Allow one-way fill of empty Stripe-sourced attrs (e.g. emails dropped by
     * field-type quirks on an earlier partial backfill). Never overwrite a set
     * value and never gap-fill money / identity lock columns.
     */
    private function isEmptyToValueFill(Entity $entity, string $attribute): bool
    {
        if (in_array($attribute, self::NO_GAP_FILL_ATTRIBUTES, true)) {
            return false;
        }

        $fetched = $entity->getFetched($attribute);
        if (!$this->isEmptyAttributeValue($fetched)) {
            return false;
        }

        return !$this->isEmptyAttributeValue($entity->get($attribute));
    }

    private function isEmptyAttributeValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_bool($value)) {
            return false;
        }

        if (is_int($value) || is_float($value)) {
            // Money/bool-like numerics that are already stored (incl. 0) are "set".
            return false;
        }

        return false;
    }

    private function isStripeProvider(Entity $entity): bool
    {
        $provider = strtolower(trim((string) ($entity->getFetched('donationPaymentProvider')
            ?? $entity->get('donationPaymentProvider')
            ?? '')));

        return $provider === 'stripe';
    }
}
