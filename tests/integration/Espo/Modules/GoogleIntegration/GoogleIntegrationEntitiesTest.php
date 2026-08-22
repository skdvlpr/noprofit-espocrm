<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\GoogleIntegration;

use Espo\Modules\GoogleIntegration\Tools\Installer;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * GoogleIntegration custom entity scopes and ORM factories.
 */
class GoogleIntegrationEntitiesTest extends SafehouseBaseTestCase
{
    public function testCustomEntityScopesSupportGetNewEntity(): void
    {
        if (!class_exists(Installer::class)) {
            $this->markTestSkipped('GoogleIntegration module not installed.');
        }

        $em = $this->getEntityManager();

        foreach (['CalendarDateSource', 'CalendarTemplate', 'GoogleCalendarOverlayEvent'] as $entityType) {
            $entity = $em->getNewEntity($entityType);
            $this->assertSame($entityType, $entity->getEntityType());
        }
    }
}
