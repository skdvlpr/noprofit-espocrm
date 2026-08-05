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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * XLSX export with totals row styling + visible cell borders.
 *
 * Core PhpSpreadsheet applies number-format styles one row past the last data
 * row (empty phantom row). We therefore locate the last non-empty row before
 * styling — never trust getHighestDataRow() alone.
 */
class TotalsProcessor extends AbstractTotalsProcessor
{
    private const BORDER_RGB = 'A8B5A8';

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

        // Lite (OpenSpout) path has limited styling.
        if ($params->getParam('lite')) {
            return $stream;
        }

        return $this->styleSheet(
            $stream,
            $this->isTotalsRequestedForParams($params)
        );
    }

    private function styleSheet(StreamInterface $stream, bool $withTotals): StreamInterface
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

            $lastRow = $this->findLastNonEmptyRow($sheet);

            if ($lastRow < 1) {
                @unlink($xlsxPath);

                return $stream;
            }

            $headerRow = $this->findHeaderRow($sheet, $lastRow);
            $highestColumn = $sheet->getHighestDataColumn($lastRow);

            if ($withTotals) {
                $this->normalizeTotalsMarkerHeader($sheet, $headerRow);

                $sheet->setCellValueExplicit(
                    'A' . $lastRow,
                    $this->getTotalsCaption(),
                    DataType::TYPE_STRING
                );

                // Style only the totals row (light yellow + bold) — not column A.
                $sheet->getStyle('A' . $lastRow . ':' . $highestColumn . $lastRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => self::TOTALS_ROW_FILL_RGB],
                    ],
                ]);
            }

            // Visible borders on every cell in the used export table
            // (header → last data/totals row, all columns including Total marker).
            $tableStart = $headerRow;
            $sheet->getStyle('A' . $tableStart . ':' . $highestColumn . $lastRow)
                ->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => self::BORDER_RGB],
                        ],
                    ],
                ]);

            // Title / timestamp rows above the table (when present).
            if ($headerRow > 1) {
                $sheet->getStyle('A1:' . $highestColumn . ($headerRow - 1))
                    ->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => self::BORDER_RGB],
                            ],
                        ],
                    ]);
            }

            return $this->writeSpreadsheetStream($spreadsheet, $xlsxPath);
        } catch (\Throwable $e) {
            @unlink($xlsxPath);

            throw new RuntimeException(
                'Failed to style export sheet: ' . $e->getMessage(),
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

    private function findHeaderRow(Worksheet $sheet, int $lastRow): int
    {
        for ($row = 1; $row < $lastRow; $row++) {
            $value = $sheet->getCell('A' . $row)->getValue();

            if ($value === self::TOTALS_MARKER_ATTRIBUTE || $value === '' || $value === null) {
                $b = $sheet->getCell('B' . $row)->getValue();

                if ($b !== null && $b !== '') {
                    return $row;
                }
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
