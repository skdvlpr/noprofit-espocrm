<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\InjectableFactory;
use Espo\Modules\NonprofitEspocrm\Tools\Export\Csv\LabeledCsvProcessor;
use Espo\Modules\NonprofitEspocrm\Tools\Export\Factory as SafehouseExportFactory;
use Espo\Modules\NonprofitEspocrm\Tools\Export\NamedExport;
use Espo\Tools\Export\Collection;
use Espo\Tools\Export\Processor\Params as ProcessorParams;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Export binding + labeled CSV processor (converted from bin/smoke-export-totals.php).
 */
class ExportIntegrationTest extends SafehouseBaseTestCase
{
    public function testSafehouseExportFactoryCreatesNamedExport(): void
    {
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);

        $exportFactory = $factory->create(SafehouseExportFactory::class);
        $export = $exportFactory->create();

        $this->assertInstanceOf(NamedExport::class, $export);
    }

    public function testCsvExportProcessorMetadataOverride(): void
    {
        $expected = 'Espo\\Modules\\NonprofitEspocrm\\Tools\\Export\\Csv\\TotalsProcessor';
        $actual = $this->getMetadata()->get(['app', 'export', 'formatDefs', 'csv', 'processorClassName']);

        $this->assertSame($expected, $actual);
    }

    public function testLabeledCsvProcessorWritesTranslatedHeaders(): void
    {
        $em = $this->getEntityManager();
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $processor = $factory->create(LabeledCsvProcessor::class);

        $meal = $em->getNewEntity('MealCount');
        $meal->set([
            'name' => 'PHPUnit Export',
            'date' => date('Y-m-d'),
            'adults' => 2,
            'minors' => 1,
        ]);
        $em->saveEntity($meal);

        $loaded = $em->getEntityById('MealCount', $meal->getId());
        $collection = $this->createMock(Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$loaded]));

        $params = (new ProcessorParams('export.csv', ['name', 'adults', 'minors'], null))
            ->withEntityType('MealCount');

        $stream = $processor->process($params, $collection);
        $stream->rewind();
        $csv = $stream->getContents();

        $this->assertNotSame('', trim($csv));
        $this->assertStringContainsString('Adults', $csv);
        $this->assertStringContainsString(',2,1', $csv);
        $this->assertDoesNotMatchRegularExpression('/(^|,)(adults|minors)(,|$)/', explode("\n", trim($csv))[0] ?? '');
    }
}
