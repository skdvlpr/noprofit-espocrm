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
 * Stripe paymentStatus is UI-read-only and drives Paid-only dashlets / fundraising.
 * Keep updates available for website ingest (type=api) and SKIP_ALL retries only.
 * Interactive staff must not flip Refunded/Disputed/Paid via API or mass update.
 */
class ProtectStripePaymentStatus implements BeforeSave
{
    use TranslatesPrimaNotaMessages;

    public static int $order = 6;

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

        if (!$entity->isAttributeChanged('paymentStatus')) {
            return;
        }

        if (!$this->isStripeProvider($entity)) {
            return;
        }

        if ($this->user->isApi()) {
            return;
        }

        throw new BadRequest($this->msg('stripePaymentStatusReadOnly'));
    }

    private function isStripeProvider(Entity $entity): bool
    {
        $provider = strtolower(trim((string) ($entity->getFetched('donationPaymentProvider')
            ?? $entity->get('donationPaymentProvider')
            ?? '')));

        return $provider === 'stripe';
    }
}
