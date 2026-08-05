<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Export\Support;

use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\ReportingEntityProfile;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\ReportingProfileRegistry;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\Export\Collection;
use Espo\Tools\Export\Processor;
use Espo\Tools\Export\Processor\Params;
use Psr\Http\Message\StreamInterface;

/**
 * Wraps a core export {@see Processor} and appends a totals row when requested.
 *
 * When totals are on:
 * - Injects a first column {@see self::TOTALS_MARKER_ATTRIBUTE} with CRM-language
 *   "Total" / "Totale" on the totals row (empty on data rows).
 * - Puts the record count under the name/text column.
 * - Sums every typed numeric column in the export extraction
 *   (int/float/currency/decimal/… — never varchar-with-digits).
 */
abstract class AbstractTotalsProcessor implements Processor
{
    /** Export param toggled from the export modal. */
    public const PARAM_INCLUDE_TOTALS = 'includeTotals';

    /**
     * Synthetic first-column attribute for the Total caption.
     * Not an entityDefs field — processors treat it as a plain string cell.
     */
    public const TOTALS_MARKER_ATTRIBUTE = '__exportTotalsLabel';

    /** Light yellow fill for the XLSX totals row only (not the Totale column). */
    public const TOTALS_ROW_FILL_RGB = 'FFF2CC';

    /** @var list<string> */
    private const NUMERIC_FIELD_TYPES = [
        FieldType::INT,
        FieldType::FLOAT,
        FieldType::CURRENCY,
        FieldType::CURRENCY_CONVERTED,
        FieldType::DECIMAL,
        FieldType::NUMBER,
        FieldType::AUTOINCREMENT,
    ];

    /** @var list<string> */
    private const TEXT_FIELD_TYPES = [
        FieldType::VARCHAR,
        FieldType::TEXT,
        FieldType::ENUM,
        FieldType::PERSON_NAME,
        FieldType::EMAIL,
        FieldType::PHONE,
        FieldType::URL,
        FieldType::WYSIWYG,
    ];

    public function __construct(
        protected ReportingProfileRegistry $profileRegistry,
        protected EntityManager $entityManager,
        protected Config $config,
        protected Language $language,
    ) {}

    abstract protected function getInnerProcessor(): Processor;

    public function process(Params $params, Collection $collection): StreamInterface
    {
        $profile = $this->resolveProfile($params);

        if ($profile === null || !$this->shouldAppendTotals($params)) {
            return $this->getInnerProcessor()->process($params, $collection);
        }

        [$params, $countAttribute] = $this->resolveCountTarget($profile, $params);
        $params = $this->injectTotalsMarkerColumn($params);
        $sumAttributes = $this->resolveSumAttributesFromExport($params);

        $effectiveCollection = $this->buildCollectionWithTotals(
            $profile,
            $params,
            $countAttribute,
            $sumAttributes,
            $collection
        );

        return $this->getInnerProcessor()->process($params, $effectiveCollection);
    }

    private function resolveProfile(Params $params): ?ReportingEntityProfile
    {
        $registered = $this->profileRegistry->getProfile($params->getEntityType());

        if ($registered !== null) {
            return $registered;
        }

        if (!$this->isExplicitTotalsOn($params)) {
            return null;
        }

        return $this->buildGenericProfile($params);
    }

    private function buildGenericProfile(Params $params): ReportingEntityProfile
    {
        $entityType = $params->getEntityType();
        $attributeList = $params->getAttributeList();
        $sumAttributes = $this->resolveSumAttributesFromExport($params);
        $entityDefs = $this->entityManager->getDefs()->getEntity($entityType);
        $labelAttribute = $this->resolveGenericLabelAttribute($entityDefs, $attributeList);

        return new ReportingEntityProfile(
            $entityType,
            'createdAt',
            $sumAttributes,
            $sumAttributes,
            $labelAttribute,
        );
    }

    /**
     * Numeric fields present in this export extraction (fieldList when set,
     * otherwise attributeList). Profile allow-lists are intentionally ignored:
     * when totals are on, every typed numeric column in the export is summed.
     *
     * @return list<string>
     */
    private function resolveSumAttributesFromExport(Params $params): array
    {
        $entityDefs = $this->entityManager->getDefs()->getEntity($params->getEntityType());
        $candidates = $params->getFieldList() ?? $params->getAttributeList();
        $sumAttributes = [];

        foreach ($candidates as $name) {
            if ($name === self::TOTALS_MARKER_ATTRIBUTE) {
                continue;
            }

            if (!$entityDefs->hasField($name)) {
                continue;
            }

            $type = $entityDefs->getField($name)->getType();

            if (!in_array($type, self::NUMERIC_FIELD_TYPES, true)) {
                continue;
            }

            $sumAttributes[] = $name;
        }

        return $sumAttributes;
    }

    /**
     * @param list<string> $attributeList
     */
    private function resolveGenericLabelAttribute(
        \Espo\ORM\Defs\EntityDefs $entityDefs,
        array $attributeList
    ): string {
        foreach (['name', 'firstName', 'title', 'subject'] as $candidate) {
            if (
                in_array($candidate, $attributeList, true)
                && $entityDefs->hasField($candidate)
                && $this->isTextAttribute($entityDefs, $candidate)
            ) {
                return $candidate;
            }
        }

        foreach ($attributeList as $attribute) {
            if ($attribute === self::TOTALS_MARKER_ATTRIBUTE) {
                continue;
            }

            if ($this->isTextAttribute($entityDefs, $attribute)) {
                return $attribute;
            }
        }

        if ($entityDefs->hasField('name')) {
            return 'name';
        }

        return $attributeList[0] ?? 'id';
    }

    /**
     * Attribute that receives the record count on the totals row.
     *
     * @return array{Params, string}
     */
    private function resolveCountTarget(ReportingEntityProfile $profile, Params $params): array
    {
        $attributeList = $params->getAttributeList();
        $entityDefs = $this->entityManager->getDefs()->getEntity($profile->entityType);
        $sumAttributes = $this->resolveSumAttributesFromExport($params);

        if (in_array($profile->totalsLabelAttribute, $attributeList, true)) {
            return [$params, $profile->totalsLabelAttribute];
        }

        foreach ($attributeList as $attribute) {
            if (in_array($attribute, $sumAttributes, true)) {
                continue;
            }

            if ($this->isTextAttribute($entityDefs, $attribute)) {
                return [$params, $attribute];
            }
        }

        $labelAttribute = $profile->totalsLabelAttribute;

        $params = $params->withAttributeList([$labelAttribute, ...$attributeList]);

        $fieldList = $params->getFieldList();

        if ($fieldList !== null && !in_array($labelAttribute, $fieldList, true)) {
            $params = $params->withFieldList([$labelAttribute, ...$fieldList]);
        }

        return [$params, $labelAttribute];
    }

    private function injectTotalsMarkerColumn(Params $params): Params
    {
        $attributeList = $params->getAttributeList();

        if (in_array(self::TOTALS_MARKER_ATTRIBUTE, $attributeList, true)) {
            return $params;
        }

        $params = $params->withAttributeList([self::TOTALS_MARKER_ATTRIBUTE, ...$attributeList]);

        $fieldList = $params->getFieldList();

        if ($fieldList !== null && !in_array(self::TOTALS_MARKER_ATTRIBUTE, $fieldList, true)) {
            $params = $params->withFieldList([self::TOTALS_MARKER_ATTRIBUTE, ...$fieldList]);
        }

        return $params;
    }

    private function isTextAttribute(\Espo\ORM\Defs\EntityDefs $entityDefs, string $attribute): bool
    {
        if (!$entityDefs->hasField($attribute)) {
            return false;
        }

        return in_array(
            $entityDefs->getField($attribute)->getType(),
            self::TEXT_FIELD_TYPES,
            true
        );
    }

    private function shouldAppendTotals(Params $params): bool
    {
        if ($this->profileRegistry->getProfile($params->getEntityType()) !== null) {
            return $this->isTotalsRequested($params);
        }

        return $this->isExplicitTotalsOn($params);
    }

    private function isTotalsRequested(Params $params): bool
    {
        $value = $params->getParam(self::PARAM_INCLUDE_TOTALS);

        return $value === null ? true : (bool) $value;
    }

    private function isExplicitTotalsOn(Params $params): bool
    {
        $value = $params->getParam(self::PARAM_INCLUDE_TOTALS);

        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    private function buildCollectionWithTotals(
        ReportingEntityProfile $profile,
        Params $params,
        string $countAttribute,
        array $sumAttributes,
        Collection $collection,
    ): Collection {
        $sums = array_fill_keys($sumAttributes, 0.0);
        $hasValue = array_fill_keys($sumAttributes, false);

        $entities = [];

        foreach ($collection as $entity) {
            $entity->set(self::TOTALS_MARKER_ATTRIBUTE, '');
            $entities[] = $entity;

            foreach ($sumAttributes as $attribute) {
                $value = $entity->get($attribute);

                if (is_numeric($value)) {
                    $sums[$attribute] += (float) $value;
                    $hasValue[$attribute] = true;
                }
            }
        }

        $totalsEntity = $this->buildTotalsEntity(
            $profile,
            $countAttribute,
            $sumAttributes,
            $sums,
            $hasValue,
            count($entities)
        );

        return new BufferedExportCollection($entities, $totalsEntity);
    }

    /**
     * @param list<string> $sumAttributes
     * @param array<string, float> $sums
     * @param array<string, bool> $hasValue
     */
    private function buildTotalsEntity(
        ReportingEntityProfile $profile,
        string $countAttribute,
        array $sumAttributes,
        array $sums,
        array $hasValue,
        int $recordCount,
    ): Entity {
        $entity = $this->entityManager->getNewEntity($profile->entityType);

        foreach ($entity->getAttributeList() as $attribute) {
            $entity->clear($attribute);
        }

        $entityDefs = $this->entityManager->getDefs()->getEntity($profile->entityType);
        $defaultCurrency = (string) ($this->config->get('defaultCurrency') ?: 'EUR');

        foreach ($sumAttributes as $attribute) {
            if (!($hasValue[$attribute] ?? false)) {
                continue;
            }

            $fieldType = $entityDefs->hasField($attribute)
                ? $entityDefs->getField($attribute)->getType()
                : null;

            if ($fieldType === FieldType::INT || $fieldType === FieldType::AUTOINCREMENT) {
                $entity->set($attribute, (int) round($sums[$attribute]));
            } else {
                $entity->set($attribute, $sums[$attribute]);
            }

            if (
                $fieldType === FieldType::CURRENCY
                || $fieldType === FieldType::CURRENCY_CONVERTED
            ) {
                $entity->set($attribute . 'Currency', $defaultCurrency);
            }
        }

        // Caption is applied in format-specific post-process (Entity::set ignores
        // unknown attributes). Keep the marker attribute empty on the entity.
        $entity->set(self::TOTALS_MARKER_ATTRIBUTE, '');

        if ($countAttribute !== '' && $countAttribute !== self::TOTALS_MARKER_ATTRIBUTE) {
            $entity->set($countAttribute, $this->getTotalsRecordCount($recordCount));
        }

        return $entity;
    }

    /** CRM-language caption for the Total column (last row). */
    protected function getTotalsCaption(): string
    {
        $label = $this->language->translateLabel('reportingExportTotalsLabel', 'labels', 'Global');

        if ($label === 'reportingExportTotalsLabel') {
            return 'Total';
        }

        return $label;
    }

    /** Record count shown under the name/text column of the totals row. */
    protected function getTotalsRecordCount(int $recordCount): string
    {
        return (string) $recordCount;
    }

    protected function isTotalsRequestedForParams(Params $params): bool
    {
        return $this->shouldAppendTotals($params) && $this->resolveProfile($params) !== null;
    }
}
