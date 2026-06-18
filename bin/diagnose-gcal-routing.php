<?php

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

try {
    $app = new Application();
    $app->setupSystemUser();
    $em = $app->getContainer()->getByClass(EntityManager::class);

    $integration = $em->getEntityById('Integration', 'GoogleCalendarDrive');
    echo "enabled=" . var_export($integration?->get('enabled'), true) . PHP_EOL;
    echo "autoCreate=" . var_export($integration?->get('googleCalendarAutoCreateEnabled'), true) . PHP_EOL;

    foreach ($em->getRDBRepository('CalendarDateSource')->find() as $row) {
        if ($row->get('deleted') || !$row->get('isActive')) {
            continue;
        }

        echo sprintf(
            "%s|%s|mode=%s|label=%s\n",
            (string) $row->get('targetEntityType'),
            (string) $row->get('sourceDateType'),
            (string) ($row->get('calendarRoutingMode') ?: 'primary'),
            (string) ($row->get('label') ?: $row->get('name'))
        );
    }

    echo PHP_EOL . "=== Recent GoogleCalendarEventLink ===" . PHP_EOL;
    foreach ($em->getRDBRepository('GoogleCalendarEventLink')->order('createdAt', 'DESC')->limit(0, 8)->find() as $link) {
        echo sprintf(
            "%s %s cal=%s at=%s\n",
            (string) $link->get('sourceEntityType'),
            (string) $link->get('sourceDateType'),
            (string) $link->get('calendarId'),
            (string) $link->get('createdAt')
        );
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
