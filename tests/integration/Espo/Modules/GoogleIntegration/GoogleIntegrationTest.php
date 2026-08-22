<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\GoogleIntegration;

use Espo\Modules\GoogleIntegration\Tools\Installer;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * GoogleCalendarDrive integration provisioning (subset of bin/smoke-google-integration.php).
 */
class GoogleIntegrationTest extends SafehouseBaseTestCase
{
    public function testIntegrationRowAndMetadata(): void
    {
        if (!class_exists(Installer::class)) {
            $this->markTestSkipped('GoogleIntegration module not installed.');
        }

        $metadata = $this->getMetadata();

        $this->assertTrue(
            is_array($metadata->get(['integrations', Installer::INTEGRATION_ID]))
        );

        $em = $this->getEntityManager();
        $integration = $em->getRDBRepository('Integration')
            ->where(['id' => Installer::INTEGRATION_ID])
            ->findOne();

        $this->assertNotNull($integration);

        $legacy = $em->getRDBRepository('Integration')
            ->where(['id' => 'GoogleSafehouse'])
            ->findOne();

        $this->assertNull($legacy);
    }
}
