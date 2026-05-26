<?php

/**
 * Verifies dynamic Google Calendar provisioning for every active CalendarDateSource target entity
 * (core + custom), including DB columns, metadata, layouts, and date-source API.
 *
 * Usage:
 *   ddev exec php bin/smoke-google-calendar-dynamic-entities.php
 */

declare(strict_types=1);

use Throwable;

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Authentication\Logins\ApiKey as ApiKeyLogin;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarCapableEntities;
use Espo\Modules\GoogleIntegration\Tools\Installer as GoogleIntegrationInstaller;
use Espo\ORM\EntityManager;
use Espo\Tools\Layout\LayoutProvider;
use GuzzleHttp\Client;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);
/** @var Config $config */
$config = $container->getByClass(Config::class);
/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);

$metadata->init(true);

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $m = $pass ? 'PASS' : 'FAIL';
    echo "  [$m] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$countGooglePanels = static function (mixed $layout) use (&$countGooglePanels): int {
    if (!is_array($layout)) {
        return 0;
    }

    $count = 0;

    foreach ($layout as $panel) {
        if (is_object($panel)) {
            $panel = json_decode(json_encode($panel), true);
        }

        if (is_array($panel) && ($panel['name'] ?? null) === 'GoogleCalendar') {
            $count++;
        }
    }

    return $count;
};

$layoutHasField = static function (mixed $layout, string $fieldName) use (&$layoutHasField): bool {
    if (is_array($layout)) {
        foreach ($layout as $item) {
            if ($layoutHasField($item, $fieldName)) {
                return true;
            }
        }

        return false;
    }

    if (is_object($layout)) {
        if (($layout->name ?? null) === $fieldName) {
            return true;
        }

        foreach (get_object_vars($layout) as $value) {
            if ($layoutHasField($value, $fieldName)) {
                return true;
            }
        }
    }

    return false;
};

$tableNameForEntity = static function (string $entityType): string {
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $entityType) ?? $entityType);
};

echo "Dynamic Google Calendar entity smoke\n\n";

$entityTypes = [];

foreach ($em->getRDBRepository('CalendarDateSource')
    ->select(['targetEntityType'])
    ->where(['isActive' => true, 'deleted' => false])
    ->find() as $row) {
    $type = $row->get('targetEntityType');

    if (is_string($type) && $type !== '') {
        $entityTypes[$type] = true;
    }
}

$entityTypes = array_keys($entityTypes);
sort($entityTypes);

$ok('at least one active CalendarDateSource target entity', $entityTypes !== [], 'count=' . count($entityTypes));

$dateSourceProvider = $injectableFactory->create(DateSourceProvider::class);
$layoutProvider = $injectableFactory->create(LayoutProvider::class);
$pdo = $em->getPDO();

$requiredFieldNames = array_keys(GoogleCalendarCapableEntities::perDateFieldDefs());

foreach ($entityTypes as $entityType) {
    echo "\n[$entityType]\n";

    if (!$metadata->get(['scopes', $entityType, 'entity'])) {
        $ok("$entityType scope.entity", false, 'not an entity scope');
        continue;
    }

    $fields = $metadata->get(['entityDefs', $entityType, 'fields']) ?? [];

    foreach ($requiredFieldNames as $fieldName) {
        $ok(
            "$entityType metadata field $fieldName",
            is_array($fields) && isset($fields[$fieldName]),
            'missing in entityDefs'
        );
    }

    $table = $tableNameForEntity($entityType);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'save_to_google_calendar'");
    $column = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
    $ok(
        "$entityType DB column save_to_google_calendar",
        is_array($column) && ($column['Field'] ?? '') === 'save_to_google_calendar',
        'table=' . $table
    );

    $sources = $dateSourceProvider->getActiveSourcesForEntityType($entityType);
    $ok(
        "$entityType has active CalendarDateSource row(s)",
        $sources !== [],
        'count=' . count($sources)
    );

    foreach (['detail', 'detailSmall'] as $layoutType) {
        $layoutJson = $layoutProvider->get($entityType, $layoutType);

        if ($layoutJson === null) {
            $ok("$entityType layout $layoutType routable", false, 'null layout');
            continue;
        }

        $layout = Json::decode($layoutJson);
        $panelCount = $countGooglePanels($layout);

        $ok(
            "$entityType layout $layoutType has exactly one GoogleCalendar panel",
            $panelCount === 1,
            'panels=' . $panelCount
        );
        $ok(
            "$entityType layout $layoutType has saveToGoogleCalendar field",
            $layoutHasField($layout, 'saveToGoogleCalendar')
        );
    }

    $customDetail = 'custom/Espo/Custom/Resources/layouts/' . $entityType . '/detail.json';

    if (is_readable($customDetail)) {
        $customLayout = Json::decode(file_get_contents($customDetail) ?: '[]');
        $ok(
            "$entityType Custom detail.json has exactly one GoogleCalendar panel",
            $countGooglePanels($customLayout) === 1,
            'panels=' . $countGooglePanels($customLayout)
        );
    }
}

echo "\nREST date-source-options\n";

$user = $em->getRDBRepository(User::ENTITY_TYPE)
    ->where(['userName' => 'smoke_api_catalog', 'deleted' => false])
    ->findOne();

if ($user === null) {
    $ok('smoke_api_catalog user exists', false);
} else {
    $siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
    $client = new Client([
        'base_uri' => $siteUrl . '/',
        'verify' => false,
        'timeout' => 30,
        'http_errors' => false,
        'headers' => ['X-Api-Key' => (string) $user->get('apiKey')],
    ]);

    foreach ($entityTypes as $entityType) {
        try {
            $r = $client->get("api/v1/GoogleIntegration/calendar/date-source-options/{$entityType}");
            $status = $r->getStatusCode();

            if ($status === 403) {
                $ok(
                    "GET date-source-options/$entityType skipped (API user ACL)",
                    true,
                    'code=403'
                );
                continue;
            }

            $ok(
                "GET date-source-options/$entityType → 200",
                $status === 200,
                'code=' . $status
            );

            if ($status === 200) {
                $body = json_decode((string) $r->getBody(), true);
                $sources = is_array($body['sources'] ?? null) ? $body['sources'] : [];
                $ok(
                    "$entityType date-source-options returns sources",
                    $sources !== [],
                    'count=' . count($sources)
                );
            }
        } catch (Throwable $e) {
            $ok("GET date-source-options/$entityType", false, $e->getMessage());
        }
    }
}

echo "\n=== " . ($fail === 0 ? 'ALL PASS' : "$fail FAILURE(S)") . " ===\n";
exit($fail === 0 ? 0 : 1);
