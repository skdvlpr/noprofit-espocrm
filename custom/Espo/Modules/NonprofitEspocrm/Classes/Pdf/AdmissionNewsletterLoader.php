<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\Pdf;

use Espo\Modules\NonprofitEspocrm\Tools\Admission\NewsletterConsent;
use Espo\ORM\Entity;
use Espo\Tools\Pdf\Data\DataLoader;
use Espo\Tools\Pdf\Params;
use stdClass;

/**
 * Newsletter tick for the admission PDF. Not a stored Lead field.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/pdf-defs.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 *
 * @implements DataLoader<Entity>
 */
class AdmissionNewsletterLoader implements DataLoader
{
    public function load(Entity $entity, Params $params): stdClass
    {
        $description = $entity->get('description');
        $fromText = NewsletterConsent::fromDescription(is_string($description) ? $description : null);
        $field = $entity->get('newsletterConsent');
        $yes = $field === 'Yes' || $fromText === NewsletterConsent::YES;
        $no = $field === 'No' || $fromText === NewsletterConsent::NO;

        return (object) [
            'newsletterYesMark' => NewsletterConsent::box($yes && !$no),
            'newsletterNoMark' => NewsletterConsent::box($no && !$yes),
            'newsletterLine' => $yes && !$no
                ? 'Newsletter: acconsente'
                : ($no && !$yes ? 'Newsletter: non acconsente' : ''),
            'outcomeApprovedMark' => NewsletterConsent::box($entity->get('admissionOutcome') === 'Approved'),
            'outcomeRejectedMark' => NewsletterConsent::box($entity->get('admissionOutcome') === 'Rejected'),
            'feeYesMark' => NewsletterConsent::box($entity->get('admissionFeePaid') === 'Yes'),
            'feeNoMark' => NewsletterConsent::box($entity->get('admissionFeePaid') === 'No'),
            'applicantName' => trim(
                (string) $entity->get('firstName') . ' ' . (string) $entity->get('lastName')
            ),
            'birthDateIt' => self::italianDate($entity->get('birthDate')),
            'boardDateIt' => self::italianDate($entity->get('admissionBoardDate'))
                ?: '____ / ____ / ________',
        ];
    }

    private static function italianDate(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $match)) {
            return '';
        }

        return $match[3] . '/' . $match[2] . '/' . $match[1];
    }
}
