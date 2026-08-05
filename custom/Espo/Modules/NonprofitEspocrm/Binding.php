<?php

namespace Espo\Modules\NonprofitEspocrm;

use Espo\Core\Binding\Binder;
use Espo\Core\Binding\BindingProcessor;
use Espo\Core\InjectableFactory;
use Espo\Modules\NonprofitEspocrm\Tools\Export\Factory as SafehouseExportFactory;
use Espo\Tools\Export\Factory as CoreExportFactory;

class Binding implements BindingProcessor
{
    public function process(Binder $binder): void
    {
        // Constructor injection of Espo\Tools\Export\Factory (Service, email exporter)
        // resolves to our factory that builds NamedExport with dated filenames.
        $binder->bindCallback(
            CoreExportFactory::class,
            static function (InjectableFactory $injectableFactory): CoreExportFactory {
                /** @var SafehouseExportFactory $factory */
                $factory = $injectableFactory->create(SafehouseExportFactory::class);

                return $factory;
            }
        );
    }
}
