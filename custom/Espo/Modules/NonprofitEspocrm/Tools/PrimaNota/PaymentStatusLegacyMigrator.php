<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\PrimaNota;

use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\ORM\EntityManager;

/**
 * Rename legacy PrimaNota.paymentStatus Paid/PaidOut → Inviato.
 *
 * Idempotent. Does NOT demote Stripe rows to Planned based on empty
 * stripePayoutId — that field was added later and is empty on historical
 * banked donations; demoting would drop real cash from totals.
 *
 * New Stripe creates are normalized to Planned by
 * {@see \Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota\NormalizeStripePaymentStatusOnCreate}.
 */
class PaymentStatusLegacyMigrator
{
    /**
     * @return array{paidOutToInviato: int, paidToInviato: int}
     */
    public function migrate(EntityManager $entityManager, bool $dryRun = false): array
    {
        $counts = [
            'paidOutToInviato' => 0,
            'paidToInviato' => 0,
        ];

        $legacy = $entityManager->getRDBRepository('PrimaNota')
            ->where([
                'paymentStatus' => ['Paid', 'PaidOut'],
            ])
            ->order('createdAt', 'ASC')
            ->find();

        foreach ($legacy as $entity) {
            $from = (string) ($entity->get('paymentStatus') ?? '');
            $bucket = $from === 'PaidOut' ? 'paidOutToInviato' : 'paidToInviato';

            if (!$dryRun) {
                $entity->set('paymentStatus', 'Inviato');
                $entityManager->saveEntity($entity, [
                    SaveOption::SKIP_ALL => true,
                    SaveOption::SILENT => true,
                ]);
            }

            $counts[$bucket]++;
        }

        return $counts;
    }
}
