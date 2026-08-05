<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Export;

use Espo\Core\Acl;
use Espo\Core\FieldProcessing\ListLoadProcessor;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\Record\Select\ApplierClassNameListProvider;
use Espo\Core\Record\ServiceContainer;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Utils\FieldUtil;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\Tools\Export\AdditionalFieldsLoaderFactory;
use Espo\Tools\Export\Export;
use Espo\Tools\Export\Params;
use Espo\Tools\Export\ProcessorFactory;
use Espo\Tools\Export\ProcessorParamsHandlerFactory;

/**
 * Injects Safehouse-dated export filenames when none were provided.
 */
class NamedExport extends Export
{
    public function __construct(
        private ExportFileNamer $fileNamer,
        ProcessorFactory $processorFactory,
        ProcessorParamsHandlerFactory $processorParamsHandlerFactory,
        AdditionalFieldsLoaderFactory $additionalFieldsLoaderFactory,
        SelectBuilderFactory $selectBuilderFactory,
        ServiceContainer $serviceContainer,
        Acl $acl,
        EntityManager $entityManager,
        Metadata $metadata,
        FileStorageManager $fileStorageManager,
        ListLoadProcessor $listLoadProcessor,
        FieldUtil $fieldUtil,
        User $user,
        ApplierClassNameListProvider $applierClassNameListProvider,
    ) {
        parent::__construct(
            $processorFactory,
            $processorParamsHandlerFactory,
            $additionalFieldsLoaderFactory,
            $selectBuilderFactory,
            $serviceContainer,
            $acl,
            $entityManager,
            $metadata,
            $fileStorageManager,
            $listLoadProcessor,
            $fieldUtil,
            $user,
            $applierClassNameListProvider,
        );
    }

    public function setParams(Params $params): self
    {
        $fileName = $params->getFileName();

        if ($fileName === null || trim($fileName) === '') {
            $params = $params->withFileName(
                $this->fileNamer->buildBaseName($params->getEntityType())
            );
        }

        return parent::setParams($params);
    }
}
