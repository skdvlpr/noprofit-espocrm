<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Stripe cash model: charge success is Planned until bank payout (Inviato).
 *
 * entityDefs default paymentStatus is Inviato (correct for manual entries).
 * Website ingest often omits paymentStatus, so Espo would apply Inviato and
 * inflate Saldo di cassa / income totals before money hits the bank.
 *
 * Runs on create even with SKIP_ALL — ingest uses that path.
 */
class NormalizeStripePaymentStatusOnCreate implements BeforeSave
{
    public static int $order = 4;

    /** @var list<string> */
    private const TERMINAL_OR_EXPLICIT = [
        'Planned',
        'Cancelled',
        'Refunded',
        'Disputed',
        'Problematic',
    ];

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isNew()) {
            return;
        }

        if (!$this->isStripeProvider($entity)) {
            return;
        }

        $status = trim((string) ($entity->get('paymentStatus') ?? ''));
        $payoutId = trim((string) ($entity->get('stripePayoutId') ?? ''));

        if ($payoutId !== '') {
            if ($status === '' || $this->isCountedLegacyOrDefault($status)) {
                $entity->set('paymentStatus', 'Inviato');
            }

            return;
        }

        if (in_array($status, self::TERMINAL_OR_EXPLICIT, true)) {
            return;
        }

        // Default Inviato / legacy Paid / empty → Planned until payout lands.
        $entity->set('paymentStatus', 'Planned');
    }

    private function isCountedLegacyOrDefault(string $status): bool
    {
        return in_array($status, ['Inviato', 'Paid', 'PaidOut'], true);
    }

    private function isStripeProvider(Entity $entity): bool
    {
        $provider = strtolower(trim((string) ($entity->get('donationPaymentProvider') ?? '')));

        return $provider === 'stripe';
    }
}
