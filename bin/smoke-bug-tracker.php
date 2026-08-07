<?php

declare(strict_types=1);

/**
 * Smoke: BugTracker — metadata, admin settings, auto-name, ACL, screenshot cleanup.
 *
 * Usage: ddev exec php bin/smoke-bug-tracker.php
 */

require __DIR__ . '/lib/refuse-production.php';

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\InjectableFactory;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Attachment;
use Espo\Entities\EmailTemplate;
use Espo\Entities\Role;
use Espo\Entities\User;
use Espo\Modules\BugTracker\Tools\Installer;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

(new Installer())->runPostInstall($container);

/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);
$metadata->init(true);

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);

/** @var Config $config */
$config = $container->getByClass(Config::class);

/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);

/** @var FileStorageManager $fileStorage */
$fileStorage = $injectableFactory->create(FileStorageManager::class);

$failures = 0;
$check = static function (string $name, bool $pass, string $detail = '') use (&$failures): void {
    if (!$pass) {
        $failures++;
    }

    echo sprintf("[%s] %s%s\n", $pass ? 'PASS' : 'FAIL', $name, $detail === '' ? '' : " — {$detail}");
};

$scope = $metadata->get(['scopes', 'BugReport']) ?? [];
$fields = $metadata->get(['entityDefs', 'BugReport', 'fields']) ?? [];
$settingsFields = $metadata->get(['entityDefs', 'Settings', 'fields']) ?? [];
$adminPanel = $metadata->get(['app', 'adminPanel', 'misc', 'itemList']) ?? [];
$client = $metadata->get(['app', 'client']) ?? [];
$scriptList = $client['scriptList'] ?? [];

$check('scope entity=true', ($scope['entity'] ?? false) === true);
$check('module BugTracker', ($scope['module'] ?? null) === 'BugTracker');
$check('name readOnly', ($fields['name']['readOnly'] ?? false) === true);
$check('settings bugTrackerEnabled', isset($settingsFields['bugTrackerEnabled']));
$check('settings technician email', isset($settingsFields['bugTrackerTechnicianEmail']));
$check('settings notify template', isset($settingsFields['bugTrackerNotifyEmailTemplate']));
$check('settings closed template', isset($settingsFields['bugTrackerClosedEmailTemplate']));
$check('admin view JS', is_file(__DIR__ . '/../client/custom/modules/bug-tracker/src/views/admin/bug-tracker.js'));
$check(
    'init.js in scriptList',
    in_array('client/custom/modules/bug-tracker/lib/init.js', $scriptList, true)
);

$adminHasBugTracker = false;
foreach ($adminPanel as $item) {
    if (is_array($item) && ($item['url'] ?? null) === '#Admin/bugTracker') {
        $adminHasBugTracker = true;
    }
    if (is_object($item) && ($item->url ?? null) === '#Admin/bugTracker') {
        $adminHasBugTracker = true;
    }
}
$check('adminPanel Bug Tracker item', $adminHasBugTracker);

$check('bugTrackerEnabled default true', $config->get('bugTrackerEnabled') !== false);
$check(
    'notify template id set',
    is_string($config->get('bugTrackerNotifyEmailTemplateId')) &&
        $config->get('bugTrackerNotifyEmailTemplateId') !== ''
);
$check(
    'closed template id set',
    is_string($config->get('bugTrackerClosedEmailTemplateId')) &&
        $config->get('bugTrackerClosedEmailTemplateId') !== ''
);

$newTpl = $em->getRDBRepositoryByClass(EmailTemplate::class)
    ->where(['name' => 'BugTracker — New report'])
    ->findOne();
$closedTpl = $em->getRDBRepositoryByClass(EmailTemplate::class)
    ->where(['name' => 'BugTracker — Closed confirmation'])
    ->findOne();
$check('seeded new-report template', $newTpl !== null);
$check('seeded closed template', $closedTpl !== null);

$roles = $em->getRDBRepositoryByClass(Role::class)->find();
$roleWithAcl = 0;
foreach ($roles as $role) {
    $data = $role->get('data');
    if (is_object($data) && isset($data->BugReport)) {
        $roleWithAcl++;
    } elseif (is_array($data) && isset($data['BugReport'])) {
        $roleWithAcl++;
    }
}
$check('roles have BugReport ACL', $roleWithAcl > 0, "count={$roleWithAcl}");

$user = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['isActive' => true, 'type' => ['admin', 'regular', 'api']])
    ->limit(0, 1)
    ->findOne();
$check('active user available', $user !== null);

$bug = null;
$attachmentId = null;

try {
    $attachment = $em->getRDBRepositoryByClass(Attachment::class)->getNew();
    $attachment->set([
        'name' => 'bug-tracker-smoke.png',
        'type' => 'image/png',
        'role' => Attachment::ROLE_ATTACHMENT,
        'field' => 'screenshots',
        'relatedType' => 'BugReport',
        'contents' => base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ),
    ]);
    $em->saveEntity($attachment);
    $attachmentId = $attachment->getId();
    $check('attachment created', is_string($attachmentId) && $attachmentId !== '');
    $check('attachment file on disk', $fileStorage->exists($attachment));

    $bug = $em->getNewEntity('BugReport');
    $bug->set([
        'description' => 'Automated smoke — safe to close.',
        'status' => 'New',
        'pageUrl' => 'https://example.test/smoke-bug-tracker',
        'pageTitle' => 'Smoke page',
        'screenshotsIds' => [$attachmentId],
        'assignedUserId' => $user ? $user->getId() : null,
    ]);
    $em->saveEntity($bug);
    $bugId = $bug->getId();
    $check('BugReport created', is_string($bugId) && $bugId !== '');

    $name = (string) $bug->get('name');
    $check(
        'auto-name format',
        (bool) preg_match('/^\d{8}-\d{4}-BugReport-[a-f0-9]{8}$/', $name),
        $name
    );

    $attachment = $em->getEntityById(Attachment::ENTITY_TYPE, $attachmentId);
    if ($attachment) {
        $attachment->set([
            'parentType' => 'BugReport',
            'parentId' => $bugId,
            'field' => 'screenshots',
        ]);
        $em->saveEntity($attachment, ['silent' => true]);
    }

    $bug = $em->getEntityById('BugReport', $bugId);
    $bug->set('status', 'Closed');
    $em->saveEntity($bug);

    $bug = $em->getEntityById('BugReport', $bugId);
    $idsAfter = $bug ? ($bug->getLinkMultipleIdList('screenshots') ?: []) : ['missing'];
    $check('screenshots cleared after close', $idsAfter === [], json_encode($idsAfter) ?: '');
    $check(
        'screenshotsClearedAt set',
        $bug && $bug->get('screenshotsClearedAt') !== null && $bug->get('screenshotsClearedAt') !== ''
    );
    $check(
        'attachment entity removed',
        $em->getEntityById(Attachment::ENTITY_TYPE, $attachmentId) === null
    );
} catch (Throwable $e) {
    $check('lifecycle', false, $e->getMessage());
} finally {
    if ($bug && $bug->getId()) {
        try {
            $em->removeEntity($bug);
        } catch (Throwable $e) {
        }
    }
    if ($attachmentId) {
        $leftover = $em->getEntityById(Attachment::ENTITY_TYPE, $attachmentId);
        if ($leftover) {
            try {
                $em->removeEntity($leftover);
            } catch (Throwable $e) {
            }
        }
    }
}

// Disabled create guard
$configWriter = $injectableFactory->create(\Espo\Core\Utils\Config\ConfigWriter::class);
$configWriter->set('bugTrackerEnabled', false);
$configWriter->save();
$config->update();

$blocked = false;
try {
    $denied = $em->getNewEntity('BugReport');
    $denied->set([
        'description' => 'should fail',
        'status' => 'New',
        'pageUrl' => 'https://example.test/disabled',
    ]);
    $em->saveEntity($denied);
    if ($denied->getId()) {
        $em->removeEntity($denied);
    }
} catch (Throwable $e) {
    $blocked = true;
}
$check('create blocked when disabled', $blocked);

$configWriter->set('bugTrackerEnabled', true);
$configWriter->save();
$config->update();

echo $failures === 0 ? "\nOK bug-tracker smoke\n" : "\nFAILED bug-tracker smoke ({$failures})\n";
exit($failures === 0 ? 0 : 1);
