<?php

declare(strict_types=1);

namespace tests\integration\Espo\Core;

use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Prove custom modules still boot and resolve on whatever Espo core is installed.
 *
 * Not a version-string check: after a core bump (10.x → 11.x → …) this suite must
 * still pass. If core APIs/metadata/ORM break custom code, these assertions fail.
 */
class CoreCompatibilityTest extends SafehouseBaseTestCase
{
    private const EXPECTED_MODULES = [
        'NonprofitEspocrm',
        'GoogleIntegration',
        'WorkflowEngine',
        'SafehouseAuroraThemes',
        'BugTracker',
    ];

    /** Custom entity types that must exist in metadata after Installer + rebuild. */
    private const CUSTOM_SCOPES = [
        'PrimaNota',
        'FoodParcelRegistration',
        'ActivityOffer',
        'ActivityInvite',
        'BugReport',
        'WorkflowDefinition',
        'CalendarDateSource',
        'CalendarTemplate',
        'MealCount',
        'WebPushSubscription',
    ];

    public function testApplicationBootstrapsOnTestInstance(): void
    {
        $this->assertNotNull($this->getContainer());
        $this->assertSame('db_test', $this->getConfig()->get('database.dbname'));
    }

    public function testCustomModulesAreLoadableOnCurrentCore(): void
    {
        foreach (self::EXPECTED_MODULES as $module) {
            $this->assertTrue(
                class_exists("Espo\\Modules\\{$module}\\AfterInstall")
                    || class_exists("Espo\\Modules\\{$module}\\Tools\\Installer"),
                "Module {$module} must load on current Espo core"
            );
        }
    }

    public function testCustomScopesExistInMetadataOnCurrentCore(): void
    {
        $meta = $this->getMetadata();

        foreach (self::CUSTOM_SCOPES as $scope) {
            $this->assertTrue(
                (bool) $meta->get(['scopes', $scope, 'entity']),
                "Scope {$scope} must be registered (core metadata merge still works)"
            );
        }
    }

    public function testCustomEntitiesCanBeInstantiatedViaOrm(): void
    {
        $em = $this->getEntityManager();

        foreach ([
            'PrimaNota',
            'FoodParcelRegistration',
            'ActivityOffer',
            'BugReport',
            'WorkflowDefinition',
            'MealCount',
        ] as $type) {
            $entity = $em->getNewEntity($type);
            $this->assertSame($type, $entity->getEntityType());
        }
    }

    public function testCoreServicesStillResolveForCustomHooks(): void
    {
        $container = $this->getContainer();

        $this->assertNotNull($container->getByClass(\Espo\Core\ORM\EntityManager::class));
        $this->assertNotNull($container->getByClass(\Espo\Core\Utils\Metadata::class));
        $this->assertNotNull($container->getByClass(\Espo\Core\InjectableFactory::class));
        $this->assertNotNull($container->getByClass(\Espo\Core\Utils\Config::class));
    }
}
