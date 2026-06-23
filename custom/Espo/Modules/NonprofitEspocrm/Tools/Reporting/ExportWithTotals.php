<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reporting;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Select\SearchParams;
use Espo\Core\Utils\Config;
use Espo\Entities\Preferences;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Appends a DB-aggregated totals row to CSV export streams for reporting entities.
 */
class ExportWithTotals
{
    public function __construct(
        private ReportingAggregateQuery $aggregateQuery,
        private ReportingProfileRegistry $profileRegistry,
        private Config $config,
        private Preferences $preferences,
    ) {}

    /**
     * @param string[] $attributeList Export column order (attribute names — matches Espo CSV header).
     */
    public function appendTotalsRowToCsv(
        StreamInterface $csvStream,
        string $entityType,
        array $attributeList,
        ?SearchParams $searchParams = null,
        ?array $additionalWhere = null,
        string $totalsLabel = 'Totals',
    ): StreamInterface {
        $profile = $this->profileRegistry->getProfile($entityType);

        if ($profile === null) {
            return $csvStream;
        }

        $sumAttributes = array_values(array_filter(
            $profile->exportTotalAttributes,
            static fn (string $attribute): bool => in_array($attribute, $attributeList, true)
        ));

        if ($sumAttributes === []) {
            return $csvStream;
        }

        $totals = $this->aggregateQuery->sum(
            $entityType,
            $sumAttributes,
            $searchParams,
            $additionalWhere,
        );

        $totalsRow = $this->buildTotalsRow(
            $attributeList,
            $profile->totalsLabelAttribute,
            $totalsLabel,
            $totals,
        );

        $delimiter = $this->resolveDelimiter();

        $csvStream->rewind();
        $content = $csvStream->getContents();

        $fp = fopen('php://temp', 'w+');

        if ($fp === false) {
            throw new RuntimeException('Could not open temp stream for export totals.');
        }

        $readFp = fopen('php://memory', 'r+');

        if ($readFp === false) {
            throw new RuntimeException('Could not open memory stream for export totals.');
        }

        fwrite($readFp, $content);
        rewind($readFp);

        while (($row = fgetcsv($readFp, 0, $delimiter, '"', "\0")) !== false) {
            fputcsv($fp, $row, $delimiter, '"', "\0");
        }

        fclose($readFp);

        fputcsv($fp, $totalsRow, $delimiter, '"', "\0");

        rewind($fp);

        return new Stream($fp);
    }

    public function buildTotalsRowForProfile(
        string $entityType,
        array $attributeList,
        ?SearchParams $searchParams = null,
        ?array $additionalWhere = null,
        string $totalsLabel = 'Totals',
    ): array {
        $profile = $this->profileRegistry->getProfile($entityType);

        if ($profile === null) {
            throw new BadRequest("Entity $entityType is not a reporting entity.");
        }

        $sumAttributes = array_values(array_filter(
            $profile->exportTotalAttributes,
            static fn (string $attribute): bool => in_array($attribute, $attributeList, true)
        ));

        if ($sumAttributes === []) {
            throw new BadRequest('No summable export columns in attribute list.');
        }

        $totals = $this->aggregateQuery->sum(
            $entityType,
            $sumAttributes,
            $searchParams,
            $additionalWhere,
        );

        return $this->buildTotalsRow(
            $attributeList,
            $profile->totalsLabelAttribute,
            $totalsLabel,
            $totals,
        );
    }

    /**
     * @param array<string, float> $totals
     * @return string[]
     */
    private function buildTotalsRow(
        array $attributeList,
        string $labelAttribute,
        string $totalsLabel,
        array $totals,
    ): array {
        $row = [];

        foreach ($attributeList as $attribute) {
            if ($attribute === $labelAttribute) {
                $row[] = $totalsLabel;
                continue;
            }

            if (array_key_exists($attribute, $totals)) {
                $row[] = $this->formatTotalCell($totals[$attribute]);
                continue;
            }

            $row[] = '';
        }

        return $row;
    }

    private function formatTotalCell(float $value): string
    {
        if (fmod($value, 1.0) === 0.0) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
    }

    private function resolveDelimiter(): string
    {
        $delimiterRaw =
            $this->preferences->get('exportDelimiter') ??
            $this->config->get('exportDelimiter') ??
            ',';

        return str_replace('\t', "\t", (string) $delimiterRaw);
    }
}
