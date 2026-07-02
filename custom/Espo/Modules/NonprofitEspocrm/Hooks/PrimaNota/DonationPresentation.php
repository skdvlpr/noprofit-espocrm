<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Pre-fills description for online donations so API create passes validation
 * before the PrimaNota formula derives name/description from donation fields.
 */
class DonationPresentation implements BeforeSave
{
    public static int $order = 1;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $description = trim((string) ($entity->get('description') ?? ''));
        $reference = trim((string) ($entity->get('donationPaymentReference') ?? ''));

        if ($description === '' && $reference === '') {
            throw new BadRequest('Description is required.');
        }

        if ($entity->get('internalClassification') !== 'Donation') {
            return;
        }

        if ($reference === '') {
            return;
        }

        if ($description !== '') {
            return;
        }

        $provider = trim((string) ($entity->get('donationPaymentProvider') ?? ''));
        if ($provider === '') {
            $provider = 'Stripe';
        }

        $description = 'Donazione '.$provider.' ordine '.$reference;

        $comment = trim((string) ($entity->get('donationComment') ?? ''));
        if ($comment !== '') {
            $description .= "\nComment: ".$comment;
        }

        $entity->set('description', $description);
    }
}
