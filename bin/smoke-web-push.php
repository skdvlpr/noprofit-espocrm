<?php

require __DIR__ . '/lib/refuse-production.php';

/**
 * Smoke: Web Push VAPID + entity + preference field + subscription CRUD.
 *
 * Usage: ddev exec php bin/smoke-web-push.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Metadata;
use Espo\Modules\NonprofitEspocrm\Tools\WebPush\WebPushService;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$metadata = $container->getByClass(Metadata::class);
$factory = $container->getByClass(InjectableFactory::class);

$fail = 0;

function ok(bool $cond, string $label): void
{
    global $fail;
    echo ($cond ? 'OK  ' : 'FAIL') . ' ' . $label . "\n";

    if (!$cond) {
        $fail++;
    }
}

ok((bool) $metadata->get(['scopes', 'WebPushSubscription', 'entity']), 'WebPushSubscription scope entity=true');
ok((bool) $metadata->get(['entityDefs', 'Preferences', 'fields', 'webPushEnabled']), 'Preferences.webPushEnabled exists');
ok(
    ($metadata->get(['entityDefs', 'Preferences', 'fields', 'webPushEnabled', 'view']) ?? '') ===
        'nonprofit-espocrm:views/preferences/fields/web-push-enabled',
    'webPushEnabled custom field view'
);
ok(is_file(__DIR__ . '/../public/web-push-sw.js'), 'public/web-push-sw.js exists');
ok(is_file(__DIR__ . '/../client/custom/modules/nonprofit-espocrm/lib/web-push.js'), 'web-push.js client helper');
ok(is_file(__DIR__ . '/../client/custom/modules/nonprofit-espocrm/manifest.webmanifest'), 'PWA manifest');
ok(
    is_file(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/libs/web-push/vendor/autoload.php'),
    'minishlink web-push vendor autoload'
);

$service = $factory->create(WebPushService::class);
$service->ensureVapidKeys();
$pub = $service->getPublicKey();
ok(is_string($pub) && strlen($pub) > 20, 'VAPID public key provisioned');

$admin = $em->getRDBRepository('User')->where(['userName' => 'admin'])->findOne()
    ?? $em->getRDBRepository('User')->where(['type' => 'admin'])->findOne();

ok($admin !== null, 'admin user exists for subscription smoke');

if ($admin) {
    $fakeEndpoint = 'https://example.com/push/smoke-' . bin2hex(random_bytes(8));
    $service->saveSubscription($admin->getId(), [
        'endpoint' => $fakeEndpoint,
        'keys' => [
            'p256dh' => 'BSmokeTestP256dhKeyValueThatIsLongEnough123456',
            'auth' => 'smokeAuthKeyValue12',
        ],
    ], 'smoke-agent');

    $row = $em->getRDBRepository('WebPushSubscription')->where([
        'userId' => $admin->getId(),
        'endpoint' => $fakeEndpoint,
    ])->findOne();

    ok($row !== null, 'subscription row created');

    $removed = $service->deleteSubscription($admin->getId(), $fakeEndpoint);
    ok($removed === 1, 'subscription deleted');
}

$scriptList = $metadata->get(['app', 'client', 'scriptList']) ?? [];
$joined = json_encode($scriptList);
ok(str_contains((string) $joined, 'web-push.js'), 'web-push.js in app.client.scriptList');

$reminderTypes = $metadata->get(['entityDefs', 'Reminder', 'fields', 'type', 'options']) ?? [];
ok(in_array('Push', $reminderTypes, true), 'Reminder.type options include Push');
ok(
    class_exists(\Espo\Modules\NonprofitEspocrm\Tools\Reminder\Sender\PushReminder::class)
        && class_exists(\Espo\Modules\NonprofitEspocrm\Jobs\SubmitPushReminders::class),
    'PushReminder sender + SubmitPushReminders job'
);
ok(
    ($metadata->get(['app', 'scheduledJobs', 'SafehouseCrmSubmitPushReminders', 'jobClassName']) ?? '') !== '',
    'SubmitPushReminders scheduled job registered'
);
ok(
    is_file(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/templates/reminderPush/en_US/subject.tpl')
        && is_file(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/templates/reminderPush/it_IT/Call/body.tpl'),
    'reminderPush templates (default + Call IT)'
);
$prefsLayout = (string) file_get_contents(
    __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Preferences/detail.json'
);
ok(str_contains($prefsLayout, 'webPushEnabled'), 'Preferences layout includes webPushEnabled');
$overviewPos = strpos($prefsLayout, '"name": "overview"');
$firstPushPos = strpos($prefsLayout, 'webPushEnabled');
ok(
    $overviewPos !== false && $firstPushPos !== false && $firstPushPos > $overviewPos
        && $firstPushPos < ($overviewPos + 400),
    'webPushEnabled is on Preferences overview (first panel)'
);

echo $fail === 0 ? "ALL OK\n" : "FAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
