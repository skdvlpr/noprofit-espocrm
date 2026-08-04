<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Export\Xlsx;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Modules\NonprofitEspocrm\Tools\Export\Support\AbstractTotalsProcessor;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\ReportingProfileRegistry;
use Espo\ORM\EntityManager;
use Espo\Tools\Export\Collection;
use Espo\Tools\Export\Format\Xlsx\Processor as CoreXlsxProcessor;
use Espo\Tools\Export\Processor;
use Espo\Tools\Export\Processor\Params;
use GuzzleHttp\Psr7\Utils;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * XLSX export with totals row styling.
 *
 * Core PhpSpreadsheet applies number-format styles one row past the last data
 * row (empty phantom row). We therefore locate the last non-empty row before
 * styling — never trust getHighestDataRow() alone.
 */
class TotalsProcessor extends AbstractTotalsProcessor
{
    public function __construct(
        ReportingProfileRegistry $profileRegistry,
        EntityManager $entityManager,
        Config $config,
        Language $language,
        private CoreXlsxProcessor $coreProcessor,
    ) {
        parent::__construct($profileRegistry, $entityManager, $config, $language);
    }

    protected function getInnerProcessor(): Processor
    {
        return $this->coreProcessor;
    }

    public function process(Params $params, Collection $collection): StreamInterface
    {
        $stream = parent::process($params, $collection);

        if (!$this->isTotalsRequestedForParams($params)) {
            return $stream;
        }

        if ($params->getParam('lite')) {
            return $stream;
        }

        return $this->styleTotalsSheet($stream);
    }

    private function styleTotalsSheet(StreamInterface $stream): StreamInterface
    {
        $contents = (string) $stream;

        if ($contents === '') {
            return $stream;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'espo-xlsx-totals');

        if ($tmp === false) {
            return $stream;
        }

        $xlsxPath = $tmp . '.xlsx';
        @unlink($tmp);

        if (file_put_contents($xlsxPath, $contents) === false) {
            return $stream;
        }

        try {
            $spreadsheet = IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();

            $totalsRow = $this->findLastNonEmptyRow($sheet);

            if ($totalsRow < 2) {
                @unlink($xlsxPath);

                return $stream;
            }

            $highestColumn = $sheet->getHighestDataColumn($totalsRow);
            $headerRow = $this->findHeaderRow($sheet, $totalsRow);

            $this->normalizeTotalsMarkerHeader($sheet, $headerRow);

            // Entity::set ignores unknown attributes — stamp Total caption here.
            $sheet->setCellValueExplicit(
                'A' . $totalsRow,
                $this->getTotalsCaption(),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );

            $pastel = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::TOTALS_ROW_FILL_RGB],
                ],
            ];

            // Paint the Total column (first column) from header through totals.
            $sheet->getStyle('A' . $headerRow . ':A' . $totalsRow)->applyFromArray($pastel);

            // Bold + pastel on the totals data row (caption, count, sums).
            $sheet->getStyle('A' . $totalsRow . ':' . $highestColumn . $totalsRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::TOTALS_ROW_FILL_RGB],
                ],
            ]);

            return $this->writeSpreadsheetStream($spreadsheet, $xlsxPath);
        } catch (\Throwable $e) {
            @unlink($xlsxPath);

            throw new RuntimeException(
                'Failed to style export totals row: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Last row that has any non-empty cell (skips PhpSpreadsheet phantom rows).
     */
    private function findLastNonEmptyRow(Worksheet $sheet): int
    {
        $maxRow = (int) $sheet->getHighestRow();
        $maxCol = $sheet->getHighestColumn();

        for ($row = $maxRow; $row >= 1; $row--) {
            $values = $sheet->rangeToArray('A' . $row . ':' . $maxCol . $row, null, true, false)[0] ?? [];

            foreach ($values as $value) {
                if ($value !== null && $value !== '') {
                    return $row;
                }
            }
        }

        return 1;
    }

    private function findHeaderRow(Worksheet $sheet, int $totalsRow): int
    {
        // Prefer row 1; if title block is present, header is usually row 3.
        for ($row = 1; $row < $totalsRow; $row++) {
            $value = $sheet->getCell('A' . $row)->getValue();

            if ($value === self::TOTALS_MARKER_ATTRIBUTE || $value === '' || $value === null) {
                $b = $sheet->getCell('B' . $row)->getValue();

                if ($b !== null && $b !== '') {
                    return $row;
                }
            }

            if (is_string($value) && $value !== '' && $value !== self::TOTALS_MARKER_ATTRIBUTE) {
                // Could be title — keep scanning for marker / ID header nearby.
                continue;
            }
        }

        return 1;
    }

    private function normalizeTotalsMarkerHeader(Worksheet $sheet, int $headerRow): void
    {
        $value = $sheet->getCell('A' . $headerRow)->getValue();

        if ($value === self::TOTALS_MARKER_ATTRIBUTE) {
            $sheet->setCellValue('A' . $headerRow, '');
        }
    }

    private function writeSpreadsheetStream(Spreadsheet $spreadsheet, string $xlsxPath): StreamInterface
    {
        $out = tempnam(sys_get_temp_dir(), 'espo-xlsx-out');

        if ($out === false) {
            @unlink($xlsxPath);

            throw new RuntimeException('Could not create temp file for styled XLSX.');
        }

        $outPath = $out . '.xlsx';
        @unlink($out);

        $writer = new XlsxWriter($spreadsheet);
        $writer->save($outPath);

        $styled = file_get_contents($outPath);
        @unlink($xlsxPath);
        @unlink($outPath);

        if ($styled === false) {
            throw new RuntimeException('Could not read styled XLSX.');
        }

        return Utils::streamFor($styled);
    }
}
