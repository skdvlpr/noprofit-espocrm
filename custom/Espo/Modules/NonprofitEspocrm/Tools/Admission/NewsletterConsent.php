<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Admission;

/**
 * Newsletter line the website already writes into Lead description.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 */
class NewsletterConsent
{
    public const YES = 'yes';

    public const NO = 'no';

    public static function fromDescription(?string $description): string
    {
        if ($description === null || $description === '') {
            return '';
        }

        if (preg_match('/^Newsletter:\s*acconsente\s*$/mi', $description) === 1) {
            return self::YES;
        }

        if (preg_match('/^Newsletter:\s*non acconsente\s*$/mi', $description) === 1) {
            return self::NO;
        }

        return '';
    }

    /**
     * The dropdown is the value staff edit. A site description line only fills
     * that dropdown, then the line is removed from the description.
     */
    public static function sync(\Espo\ORM\Entity $entity): void
    {
        $description = is_string($entity->get('description')) ? $entity->get('description') : '';
        $fromText = self::fromDescription($description);
        $field = $entity->get('newsletterConsent');

        if (($field === null || $field === '') && $fromText !== '') {
            $entity->set('newsletterConsent', $fromText === self::YES ? 'Yes' : 'No');
        }

        $stripped = preg_replace('/^Newsletter:\s*(?:non )?acconsente\s*$/mi', '', $description) ?? $description;
        $stripped = trim($stripped);

        if ($stripped !== $description) {
            $entity->set('description', $stripped === '' ? null : $stripped);
        }
    }

    public static function box(bool $marked): string
    {
        return $marked ? 'X' : ' ';
    }
}
