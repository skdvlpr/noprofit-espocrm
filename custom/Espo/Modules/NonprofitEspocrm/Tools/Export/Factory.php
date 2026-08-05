<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Export;

use Espo\Core\Acl;
use Espo\Core\AclManager;
use Espo\Core\Binding\BindingContainerBuilder;
use Espo\Core\InjectableFactory;
use Espo\Entities\User;
use Espo\Tools\Export\Export;
use Espo\Tools\Export\Factory as CoreFactory;

/**
 * Creates {@see NamedExport} so default attachment names are dated.
 */
class Factory extends CoreFactory
{
    public function __construct(
        private InjectableFactory $injectableFactory,
        private AclManager $aclManager,
    ) {
        parent::__construct($injectableFactory, $aclManager);
    }

    public function create(): Export
    {
        /** @var NamedExport $export */
        $export = $this->injectableFactory->create(NamedExport::class);

        return $export;
    }

    public function createForUser(User $user): Export
    {
        $bindingContainer = BindingContainerBuilder::create()
            ->bindInstance(User::class, $user)
            ->bindInstance(Acl::class, $this->aclManager->createUserAcl($user))
            ->build();

        /** @var NamedExport $export */
        $export = $this->injectableFactory->createWithBinding(NamedExport::class, $bindingContainer);

        return $export;
    }
}
