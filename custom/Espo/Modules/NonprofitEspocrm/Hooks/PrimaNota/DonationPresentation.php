<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Language;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Pre-fills description for online donations so API create passes validation
 * before the PrimaNota formula derives name/description from donation fields.
 */
class DonationPresentation implements BeforeSave
{
    use TranslatesPrimaNotaMessages;

    public static int $order = 1;

    public function __construct(Language $language)
    {
        $this->language = $language;
    }

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $description = trim((string) ($entity->get('description') ?? ''));
        $reference = trim((string) ($entity->get('donationPaymentReference') ?? ''));

        if ($description === '' && $reference === '') {
            throw new BadRequest($this->msg('descriptionRequired'));
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
            $provider = 'Other';
        }

        $description = 'Donazione '.$provider.' ordine '.$reference;

        $comment = trim((string) ($entity->get('donationComment') ?? ''));
        if ($comment !== '') {
            $description .= "\nComment: ".$comment;
        }

        $entity->set('description', $description);
    }
}
