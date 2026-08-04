<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Export\Csv;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Modules\NonprofitEspocrm\Tools\Export\Support\AbstractTotalsProcessor;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\ReportingProfileRegistry;
use Espo\ORM\EntityManager;
use Espo\Tools\Export\Collection;
use Espo\Tools\Export\Processor;
use Espo\Tools\Export\Processor\Params;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

/**
 * CSV export processor with a reporting totals row (metadata-overridden).
 *
 * The Total caption is written in post-process: Espo Entity::set() ignores
 * unknown attributes, so the synthetic marker column cannot carry the label
 * via the entity alone.
 */
class TotalsProcessor extends AbstractTotalsProcessor
{
    public function __construct(
        ReportingProfileRegistry $profileRegistry,
        EntityManager $entityManager,
        Config $config,
        Language $language,
        private LabeledCsvProcessor $csvProcessor,
    ) {
        parent::__construct($profileRegistry, $entityManager, $config, $language);
    }

    protected function getInnerProcessor(): Processor
    {
        return $this->csvProcessor;
    }

    public function process(Params $params, Collection $collection): StreamInterface
    {
        $stream = parent::process($params, $collection);

        if (!$this->isTotalsRequestedForParams($params)) {
            return $stream;
        }

        return $this->injectTotalsCaption($stream);
    }

    private function injectTotalsCaption(StreamInterface $stream): StreamInterface
    {
        $csv = (string) $stream;

        if ($csv === '') {
            return $stream;
        }

        $lines = preg_split("/\r\n|\n|\r/", $csv) ?: [];

        // Drop trailing empty lines for index calc, keep structure for rebuild.
        $lastIdx = null;

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (trim($lines[$i]) !== '') {
                $lastIdx = $i;
                break;
            }
        }

        if ($lastIdx === null) {
            return $stream;
        }

        $caption = $this->getTotalsCaption();
        $line = $lines[$lastIdx];

        // Marker column is first and empty on the totals row before post-process.
        if (str_starts_with($line, ',')) {
            $lines[$lastIdx] = $this->csvEscape($caption) . $line;
        } else if (str_starts_with($line, '"",')) {
            $lines[$lastIdx] = $this->csvEscape($caption) . substr($line, 2);
        } else {
            $lines[$lastIdx] = $this->csvEscape($caption) . ',' . $line;
        }

        return Utils::streamFor(implode("\n", $lines) . (str_ends_with($csv, "\n") ? "\n" : ''));
    }

    private function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
