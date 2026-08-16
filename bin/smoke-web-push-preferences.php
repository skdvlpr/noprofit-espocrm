<?php

require __DIR__ . '/lib/refuse-production.php';

/**
 * Smoke: Preferences Web Push parity with In-App assignment entity checklist.
 *
 * Usage:
 *   ddev exec php bin/smoke-web-push-preferences.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Modules\NonprofitEspocrm\Tools\WebPush\WebPushPreferenceChecker;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
$metadata = $container->get('metadata');

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    echo ($pass ? '  [PASS] ' : '  [FAIL] ') . $name . ($detail !== '' ? " — $detail" : '') . "\n";
};

echo "Web Push preferences parity\n";

$field = $metadata->get(['entityDefs', 'Preferences', 'fields', 'assignmentPushNotificationsIgnoreEntityTypeList']);
$ok(
    'Preferences.assignmentPushNotificationsIgnoreEntityTypeList defined',
    is_array($field) && ($field['type'] ?? '') === 'checklist'
);
$ok(
    'field uses nonprofit-espocrm checklist view',
    ($field['view'] ?? '') ===
        'nonprofit-espocrm:views/preferences/fields/assignment-push-notifications-ignore-entity-type-list'
);

$layoutPath = __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Preferences/detail.json';
$layout = (string) file_get_contents($layoutPath);
$ok(
    'Preferences detail layout includes push ignore checklist',
    str_contains($layout, 'assignmentPushNotificationsIgnoreEntityTypeList')
);
$ok(
    'Push and In-app checklists share one layout row (two columns)',
    (bool) preg_match(
        '/assignmentPushNotificationsIgnoreEntityTypeList[\s\S]{0,120}assignmentNotificationsIgnoreEntityTypeList/',
        $layout
    )
);

$en = json_decode(
    (string) file_get_contents(
        __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/en_US/Preferences.json'
    ),
    true
);
$it = json_decode(
    (string) file_get_contents(
        __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/Preferences.json'
    ),
    true
);
$ok(
    'EN push label has no come/in-app jargon',
    ($en['fields']['assignmentPushNotificationsIgnoreEntityTypeList'] ?? '') === 'Push assignment notifications'
        && !str_contains(strtolower(json_encode($en)), 'come in-app')
        && !str_contains(strtolower((string) ($en['fields']['assignmentPushNotificationsIgnoreEntityTypeList'] ?? '')), 'in-app style')
);
$ok(
    'IT push label mirrors in-app wording (no come in-app)',
    ($it['fields']['assignmentPushNotificationsIgnoreEntityTypeList'] ?? '') === 'Notifiche di assegnazione push'
        && !str_contains(strtolower((string) ($it['fields']['assignmentPushNotificationsIgnoreEntityTypeList'] ?? '')), 'come')
);

$ok(
    'SafehouseDefaults includes Task for assignment notifications',
    in_array('Task', \Espo\Modules\NonprofitEspocrm\Tools\SafehouseDefaults::ASSIGNMENT_NOTIFICATION_ENTITY_TYPES, true)
);

$viewPath = __DIR__
    . '/../client/custom/modules/nonprofit-espocrm/src/views/preferences/fields/'
    . 'assignment-push-notifications-ignore-entity-type-list.js';
$ok('frontend checklist view exists', is_file($viewPath));

$checker = $container->getByClass(\Espo\Core\InjectableFactory::class)
    ->create(WebPushPreferenceChecker::class);

$prefs = $container->get('entityManager')->getNewEntity('Preferences');
$prefs->set('webPushEnabled', false);
$ok('disabled master switch blocks all', !$checker->allowsEntity($prefs, 'Task'));

$prefs->set('webPushEnabled', true);
$prefs->set('assignmentPushNotificationsIgnoreEntityTypeList', []);
$ok('enabled + empty ignore allows Task', $checker->allowsEntity($prefs, 'Task'));
$ok('enabled + empty ignore allows null entity', $checker->allowsEntity($prefs, null));

$prefs->set('assignmentPushNotificationsIgnoreEntityTypeList', ['Task', 'Meeting']);
$ok('ignore list blocks Task', !$checker->allowsEntity($prefs, 'Task'));
$ok('ignore list still allows Call', $checker->allowsEntity($prefs, 'Call'));

if ($fail > 0) {
    echo "\n=== FAILURES: $fail ===\n";
    exit(1);
}

echo "\n=== ALL PASS ===\n";
