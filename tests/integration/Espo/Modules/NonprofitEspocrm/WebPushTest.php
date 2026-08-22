<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\InjectableFactory;
use Espo\Modules\NonprofitEspocrm\Tools\WebPush\WebPushPreferenceChecker;
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

    public function testWebPushSubscriptionCrudAndPreferenceChecker(): void
    {
        $em = $this->getEntityManager();
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $service = $factory->create(WebPushService::class);
        $checker = $factory->create(WebPushPreferenceChecker::class);

        $user = $em->getRDBRepository('User')->where(['userName' => 'admin'])->findOne();
        if ($user === null) {
            $user = $this->createUser([
                'userName' => 'phpunit_webpush_' . bin2hex(random_bytes(2)),
                'firstName' => 'PHPUnit',
                'lastName' => 'WebPush',
                'type' => 'admin',
                'isActive' => true,
            ]);
        }

        $preferences = $em->getEntityById('Preferences', $user->getId());
        $this->assertNotNull($preferences);
        $preferences->set([
            'webPushEnabled' => true,
            'assignmentPushNotificationsIgnoreEntityTypeList' => ['Meeting'],
        ]);
        $em->saveEntity($preferences);

        $this->assertTrue($checker->allowsEntity($preferences, 'Task'));
        $this->assertFalse($checker->allowsEntity($preferences, 'Meeting'));

        $endpoint = 'https://example.com/push/phpunit-' . bin2hex(random_bytes(4));
        $service->saveSubscription($user->getId(), [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'BSmokeTestP256dhKeyValueThatIsLongEnough123456',
                'auth' => 'smokeAuthKeyValue12',
            ],
        ], 'phpunit-agent');

        $row = $em->getRDBRepository('WebPushSubscription')->where([
            'userId' => $user->getId(),
            'endpoint' => $endpoint,
        ])->findOne();

        $this->assertNotNull($row);
        $this->assertSame(1, $service->deleteSubscription($user->getId(), $endpoint));
    }
}
