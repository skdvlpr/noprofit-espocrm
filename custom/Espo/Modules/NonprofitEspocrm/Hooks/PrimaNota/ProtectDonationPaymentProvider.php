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
 * - Platform cannot change after create (UI + API).
 * - Manual UI cannot set Stripe on create (website ingest uses type=api).
 */
class ProtectDonationPaymentProvider implements BeforeSave
{
    use TranslatesPrimaNotaMessages;

    public static int $order = 3;

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

        $provider = trim((string) ($entity->get('donationPaymentProvider') ?? ''));

        if ($entity->isNew()) {
            if ($this->isStripe($provider) && !$this->user->isApi()) {
                throw new BadRequest($this->msg('stripeManualCreateBlocked'));
            }

            return;
        }

        if ($entity->isAttributeChanged('donationPaymentProvider')) {
            throw new BadRequest($this->msg('platformImmutable'));
        }
    }

    private function isStripe(string $provider): bool
    {
        return strtolower($provider) === 'stripe';
    }
}
