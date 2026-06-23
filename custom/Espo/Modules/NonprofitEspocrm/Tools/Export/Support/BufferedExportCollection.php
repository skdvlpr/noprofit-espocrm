<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Export\Support;

use Espo\ORM\Entity;
use Espo\Tools\Export\Collection as ExportCollection;
use Traversable;

/**
 * Re-iterable export collection used to inject a synthetic totals row.
 *
 * The core export processors (CSV, XLSX/PhpSpreadsheet, XLSX/OpenSpout) only
 * `foreach` over an {@see ExportCollection}. The base collection is single-pass
 * (streamed `sth()`), so to both compute totals and feed the processor we buffer
 * the already-prepared entities and yield them again, followed by the totals
 * entity. Extends the concrete core class to satisfy processor type hints; the
 * parent constructor is intentionally not called (its private state is unused).
 *
 * @extends ExportCollection
 */
class BufferedExportCollection extends ExportCollection
{
    /**
     * @param Entity[] $entities Already prepared (list-load processed) entities.
     */
    public function __construct(
        private array $entities,
        private ?Entity $totalsEntity,
    ) {}

    public function getIterator(): Traversable
    {
        foreach ($this->entities as $entity) {
            yield $entity;
        }

        if ($this->totalsEntity !== null) {
            yield $this->totalsEntity;
        }
    }
}
