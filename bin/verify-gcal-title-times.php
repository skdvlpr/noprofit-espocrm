<?php

/**
 * Verify Google event titles use CalendarDateSource.label as suffix for all entity types.
 *
 * Usage: ddev exec php bin/verify-gcal-title-times.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\InjectableFactory;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarEventTitle;
use Espo\ORM\EntityManager;

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$factory = $container->getByClass(InjectableFactory::class);

$admin = $em->getRDBRepository('User')->where(['userName' => 'admin', 'deleted' => false])->findOne();

if ($admin === null) {
    fwrite(STDERR, "FAIL: admin user not found\n");
    exit(1);
}

$container->getByClass(ApplicationUser::class)->setUser($admin);

$pusher = $factory->create(EventPusher::class);
$provider = $factory->create(DateSourceProvider::class);
$buildSummary = new ReflectionMethod($pusher, 'buildSummary');
$buildSummary->setAccessible(true);

$fail = 0;

foreach ($provider->getActiveSourcesForEntityType('Meeting') as $source) {
    if (($source['sourceDateType'] ?? '') !== 'main') {
        continue;
    }

    $entity = $em->getNewEntity('Meeting');
    $entity->set('name', 'Smoke title check');

    $title = $buildSummary->invoke($pusher, $entity, 'main', $source);
    $expected = GoogleCalendarEventTitle::format('Smoke title check', (string) $source['label']);
    $ok = $title === $expected;

    echo ($ok ? '[PASS]' : '[FAIL]') . " Meeting title=\"{$title}\" expected=\"{$expected}\"\n";

    if (!$ok) {
        $fail++;
    }
}

foreach ($provider->getActiveSourcesForEntityType('Call') as $source) {
    if (($source['sourceDateType'] ?? '') !== 'main') {
        continue;
    }

    $entity = $em->getNewEntity('Call');
    $entity->set('name', 'Smoke title check');

    $title = $buildSummary->invoke($pusher, $entity, 'main', $source);
    $expected = GoogleCalendarEventTitle::format('Smoke title check', (string) $source['label']);
    $ok = $title === $expected;

    echo ($ok ? '[PASS]' : '[FAIL]') . " Call title=\"{$title}\" expected=\"{$expected}\"\n";

    if (!$ok) {
        $fail++;
    }
}

echo "\nAll active CalendarDateSource rows:\n";

foreach ($em->getRDBRepository('CalendarDateSource')->where(['deleted' => false, 'isActive' => true])->find() as $row) {
    $key = $row->get('targetEntityType') . ':' . ($row->get('sourceDateType') ?: 'main');
    $label = trim((string) $row->get('label'));
    $entity = $em->getNewEntity((string) $row->get('targetEntityType'));
    $entity->set('name', 'Title probe');

    $title = $buildSummary->invoke(
        $pusher,
        $entity,
        (string) ($row->get('sourceDateType') ?: 'main'),
        [
            'sourceDateType' => $row->get('sourceDateType') ?: 'main',
            'label' => $label,
        ]
    );
    $expected = GoogleCalendarEventTitle::format('Title probe', $label);
    $ok = $title === $expected && $label !== '';

    echo ($ok ? '[PASS]' : '[FAIL]') . " {$key} label=\"{$label}\" title=\"{$title}\"\n";

    if (!$ok) {
        $fail++;
    }
}

exit($fail === 0 ? 0 : 1);
