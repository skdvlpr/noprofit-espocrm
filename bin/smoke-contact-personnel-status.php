<?php

declare(strict_types=1);

require __DIR__ . '/lib/refuse-production.php';

/**
 * Smoke: Contact personnelStatus for all types + User delete → Contact Inactive.
 *
 * Usage:
 *   ddev exec php bin/smoke-contact-personnel-status.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\Tools\Layout\LayoutProvider;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
$em = $container->get('entityManager');
$metadata = $container->get('metadata');
/** @var LayoutProvider $layoutProvider */
$layoutProvider = $container->get('injectableFactory')->create(LayoutProvider::class);

$fail = 0;
$ok = 0;

$assert = static function (string $label, bool $cond, string $detail = '') use (&$fail, &$ok): void {
    if ($cond) {
        echo "OK  {$label}" . ($detail !== '' ? " ({$detail})" : '') . PHP_EOL;
        $ok++;
    } else {
        echo "FAIL {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
        $fail++;
    }
};

$assert(
    'personnelStatus not gated by contactType dynamicLogic',
    $metadata->get(['clientDefs', 'Contact', 'dynamicLogic', 'fields', 'personnelStatus']) === null
);

$assert(
    'Contact recordViews.detail custom',
    $metadata->get(['clientDefs', 'Contact', 'recordViews', 'detail'])
        === 'nonprofit-espocrm:views/contact/record/detail'
);

$assert(
    'Contact recordViews.list custom',
    $metadata->get(['clientDefs', 'Contact', 'recordViews', 'list'])
        === 'nonprofit-espocrm:views/contact/record/list'
);

$listJson = json_decode((string) $layoutProvider->get('Contact', 'list'), true);
$listNames = array_map(
    static fn ($c) => is_array($c) ? ($c['name'] ?? null) : null,
    $listJson ?? []
);
$typeIdx = array_search('contactType', $listNames, true);
$statusIdx = array_search('personnelStatus', $listNames, true);
$assert(
    'list.json has contactType then personnelStatus adjacent',
    $typeIdx !== false && $statusIdx !== false && $statusIdx === $typeIdx + 1,
    'type@' . var_export($typeIdx, true) . ' status@' . var_export($statusIdx, true)
);

$listSmall = json_decode((string) $layoutProvider->get('Contact', 'listSmall'), true);
$smallNames = array_map(
    static fn ($c) => is_array($c) ? ($c['name'] ?? null) : null,
    $listSmall ?? []
);
$assert(
    'listSmall has contactType + personnelStatus',
    in_array('contactType', $smallNames, true) && in_array('personnelStatus', $smallNames, true)
);

$created = [];

try {
    $helpSeeker = $em->getNewEntity('Contact');
    $helpSeeker->set([
        'firstName' => 'Smoke',
        'lastName' => 'HelpSeeker',
        'contactType' => 'HelpSeeker',
    ]);
    $em->saveEntity($helpSeeker);
    $created[] = $helpSeeker;
    $assert(
        'HelpSeeker defaults personnelStatus Active',
        $helpSeeker->get('personnelStatus') === 'Active',
        (string) $helpSeeker->get('personnelStatus')
    );

    $colleague = $em->getNewEntity('Contact');
    $colleague->set([
        'firstName' => 'Smoke',
        'lastName' => 'Colleague',
        'contactType' => 'Colleague',
        'personnelStatus' => '',
    ]);
    $em->saveEntity($colleague);
    $created[] = $colleague;
    $assert(
        'Colleague empty status becomes Active',
        $colleague->get('personnelStatus') === 'Active',
        (string) $colleague->get('personnelStatus')
    );

    $volActive = $em->getNewEntity('Contact');
    $volActive->set([
        'firstName' => 'Smoke',
        'lastName' => 'VolActive',
        'contactType' => 'Volunteer',
        'personnelStatus' => null,
        'startDate' => date('Y-m-d', strtotime('-5 days')),
        'endDate' => null,
    ]);
    $em->saveEntity($volActive);
    $created[] = $volActive;
    $assert(
        'Volunteer with valid dates and null status → Active',
        $volActive->get('personnelStatus') === 'Active',
        (string) $volActive->get('personnelStatus')
    );

    $volFuture = $em->getNewEntity('Contact');
    $volFuture->set([
        'firstName' => 'Smoke',
        'lastName' => 'VolFuture',
        'contactType' => 'Volunteer',
        'personnelStatus' => 'Active',
        'startDate' => date('Y-m-d', strtotime('+10 days')),
    ]);
    $em->saveEntity($volFuture);
    $created[] = $volFuture;
    $assert(
        'Volunteer with future startDate → Inactive',
        $volFuture->get('personnelStatus') === 'Inactive',
        (string) $volFuture->get('personnelStatus')
    );

    $memberOk = $em->getNewEntity('Contact');
    $memberOk->set([
        'firstName' => 'Smoke',
        'lastName' => 'MemOk',
        'contactType' => 'MemberContact',
        'personnelStatus' => '',
        'joinDate' => date('Y-m-d', strtotime('-30 days')),
        'leaveDate' => null,
    ]);
    $em->saveEntity($memberOk);
    $created[] = $memberOk;
    $assert(
        'MemberContact with valid joinDate and empty status → Active',
        $memberOk->get('personnelStatus') === 'Active',
        (string) $memberOk->get('personnelStatus')
    );

    $formulaPath = __DIR__ . '/../client/custom/modules/nonprofit-espocrm/src/views/export/modals/export.js';
    $exportJs = is_file($formulaPath) ? (string) file_get_contents($formulaPath) : '';
    $assert(
        'export modal setup calls Dep.prototype.setup',
        str_contains($exportJs, 'Dep.prototype.setup.call(this)')
    );

    $user = $em->getNewEntity('User');
    $user->set([
        'userName' => 'smoke_contact_inactive_' . substr(md5((string) microtime(true)), 0, 8),
        'firstName' => 'Smoke',
        'lastName' => 'UserDel',
        'type' => 'regular',
        'isActive' => true,
    ]);
    $em->saveEntity($user, [SaveOption::SKIP_ALL => true]);
    $created[] = $user;

    $linked = $em->getNewEntity('Contact');
    $linked->set([
        'firstName' => 'Smoke',
        'lastName' => 'Linked',
        'contactType' => 'Volunteer',
        'personnelStatus' => 'Active',
        'linkedUserId' => $user->getId(),
        'startDate' => date('Y-m-d', strtotime('-10 days')),
    ]);
    $em->saveEntity($linked);
    $created[] = $linked;

    $em->removeEntity($user);

    $linkedFresh = $em->getEntityById('Contact', $linked->getId());
    $assert(
        'User delete sets linked Contact Inactive',
        $linkedFresh !== null && $linkedFresh->get('personnelStatus') === 'Inactive',
        $linkedFresh ? (string) $linkedFresh->get('personnelStatus') : 'contact missing'
    );

    $assert(
        'User delete does not delete Contact',
        $linkedFresh !== null && !$linkedFresh->get('deleted')
    );
} catch (Throwable $e) {
    echo 'FAIL exception — ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    $fail++;
} finally {
    foreach (array_reverse($created) as $entity) {
        try {
            if ($entity->getEntityType() === 'User') {
                continue;
            }
            $em->removeEntity($entity);
        } catch (Throwable $e) {
            // best-effort cleanup
        }
    }
}

echo PHP_EOL . "Passed: {$ok}, Failed: {$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
