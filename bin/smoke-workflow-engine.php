<?php

declare(strict_types=1);

require __DIR__ . '/lib/refuse-production.php';

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Modules\WorkflowEngine\Tools\Installer;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

(new Installer())->runPostInstall($container);

/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);
$metadata->init(true);
/** @var Config $config */
$config = $container->getByClass(Config::class);
$config->update();

$failures = 0;
$check = static function (string $name, bool $pass, string $detail = '') use (&$failures): void {
    if (!$pass) {
        $failures++;
    }

    echo sprintf(
        "[%s] %s%s\n",
        $pass ? 'PASS' : 'FAIL',
        $name,
        $detail === '' ? '' : " — {$detail}"
    );
};

$fields = $metadata->get(['entityDefs', 'WorkflowDefinition', 'fields']) ?? [];
$scope = $metadata->get(['scopes', 'WorkflowDefinition']) ?? [];
$acl = $metadata->get(['aclDefs', 'WorkflowDefinition']) ?? [];

$check('WorkflowDefinition scope is registered', ($scope['entity'] ?? false) === true);
$check('WorkflowDefinition belongs to WorkflowEngine', ($scope['module'] ?? null) === 'WorkflowEngine');
$check('WorkflowDefinition has no general navigation tab', ($scope['tab'] ?? true) === false);
$check('WorkflowDefinition default access is denied', ($acl['read'] ?? null) === 'no');

foreach (['name', 'isActive', 'targetEntityType', 'triggerType', 'description'] as $field) {
    $check("WorkflowDefinition field {$field} is registered", isset($fields[$field]));
}

$check(
    'WorkflowDefinition trigger options are W1-only',
    ($fields['triggerType']['options'] ?? null) === ['afterCreate', 'afterUpdate']
);
$check(
    'WorkflowDefinition list layout has a linked name',
    is_file(__DIR__ . '/../custom/Espo/Modules/WorkflowEngine/Resources/layouts/WorkflowDefinition/list.json')
    && str_contains(
        (string) file_get_contents(
            __DIR__ . '/../custom/Espo/Modules/WorkflowEngine/Resources/layouts/WorkflowDefinition/list.json'
        ),
        '"link": true'
    )
);
$check(
    'Inert late-after-save hook is present',
    class_exists(\Espo\Modules\WorkflowEngine\Hooks\Common\WorkflowTrigger::class)
);
$check(
    'Inert runner is present',
    class_exists(\Espo\Modules\WorkflowEngine\Services\WorkflowRunner::class)
);

$manifestPath = __DIR__ . '/../custom/Espo/Modules/WorkflowEngine/manifest.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$zipPath = __DIR__ . '/../dist/workflow-engine-v' . $manifest['version'] . '.zip';
$check('WorkflowEngine package exists', is_file($zipPath), $zipPath);

if (is_file($zipPath) && class_exists(ZipArchive::class)) {
    $zip = new ZipArchive();
    $opened = $zip->open($zipPath) === true;
    $check('WorkflowEngine package opens', $opened);

    if ($opened) {
        foreach ([
            'manifest.json',
            'scripts/AfterInstall.php',
            'files/custom/Espo/Modules/WorkflowEngine/manifest.json',
            'files/client/custom/modules/workflow-engine/lib/init.js',
        ] as $entry) {
            $check("WorkflowEngine package contains {$entry}", $zip->locateName($entry) !== false);
        }

        $zip->close();
    }
}

$apiKey = getenv('WORKFLOW_ENGINE_API_KEY');
$siteUrl = rtrim((string) $config->get('siteUrl'), '/');

if (is_string($apiKey) && $apiKey !== '' && $siteUrl !== '') {
    $request = curl_init($siteUrl . '/api/v1/Metadata?key=scopes.WorkflowDefinition');
    curl_setopt_array($request, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-Api-Key: ' . $apiKey, 'Accept: application/json'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($request);
    $status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    curl_close($request);

    $check('REST metadata probe returns 200', $status === 200, 'code=' . $status);
    $response = is_string($body) ? json_decode($body, true) : null;
    $check('REST metadata confirms WorkflowDefinition scope', ($response['entity'] ?? false) === true);
} else {
    echo "[SKIP] REST metadata probe — set WORKFLOW_ENGINE_API_KEY to enable it.\n";
}

if ($failures > 0) {
    exit(1);
}

echo "WorkflowEngine W1 smoke passed.\n";
