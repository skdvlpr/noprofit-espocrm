<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\FoodParcel;

use Espo\Entities\PhoneNumber as PhoneNumberEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Repositories\PhoneNumber as PhoneNumberRepository;
use stdClass;

/**
 * Copies identity fields from Contact onto FoodParcelRegistration snapshots
 * used in list views and PDF export.
 */
class FoodParcelContactSync
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function applyContact(Entity $registration, Entity $contact): void
    {
        $registration->set([
            'taxCode' => $contact->get('taxCode'),
            'phone' => $this->formatContactPhones($contact),
            'phonePdf' => $this->formatContactPhonesPdf($contact),
            'addressLine' => $this->formatContactAddressLine($contact),
            'addressStreet' => $contact->get('addressStreet'),
            'addressCity' => $contact->get('addressCity'),
            'addressState' => $contact->get('addressState'),
            'addressCountry' => $contact->get('addressCountry'),
            'addressPostalCode' => $contact->get('addressPostalCode'),
        ]);
    }

    public function syncFromContactId(Entity $registration): void
    {
        $contactId = $registration->get('contactId');

        if ($contactId === null || $contactId === '') {
            return;
        }

        $contact = $this->entityManager->getEntityById('Contact', $contactId);

        if ($contact === null) {
            return;
        }

        $this->applyContact($registration, $contact);
    }

    public function syncRegistrationsForContactId(string $contactId): void
    {
        $contact = $this->entityManager->getEntityById('Contact', $contactId);

        if ($contact === null) {
            return;
        }

        $collection = $this->entityManager
            ->getRDBRepository('FoodParcelRegistration')
            ->where(['contactId' => $contactId])
            ->find();

        foreach ($collection as $registration) {
            $this->applyContact($registration, $contact);
            $this->entityManager->saveEntity($registration);
        }
    }

    public function formatContactPhones(Entity $contact): string
    {
        $lines = $this->buildPhoneLines($contact);

        return implode("\n", $lines);
    }

    public function formatContactPhonesPdf(Entity $contact): string
    {
        $lines = $this->buildPhoneLines($contact);

        if ($lines === []) {
            return '';
        }

        $items = array_map(
            fn (string $line): string => '<div style="margin:0;padding:0;line-height:1.35;">'
                . htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                . '</div>',
            $lines
        );

        return implode('', $items);
    }

    public function formatContactAddressLine(Entity $contact): string
    {
        $street = trim((string) $contact->get('addressStreet'));
        $city = trim((string) $contact->get('addressCity'));
        $state = trim((string) $contact->get('addressState'));
        $postalCode = trim((string) $contact->get('addressPostalCode'));
        $country = trim((string) $contact->get('addressCountry'));

        $cityPart = $city;

        if ($state !== '' || $postalCode !== '') {
            $regionPostal = trim($state . ' ' . $postalCode);
            $cityPart = $cityPart !== ''
                ? $cityPart . ', ' . $regionPostal
                : $regionPostal;
        }

        $parts = array_values(array_filter([$street, $cityPart, $country], static fn (string $part): bool => $part !== ''));

        return implode(', ', $parts);
    }

    /**
     * @return string[]
     */
    private function buildPhoneLines(Entity $contact): array
    {
        $rows = $this->collectPhoneRows($contact);

        if ($rows === []) {
            $fallback = $contact->get('phoneNumber');

            if (is_string($fallback) && trim($fallback) !== '') {
                return [trim($fallback)];
            }

            return [];
        }

        $lines = [];

        foreach ($rows as $row) {
            $number = trim((string) ($row->phoneNumber ?? ''));

            if ($number === '') {
                continue;
            }

            $type = trim((string) ($row->type ?? ''));

            $lines[] = $type !== '' ? $number . ' (' . $type . ')' : $number;
        }

        return $lines;
    }

    /**
     * @return stdClass[]
     */
    private function collectPhoneRows(Entity $contact): array
    {
        $raw = $contact->get('phoneNumberData');

        if (is_array($raw) && $raw !== []) {
            return array_map(fn (mixed $row): stdClass => $this->normalizePhoneRow($row), $raw);
        }

        if (!$contact->hasId()) {
            return [];
        }

        return $this->phoneRepository()->getPhoneNumberData($contact);
    }

    private function normalizePhoneRow(mixed $row): stdClass
    {
        if ($row instanceof stdClass) {
            return $row;
        }

        if (is_array($row)) {
            return (object) $row;
        }

        return (object) [];
    }

    private function phoneRepository(): PhoneNumberRepository
    {
        /** @var PhoneNumberRepository */
        return $this->entityManager->getRepository(PhoneNumberEntity::ENTITY_TYPE);
    }
}
