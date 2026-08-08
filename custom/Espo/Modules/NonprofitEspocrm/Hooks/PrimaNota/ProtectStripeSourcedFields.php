<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * When donationPaymentProvider is Stripe, every Stripe-sourced attribute is locked
 * after create for normal UI users — including paymentStatus (Stripe / refresh only).
 * Operational fields remain editable: assignedUser, teams, modelDClassification.
 * Ingest create still works (isNew).
 *
 * Trusted site/API sync users (config safehouseStripeSyncUserNames / Ids) may
 * overwrite Stripe-sourced attrs so webhook + «Aggiorna da Stripe» keep CRM in sync.
 *
 * Incomplete ingest window: if stripeChargeId is still empty, allow one-time
 * backfill of Stripe-sourced attributes (thank-you raced ahead of BalanceTransaction).
 *
 * Gap fill: even after charge id is set, allow writing a Stripe-sourced attribute
 * only when the fetched (DB) value is empty and the incoming value is non-empty
 * (covers email-type / partial backfill gaps without reopening money fields).
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
        'paymentStatus',
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
        'stripeInvoiceId',
        'stripeInvoiceNumber',
        'stripePayoutId',
        'stripePayoutPaidAt',
    ];

    public function __construct(
        Language $language,
        private User $user,
        private Config $config,
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

        if ($this->isTrustedStripeSyncUser()) {
            return;
        }

        // Incomplete Stripe ingest (no charge id yet) — allow settlement/enrichment backfill.
        if ($this->isIncompleteStripeIngest($entity)) {
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

    private function isTrustedStripeSyncUser(): bool
    {
        $userName = trim((string) $this->user->get('userName'));
        $userId = trim((string) $this->user->getId());

        /** @var mixed $names */
        $names = $this->config->get('safehouseStripeSyncUserNames');
        if (!is_array($names) || $names === []) {
            $names = ['website', 'site_safehouse.community'];
        }

        foreach ($names as $name) {
            if (is_string($name) && trim($name) !== '' && $userName === trim($name)) {
                return true;
            }
        }

        /** @var mixed $ids */
        $ids = $this->config->get('safehouseStripeSyncUserIds');
        if (is_array($ids)) {
            foreach ($ids as $id) {
                if (is_string($id) && trim($id) !== '' && $userId === trim($id)) {
                    return true;
                }
            }
        }

        return false;
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
     * field-type quirks on an earlier partial backfill). Never overwrite a set value.
     */
    private function isEmptyToValueFill(Entity $entity, string $attribute): bool
    {
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
