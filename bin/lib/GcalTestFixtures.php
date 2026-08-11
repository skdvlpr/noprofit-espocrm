<?php

declare(strict_types=1);


require __DIR__ . '/refuse-production.php';


use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Shared Google Calendar E2E fixtures: realistic CRM data + T- prefix.
 *
 * Production entities (Meeting, Call, Contact, …) — default test scope.
 * GCalSmoke* — custom-entity extension smoke only (see test-google-calendar-smoke-entities.php).
 */
final class GcalTestFixtures
{
    public const TEST_PREFIX = 'T-';

    public const DEFAULT_APP_TIMEZONE = 'Europe/Rome';

    /** @var list<string> */
    public const LEGACY_PREFIXES = [
        'E2E_',
        'SmokeGCal',
        'BLOCK3_',
        'BLOCK4_',
        'BLOCK58_',
    ];

    /** @var list<string> */
    private const PERSON_ENTITIES = ['Contact'];

    /** @var array<string, list<string>> */
    private const REALISTIC_TITLES = [
        'Meeting' => [
            'Riunione team cucina',
            'Briefing volontari settimanale',
            'Incontro coordinamento pasti',
        ],
        'Call' => [
            'Chiamata fornitore pasti',
            'Follow-up donatore locale',
            'Coordinamento turno serale',
        ],
        'Task' => [
            'Aggiornamento report mensile',
            'Verifica inventario dispensa',
            'Preparazione documenti bando',
        ],
        'Opportunity' => [
            'Bando fondazione locale',
            'Finanziamento attrezzature cucina',
            'Contributo progetto pasti',
        ],
        'Account' => [
            'Cooperativa Solidarietà',
            'Fornitore BioVerde',
            'Partner Comune di Milano',
        ],
        'Campaign' => [
            'Raccolta fondi estate',
            'Campagna volontari autunno',
            'Evento benefico annuale',
        ],
        'Contact' => ['Rossi', 'Bianchi', 'Verdi', 'Ferrari', 'Conti', 'Romano'],
        'GCalSmokeAllDay' => [
            'Verifica calendario giornata intera',
        ],
        'GCalSmokeDateTime' => [
            'Verifica intervallo orario',
        ],
        'GCalSmokeTwinDate' => [
            'Verifica doppia data custom',
        ],
    ];

    /** @var array<string, list<string>> */
    private const FUNNY_TITLES = [
        'Meeting' => [
            'Chi ha mangiato l\'ultimo cannolo?',
            'Consiglio di guerra contro il forno rotto',
            'Summit internazionale del ragù',
        ],
        'Call' => [
            'Il gatto ha prenotato la meeting room',
            'Fornitore di pane magicamente levitante',
            'Donatore che paga in abbracci e biscotti',
        ],
        'Task' => [
            'Contare i chicchi di riso fino a mezzanotte',
            'Spiegare al bando perché meritiamo i soldi (con meme)',
            'Inventario: trovare chi ha nascosto le posate',
        ],
        'Opportunity' => [
            'Bando «Cucina del futuro» (serve viaggio nel tempo)',
            'Fondo per fondi — meta-finanziamiento',
            'Grant UFO: pasti per alieni vegetariani',
        ],
        'Account' => [
            'Pasta Quantica S.r.l.',
            'Cooperativa Ciambella Senza Buco',
            'Orecchiette Elon S.p.A.',
        ],
        'Campaign' => [
            'Salva la parmigiana 2026',
            'Eroi con grembiule — reclutamento',
            'Cena dei bro che brodano',
        ],
        'Contact' => ['Panettone', 'Tramezzino', 'Lasagnone', 'Polpetta', 'Carbonara', 'Tiramisu'],
        'GCalSmokeAllDay' => [
            'Giornata intera nel frigo (smoke test)',
        ],
        'GCalSmokeDateTime' => [
            'Macchina del tempo 14:00–15:00',
        ],
        'GCalSmokeTwinDate' => [
            'Prima data: latte — seconda data: caffè',
        ],
    ];

    public static function isSmokeEntity(string $entityType): bool
    {
        return str_starts_with($entityType, 'GCalSmoke');
    }

    public static function testScope(): string
    {
        $scope = $_SERVER['GCAL_TEST_SCOPE'] ?? 'production';

        return in_array($scope, ['production', 'smoke', 'all'], true) ? $scope : 'production';
    }

    /**
     * @param array<string, mixed> $sourcesByEntity keyed by entity type
     * @return array<string, mixed>
     */
    public static function filterSourcesByScope(array $sourcesByEntity, ?string $scope = null): array
    {
        $scope ??= self::testScope();

        if ($scope === 'all') {
            return $sourcesByEntity;
        }

        return array_filter(
            $sourcesByEntity,
            static fn (string $entityType): bool => $scope === 'smoke'
                ? self::isSmokeEntity($entityType)
                : !self::isSmokeEntity($entityType),
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function makeTag(): string
    {
        return self::TEST_PREFIX . gmdate('Ymd-His');
    }

    public static function makeSuffix(): string
    {
        return substr(Util::generateId(), 0, 6);
    }

    public static function nameField(string $entityType): string
    {
        return in_array($entityType, self::PERSON_ENTITIES, true) ? 'lastName' : 'name';
    }

    public static function dayOffset(string $entityType): int
    {
        return match ($entityType) {
            'Account', 'Call' => 0,
            'Campaign', 'GCalSmokeAllDay' => 1,
            'GCalSmokeDateTime', 'GCalSmokeTwinDate' => 2,
            'Meeting', 'Contact' => 3,
            'Opportunity' => 4,
            'Task' => 5,
            default => 0,
        };
    }

    public static function pickTitle(
        string $entityType,
        string $suffix,
        ?string $variant = null,
        string $titleStyle = 'realistic'
    ): string {
        $catalog = $titleStyle === 'funny' ? self::FUNNY_TITLES : self::REALISTIC_TITLES;
        $list = $catalog[$entityType] ?? ($titleStyle === 'funny' ? ['Record buffo'] : ['Record calendario']);
        $idx = hexdec(substr($suffix, 0, 2)) % count($list);
        $base = $list[$idx];

        if (in_array($entityType, self::PERSON_ENTITIES, true)) {
            return $base;
        }

        $name = self::TEST_PREFIX . ' ' . $base;

        if ($variant !== null && $variant !== '') {
            $name .= ' (' . $variant . ')';
        }

        return $name;
    }

    /**
     * @param array{
     *   tag?: string,
     *   suffix?: string,
     *   baseDate?: DateTimeImmutable,
     *   adminId?: string|null,
     *   sources?: list<array{sourceDateType: string, dateField: string, endDateField?: mixed, allDay?: bool}>,
     *   variant?: string|null,
     *   titleStyle?: 'realistic'|'funny'
     * } $context
     * @return array<string, mixed>
     */
  /**
     * Interpret wall-clock local time in app TZ and return UTC storage strings.
     */
    public static function wallClockToUtcStorage(string $localDateTime, ?string $timeZone = null): string
    {
        $tz = new DateTimeZone($timeZone ?? self::DEFAULT_APP_TIMEZONE);
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $localDateTime, $tz);

        if ($parsed === false) {
            throw new RuntimeException('Could not parse wall-clock datetime `' . $localDateTime . '`.');
        }

        return $parsed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * @return array{0: string, 1: string} dateStart, dateEnd (UTC storage)
     */
    private static function resolveDateTimes(
        DateTimeImmutable $wallClockLocal,
        ?string $endTime = null,
        ?string $appTimeZone = null
    ): array {
        $tz = $appTimeZone ?? self::DEFAULT_APP_TIMEZONE;
        $localTz = new DateTimeZone($tz);
        $localStart = $wallClockLocal->format('Y-m-d H:i:s');

        if ($wallClockLocal->format('H:i:s') !== '00:00:00') {
            $startUtc = self::wallClockToUtcStorage($localStart, $tz);
            $localEnd = $endTime !== null
                ? $wallClockLocal->format('Y-m-d') . ' ' . $endTime
                : DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $localStart, $localTz)
                    ->modify('+1 hour')
                    ->format('Y-m-d H:i:s');
            $endUtc = self::wallClockToUtcStorage($localEnd, $tz);

            return [$startUtc, $endUtc];
        }

        $dateOnly = $wallClockLocal->format('Y-m-d');
        $defaultStart = self::wallClockToUtcStorage($dateOnly . ' 10:00:00', $tz);
        $defaultEnd = self::wallClockToUtcStorage($dateOnly . ' 11:00:00', $tz);

        return [$defaultStart, $defaultEnd];
    }

    public static function buildAttributes(string $entityType, array $context): array
    {
        $suffix = $context['suffix'] ?? self::makeSuffix();
        $appTimeZone = isset($context['appTimeZone']) ? (string) $context['appTimeZone'] : self::DEFAULT_APP_TIMEZONE;
        $baseDate = $context['baseDate'] ?? new DateTimeImmutable('+2 days', new DateTimeZone($appTimeZone));
        $d = $baseDate->format('Y-m-d');
        [$dt, $de] = self::resolveDateTimes(
            $baseDate,
            isset($context['endTime']) ? (string) $context['endTime'] : null,
            $appTimeZone
        );
        $adminId = $context['adminId'] ?? null;
        $variant = $context['variant'] ?? null;
        $sources = $context['sources'] ?? [];
        $titleStyle = ($context['titleStyle'] ?? 'realistic') === 'funny' ? 'funny' : 'realistic';

        return match ($entityType) {
            'Account' => [
                'name' => self::pickTitle('Account', $suffix, $variant, $titleStyle) . ' ' . $suffix,
                'cDataFirmaContratto' => $d,
            ],
            'Call' => [
                'name' => self::pickTitle('Call', $suffix, $variant, $titleStyle),
                'dateStart' => $dt,
                'dateEnd' => $de,
                'direction' => 'Outbound',
                'status' => 'Planned',
                'assignedUserId' => $adminId,
            ],
            'Campaign' => [
                'name' => self::pickTitle('Campaign', $suffix, $variant, $titleStyle),
                'startDate' => $d,
                'status' => 'Active',
            ],
            'GCalSmokeAllDay' => [
                'name' => self::pickTitle('GCalSmokeAllDay', $suffix, $variant, $titleStyle),
                'eventDate' => $d,
                'assignedUserId' => $adminId,
            ],
            'GCalSmokeDateTime' => [
                'name' => self::pickTitle('GCalSmokeDateTime', $suffix, $variant, $titleStyle),
                'dateStart' => $dt,
                'dateEnd' => $de,
                'assignedUserId' => $adminId,
            ],
            'GCalSmokeTwinDate' => [
                'name' => self::pickTitle('GCalSmokeTwinDate', $suffix, $variant, $titleStyle),
                'primaryDate' => $d,
                'reviewDate' => $baseDate->modify('+1 day')->format('Y-m-d'),
                'assignedUserId' => $adminId,
            ],
            'Meeting' => [
                'name' => self::pickTitle('Meeting', $suffix, $variant, $titleStyle),
                'dateStart' => $dt,
                'dateEnd' => $de,
                'status' => 'Planned',
                'assignedUserId' => $adminId,
            ],
            'Contact' => [
                'firstName' => $titleStyle === 'funny' ? 'Pippo' : 'Marco',
                'lastName' => self::TEST_PREFIX . self::pickTitle('Contact', $suffix, null, $titleStyle) . '-' . $suffix,
                'contactType' => 'Volunteer',
                'birthDate' => $d,
                'startDate' => $d,
                'endDate' => $baseDate->modify('+2 days')->format('Y-m-d'),
                'emailAddress' => 't-contact-' . $suffix . '@safehouse.test',
            ],
            'Opportunity' => [
                'name' => self::pickTitle('Opportunity', $suffix, $variant, $titleStyle),
                'presentationDate' => $d,
                'closeDate' => $baseDate->modify('+1 day')->format('Y-m-d'),
                'amount' => 1500.00,
                'amountCurrency' => 'EUR',
            ],
            'Task' => [
                'name' => self::pickTitle('Task', $suffix, $variant, $titleStyle),
                'status' => 'Not Started',
                'dateEndDate' => $d,
                'dateStartDate' => $d,
                'assignedUserId' => $adminId,
            ],
            default => self::buildGenericAttributes($entityType, $sources, $suffix, $d, $dt, $de, $variant, $titleStyle),
        };
    }

    /**
     * @param list<array{sourceDateType: string, dateField: string, endDateField?: mixed, allDay?: bool}> $sources
     * @return array<string, mixed>
     */
    private static function buildGenericAttributes(
        string $entityType,
        array $sources,
        string $suffix,
        string $eventDate,
        string $eventDateTime,
        string $eventDateTimeEnd,
        ?string $variant,
        string $titleStyle = 'realistic'
    ): array {
        $attrs = [
            'name' => self::pickTitle($entityType, $suffix, $variant, $titleStyle),
        ];

        if ($variant !== null && $variant !== '') {
            $attrs['name'] .= ' (' . $variant . ')';
        }

        foreach ($sources as $source) {
            $field = (string) ($source['dateField'] ?? '');

            if ($field === '') {
                continue;
            }

            $allDay = (bool) ($source['allDay'] ?? false);
            $endField = $source['endDateField'] ?? null;

            if ($allDay) {
                $attrs[$field] = $eventDate;
            } else {
                $attrs[$field] = str_contains(strtolower($field), 'end') ? $eventDateTimeEnd : $eventDateTime;
            }

            if (is_string($endField) && $endField !== '') {
                $attrs[$endField] = $allDay ? $eventDate : $eventDateTimeEnd;
            }
        }

        return $attrs;
    }

    /**
     * @param list<array{sourceDateType: string, dateField: string}> $sources
     */
    public static function createRecord(
        EntityManager $em,
        string $entityType,
        array $context,
        array $sources = []
    ): ?Entity {
        $entity = $em->getNewEntity($entityType);
        $context['sources'] = $sources;
        $entity->set(self::buildAttributes($entityType, $context));

        try {
            $em->saveEntity($entity);

            return $entity;
        } catch (Throwable) {
            return null;
        }
    }

    public static function fillDateFields(
        Entity $entity,
        array $sources,
        string $eventDate,
        string $eventDateTime,
        string $eventDateTimeEnd
    ): void {
        foreach ($sources as $source) {
            $field = $source['dateField'] ?? '';

            if ($field === '') {
                continue;
            }

            $type = $entity->getAttributeType($field);

            if ($type === 'date') {
                $entity->set($field, $eventDate);
            } elseif ($type === 'datetime' || in_array($field, ['dateStart', 'dateEnd'], true)) {
                $entity->set(
                    $field,
                    str_contains($field, 'End') || $field === 'dateEnd' ? $eventDateTimeEnd : $eventDateTime
                );
            } else {
                $entity->set($field, $eventDate);
            }
        }
    }

    /**
     * @param array<int, string> $sourceDateTypes
     * @return array<int, array<string, mixed>>
     */
    public static function buildEventSettings(array $sourceDateTypes, string $location = '{{name}}'): array
    {
        $rows = [];

        foreach ($sourceDateTypes as $sourceDateType) {
            $rows[] = [
                'sourceDateType' => $sourceDateType,
                'reminderMode' => 'none',
                'reminders' => [],
                'location' => $location,
                'visibility' => 'default',
                'transparency' => 'opaque',
                'colorId' => '',
                'descriptionTemplateOverride' => '',
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function cleanupPrefixes(bool $includeLegacy = false): array
    {
        $prefixes = [self::TEST_PREFIX];

        if ($includeLegacy) {
            $prefixes = array_merge($prefixes, self::LEGACY_PREFIXES);
        }

        return $prefixes;
    }
}
