<?php

namespace Espo\Modules\SafehouseCrm\Tools\Export\Csv;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Modules\SafehouseCrm\Tools\Export\Support\AbstractTotalsProcessor;
use Espo\Modules\SafehouseCrm\Tools\Reporting\ReportingProfileRegistry;
use Espo\ORM\EntityManager;
use Espo\Tools\Export\Format\Csv\Processor as CoreCsvProcessor;
use Espo\Tools\Export\Processor;

/**
 * CSV export processor with a reporting totals row (metadata-overridden).
 */
class TotalsProcessor extends AbstractTotalsProcessor
{
    public function __construct(
        ReportingProfileRegistry $profileRegistry,
        EntityManager $entityManager,
        Config $config,
        Language $language,
        private CoreCsvProcessor $coreProcessor,
    ) {
        parent::__construct($profileRegistry, $entityManager, $config, $language);
    }

    protected function getInnerProcessor(): Processor
    {
        return $this->coreProcessor;
    }
}
