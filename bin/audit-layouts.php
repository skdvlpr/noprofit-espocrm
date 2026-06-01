<?php

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Metadata;
use Espo\Core\InjectableFactory;
use Espo\Tools\Layout\LayoutProvider;

$app = new Application();
$container = $app->getContainer();
$metadata = $container->getByClass(Metadata::class);
$metadata->init(true);
$layoutProvider = $container->getByClass(InjectableFactory::class)
    ->create(LayoutProvider::class);

$entityTypes = [
    'Account', 'Opportunity', 'Member', 'VolunteerEmployee', 'MealCount', 'Document',
    'Meeting', 'Call', 'Task', 'Campaign',
    'GCalSmokeAllDay', 'GCalSmokeDateTime', 'GCalSmokeTwinDate',
];

$layoutTypes = [
    'detail', 'detailSmall', 'list', 'listSmall', 'filters', 'massUpdate', 'edit',
];

$requiredByEntity = [
    'Opportunity' => ['presentationDate', 'closeDate', 'stage', 'amount', 'name', 'account'],
    'Member' => ['firstName', 'lastName', 'status', 'birthDate'],
    'VolunteerEmployee' => ['firstName', 'lastName', 'status'],
    'MealCount' => ['date', 'adults', 'minors', 'totalMeals', 'foodUnitPrice'],
    'Account' => ['name'],
];

echo "=== Layout audit ===\n\n";

$issues = [];

foreach ($entityTypes as $entityType) {
    if (!($metadata->get(['scopes', $entityType, 'entity']) ?? false)) {
        continue;
    }

    $module = $metadata->get("app.layouts.{$entityType}.detail.module") ?? '(default reverse modules)';

    echo "## {$entityType} (detail module: {$module})\n";

    foreach ($layoutTypes as $layoutType) {
        $raw = $layoutProvider->get($entityType, $layoutType);

        if ($raw === null) {
            if (in_array($layoutType, ['detail', 'list', 'filters'], true)) {
                $issues[] = "{$entityType}/{$layoutType}: MISSING";
                echo "  [MISSING] {$layoutType}\n";
            }
            continue;
        }

        $layout = Json::decode($raw);
        $fields = extractFieldNames($layout);

        if ($layoutType === 'detail' && isset($requiredByEntity[$entityType])) {
            foreach ($requiredByEntity[$entityType] as $required) {
                if (!in_array($required, $fields, true)) {
                    $issues[] = "{$entityType}/detail: missing field {$required}";
                    echo "  [GAP] detail missing: {$required}\n";
                }
            }
        }

        if ($layoutType === 'detail') {
            echo "  detail fields: " . implode(', ', $fields) . "\n";
        }
    }

    echo "\n";
}

echo "=== Issues: " . count($issues) . " ===\n";
foreach ($issues as $issue) {
    echo "  - {$issue}\n";
}

/**
 * @param mixed $layout
 * @return list<string>
 */
function extractFieldNames(mixed $layout): array
{
    if (!is_array($layout)) {
        return [];
    }

    $names = [];

    foreach ($layout as $panel) {
        if (is_object($panel)) {
            $panel = json_decode(json_encode($panel), true);
        }

        if (!is_array($panel)) {
            continue;
        }

        foreach ($panel['rows'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as $cell) {
                if (is_array($cell) && isset($cell['name']) && is_string($cell['name'])) {
                    $names[] = $cell['name'];
                }
            }
        }
    }

    return array_values(array_unique($names));
}
