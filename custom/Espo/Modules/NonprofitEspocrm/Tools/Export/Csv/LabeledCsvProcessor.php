<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Export\Csv;

use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Language;
use Espo\Entities\Preferences;
use Espo\ORM\Entity;
use Espo\Tools\Export\Collection;
use Espo\Tools\Export\Format\Xlsx\FieldHelper;
use Espo\Tools\Export\Processor as ProcessorInterface;
use Espo\Tools\Export\Processor\Params;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use const JSON_UNESCAPED_UNICODE;

/**
 * CSV export with translated column headers (core CSV writes raw attribute names).
 */
class LabeledCsvProcessor implements ProcessorInterface
{
    public function __construct(
        private Config $config,
        private Preferences $preferences,
        private Language $language,
        private FieldHelper $fieldHelper,
    ) {}

    public function process(Params $params, Collection $collection): StreamInterface
    {
        $attributeList = $params->getAttributeList();
        $entityType = $params->getEntityType();

        $delimiterRaw =
            $this->preferences->get('exportDelimiter') ??
            $this->config->get('exportDelimiter') ??
            ',';

        $delimiter = str_replace('\t', "\t", $delimiterRaw);

        $fp = fopen('php://temp', 'w');

        if ($fp === false) {
            throw new RuntimeException('Could not open temp.');
        }

        $headerLabels = [];

        foreach ($attributeList as $attribute) {
            $headerLabels[] = $this->translateLabel($entityType, $attribute);
        }

        fputcsv($fp, $headerLabels, $delimiter);

        foreach ($collection as $entity) {
            $preparedRow = $this->prepareRow($entity, $entityType, $attributeList);

            fputcsv($fp, $preparedRow, $delimiter, '"', "\0");
        }

        rewind($fp);

        return new Stream($fp);
    }

    /**
     * @param string[] $attributeList
     * @return string[]
     */
    private function prepareRow(Entity $entity, string $entityType, array $attributeList): array
    {
        $preparedRow = [];

        foreach ($attributeList as $attribute) {
            $value = $this->formatCellValue($entity, $entityType, $attribute);

            if (is_array($value) || is_object($value)) {
                $value = Json::encode($value, JSON_UNESCAPED_UNICODE);
            }

            $value = (string) $value;

            $preparedRow[] = $this->sanitizeCellValue($value);
        }

        return $preparedRow;
    }

    private function formatCellValue(Entity $entity, string $entityType, string $attribute): mixed
    {
        if (!$entity->has($attribute)) {
            return '';
        }

        $value = $entity->get($attribute);

        if ($value === null || $value === '') {
            return $value;
        }

        $fieldData = $this->fieldHelper->getData($entityType, $attribute);

        if ($fieldData === null) {
            return $value;
        }

        if ($fieldData->getType() === FieldType::ENUM) {
            return $this->language->translateOption(
                (string) $value,
                $fieldData->getField(),
                $fieldData->getEntityType()
            );
        }

        return $value;
    }

    private function sanitizeCellValue(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (in_array($value[0], ['+', '-', '@', '='], true)) {
            return "'" . $value;
        }

        return $value;
    }

    private function translateLabel(string $entityType, string $name): string
    {
        $label = $name;

        $fieldData = $this->fieldHelper->getData($entityType, $name);
        $isForeignReference = $this->fieldHelper->isForeignReference($name);

        if ($isForeignReference && $fieldData && $fieldData->getLink()) {
            $label =
                $this->language->translateLabel($fieldData->getLink(), 'links', $entityType) . ' . ' .
                $this->language->translateLabel($fieldData->getField(), 'fields', $fieldData->getEntityType());
        }

        if (!$isForeignReference) {
            $label = $this->language->translateLabel($name, 'fields', $entityType);
        }

        return $label;
    }
}
