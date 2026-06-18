<?php

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarProvisioner;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarRoutingMode;

/** @var Application $app */
$app = $GLOBALS['app'] ?? new Application();
$factory = $app->getContainer()->getByClass(InjectableFactory::class);
$provisioner = $factory->create(CalendarProvisioner::class);

$failures = 0;

$ok = static function (string $label, bool $pass, string $detail = '') use (&$failures): void {
    if ($pass) {
        echo "OK  $label\n";

        return;
    }

    $failures++;
    echo "FAIL $label" . ($detail !== '' ? " ($detail)" : '') . "\n";
};

$ok('CalendarRoutingMode normalizes invalid to primary', CalendarRoutingMode::normalize('bogus') === 'primary');
$ok('CalendarRoutingMode accepts user_pick', CalendarRoutingMode::isValid('user_pick'));
$ok('CalendarRoutingMode accepts auto_dedicated', CalendarRoutingMode::isValid('auto_dedicated'));

$ok(
    'Dedicated name uses CRM - {label} default',
    $provisioner->resolveDedicatedCalendarName([
        'label' => 'Calls',
        'name' => 'Call main',
    ]) === 'CRM - Calls'
);

$ok(
    'Dedicated name honors dedicatedCalendarName override',
    $provisioner->resolveDedicatedCalendarName([
        'label' => 'Calls',
        'dedicatedCalendarName' => 'CRM - Custom Calls',
    ]) === 'CRM - Custom Calls'
);

$ok(
    'Dedicated name falls back to name when label empty',
    $provisioner->resolveDedicatedCalendarName([
        'name' => 'Tasks',
        'label' => '',
    ]) === 'CRM - Tasks'
);

exit($failures > 0 ? 1 : 0);
