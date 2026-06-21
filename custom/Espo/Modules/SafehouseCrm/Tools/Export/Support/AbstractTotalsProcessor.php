<?php

namespace Espo\Modules\SafehouseCrm\Tools\Export\Support;

use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Modules\SafehouseCrm\Tools\Reporting\ReportingEntityProfile;
use Espo\Modules\SafehouseCrm\Tools\Reporting\ReportingProfileRegistry;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\Export\Collection;
use Espo\Tools\Export\Processor;
use Espo\Tools\Export\Processor\Params;
use Psr\Http\Message\StreamInterface;

/**
 * Wraps a core export {@see Processor} and appends a DB-consistent totals row
 * for Safehouse reporting entities (MealCount, later AssociationMealCount).
 *
 * Behaviour:
 *  - Non-reporting entity, or totals turned off → pass through untouched.
 *  - Reporting entity → buffer the (already ACL/column filtered) collection,
 *    sum the profile's numeric attributes that are actually being exported, and
 *    append a synthetic totals entity so the core processor formats it natively.
 *
 * The totals therefore always match exactly what was exported (same filters,
 * same field-level ACL, same selected columns) without re-querying.
 */
abstract class AbstractTotalsProcessor implements Processor
{
    /** Export param toggled from the export modal (default on for reporting). */
    public const PARAM_INCLUDE_TOTALS = 'includeTotals';

    public function __construct(
        protected ReportingProfileRegistry $profileRegistry,
        protected EntityManager $entityManager,
        protected Config $config,
        protected Language $language,
    ) {}

    abstract protected function getInnerProcessor(): Processor;

    public function process(Params $params, Collection $collection): StreamInterface
    {
        $profile = $this->profileRegistry->getProfile($params->getEntityType());

        if ($profile === null || !$this->isTotalsRequested($params)) {
            return $this->getInnerProcessor()->process($params, $collection);
        }

        // A totals row needs a text caption cell. A caption written into a
        // numeric/date column is coerced away by that column's value formatter
        // (int -> 0, date -> blank), so resolve a type-safe target first and,
        // as a last resort, inject the label column so the row is always
        // identifiable. This keeps CSV and XLSX behaviour identical.
        [$params, $labelAttribute] = $this->resolveLabelTarget($profile, $params);

        $effectiveCollection = $this->buildCollectionWithTotals($profile, $params, $labelAttribute, $collection);

        return $this->getInnerProcessor()->process($params, $effectiveCollection);
    }

    /**
     * @return array{Params, string} Possibly column-augmented params and the
     *     attribute that will carry the "Totals" caption.
     */
    private function resolveLabelTarget(ReportingEntityProfile $profile, Params $params): array
    {
        $attributeList = $params->getAttributeList();
        $entityDefs = $this->entityManager->getDefs()->getEntity($profile->entityType);

        // 1. Configured label attribute (e.g. name) is already exported.
        if (in_array($profile->totalsLabelAttribute, $attributeList, true)) {
            return [$params, $profile->totalsLabelAttribute];
        }

        // 2. Reuse the first exported text column — no extra column needed.
        foreach ($attributeList as $attribute) {
            if (in_array($attribute, $profile->exportTotalAttributes, true)) {
                continue;
            }

            if ($this->isTextAttribute($entityDefs, $attribute)) {
                return [$params, $attribute];
            }
        }

        // 3. Only numeric/date columns selected: inject the label column up front.
        $labelAttribute = $profile->totalsLabelAttribute;

        $params = $params->withAttributeList([$labelAttribute, ...$attributeList]);

        $fieldList = $params->getFieldList();

        if ($fieldList !== null && !in_array($labelAttribute, $fieldList, true)) {
            $params = $params->withFieldList([$labelAttribute, ...$fieldList]);
        }

        return [$params, $labelAttribute];
    }

    private function isTextAttribute(\Espo\ORM\Defs\EntityDefs $entityDefs, string $attribute): bool
    {
        if (!$entityDefs->hasField($attribute)) {
            return false;
        }

        return in_array(
            $entityDefs->getField($attribute)->getType(),
            [FieldType::VARCHAR, FieldType::TEXT],
            true
        );
    }

    private function isTotalsRequested(Params $params): bool
    {
        $value = $params->getParam(self::PARAM_INCLUDE_TOTALS);

        // Default: on for reporting entities (incl. API exports without the flag).
        return $value === null ? true : (bool) $value;
    }

    private function buildCollectionWithTotals(
        ReportingEntityProfile $profile,
        Params $params,
        string $labelAttribute,
        Collection $collection,
    ): Collection {
        $sums = array_fill_keys($profile->exportTotalAttributes, 0.0);
        $hasValue = array_fill_keys($profile->exportTotalAttributes, false);

        $entities = [];

        foreach ($collection as $entity) {
            $entities[] = $entity;

            foreach ($profile->exportTotalAttributes as $attribute) {
                $value = $entity->get($attribute);

                if (is_numeric($value)) {
                    $sums[$attribute] += (float) $value;
                    $hasValue[$attribute] = true;
                }
            }
        }

        $totalsEntity = $this->buildTotalsEntity($profile, $labelAttribute, $sums, $hasValue);

        return new BufferedExportCollection($entities, $totalsEntity);
    }

    /**
     * @param array<string, float> $sums
     * @param array<string, bool> $hasValue
     */
    private function buildTotalsEntity(
        ReportingEntityProfile $profile,
        string $labelAttribute,
        array $sums,
        array $hasValue,
    ): Entity {
        $entity = $this->entityManager->getNewEntity($profile->entityType);

        // Strip field defaults (e.g. foodUnitPrice = 1.5) so only summed columns
        // and the label appear in the totals row.
        foreach ($entity->getAttributeList() as $attribute) {
            $entity->clear($attribute);
        }

        $entityDefs = $this->entityManager->getDefs()->getEntity($profile->entityType);
        $defaultCurrency = (string) ($this->config->get('defaultCurrency') ?: 'EUR');

        foreach ($profile->exportTotalAttributes as $attribute) {
            // Only render a total where at least one exported row contributed.
            if (!($hasValue[$attribute] ?? false)) {
                continue;
            }

            $fieldType = $entityDefs->hasField($attribute)
                ? $entityDefs->getField($attribute)->getType()
                : null;

            if ($fieldType === FieldType::INT) {
                $entity->set($attribute, (int) round($sums[$attribute]));
            } else {
                $entity->set($attribute, $sums[$attribute]);
            }

            if ($fieldType === FieldType::CURRENCY) {
                $entity->set($attribute . 'Currency', $defaultCurrency);
            }
        }

        if ($labelAttribute !== '') {
            $entity->set($labelAttribute, $this->getTotalsLabel());
        }

        return $entity;
    }

    protected function getTotalsLabel(): string
    {
        $label = $this->language->translateLabel('reportingExportTotalsLabel', 'labels', 'Global');

        return $label === 'reportingExportTotalsLabel' ? 'Totals' : $label;
    }
}
