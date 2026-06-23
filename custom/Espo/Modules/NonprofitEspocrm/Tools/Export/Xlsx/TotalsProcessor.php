<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Export\Xlsx;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Modules\NonprofitEspocrm\Tools\Export\Support\AbstractTotalsProcessor;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\ReportingProfileRegistry;
use Espo\ORM\EntityManager;
use Espo\Tools\Export\Format\Xlsx\Processor as CoreXlsxProcessor;
use Espo\Tools\Export\Processor;

/**
 * XLSX export processor with a reporting totals row (metadata-overridden).
 *
 * The synthetic totals entity is rendered by the core XLSX processor, so the
 * totals cells inherit native integer/currency number formatting.
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
}
