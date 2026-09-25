<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Admission;

use Espo\ORM\Entity;

/**
 * Copy empty Contact board fields from a converted Lead. Never copy
 * the admission file (PDF is built on demand).
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 */
class ContactAdmissionCopy
{
    /** @var list<string> */
    public const BOARD_FIELDS = [
        'admissionBoardDate',
        'admissionOutcome',
        'memberBookNumber',
        'admissionFeePaid',
        'admissionReceiptNumber',
        'newsletterConsent',
    ];

    public static function shouldCopy(Entity $lead, ?Entity $contact): bool
    {
        if ($contact === null) {
            return false;
        }

        if ($lead->get('status') !== 'Converted') {
            return false;
        }

        $contactId = $lead->get('createdContactId');

        if (!is_string($contactId) || $contactId === '' || $contactId !== $contact->getId()) {
            return false;
        }

        $leadAssociato = AdmissionPdf::isAssociato($lead->get('contactType'));
        $contactAssociato = AdmissionPdf::isAssociato($contact->get('contactType'));

        return $leadAssociato && $contactAssociato;
    }

    public static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * @return list<string>
     */
    public static function fieldsToCopy(Entity $lead, Entity $contact): array
    {
        $out = [];

        foreach (self::BOARD_FIELDS as $field) {
            if (self::isEmpty($contact->get($field)) && !self::isEmpty($lead->get($field))) {
                $out[] = $field;
            }
        }

        return $out;
    }

    public static function apply(Entity $lead, Entity $contact): bool
    {
        if (!self::shouldCopy($lead, $contact)) {
            return false;
        }

        $fields = self::fieldsToCopy($lead, $contact);

        if ($fields === []) {
            return false;
        }

        foreach ($fields as $field) {
            $contact->set($field, $lead->get($field));
        }

        return true;
    }
}
