<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\InjectableFactory;
use Espo\Modules\NonprofitEspocrm\Tools\WebPush\WebPushService;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Web Push metadata and VAPID provisioning (converted from bin/smoke-web-push.php).
 */
class WebPushTest extends SafehouseBaseTestCase
{
    public function testWebPushMetadataAndVapidKeys(): void
    {
        $metadata = $this->getMetadata();

        $this->assertTrue((bool) $metadata->get(['scopes', 'WebPushSubscription', 'entity']));
        $this->assertTrue((bool) $metadata->get(['entityDefs', 'Preferences', 'fields', 'webPushEnabled']));

        $root = dirname(__DIR__, 5);
        $this->assertFileExists($root . '/public/web-push-sw.js');
        $this->assertFileExists(
            $root . '/custom/Espo/Modules/NonprofitEspocrm/libs/web-push/vendor/autoload.php'
        );

        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $service = $factory->create(WebPushService::class);
        $service->ensureVapidKeys();

        $pub = $service->getPublicKey();
        $this->assertIsString($pub);
        $this->assertGreaterThan(20, strlen($pub));
    }
}
