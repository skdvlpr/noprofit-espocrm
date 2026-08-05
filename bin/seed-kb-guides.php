<?php

declare(strict_types=1);

/**
 * Seed / refresh Italian Knowledge Base guides (local DDEV / CI only).
 *
 * Usage: ddev exec php bin/seed-kb-guides.php
 */

require __DIR__ . '/lib/refuse-production.php';

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Modules\NonprofitEspocrm\Tools\KnowledgeBaseGuidesSeeder;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

$result = $container
    ->getByClass(\Espo\Core\InjectableFactory::class)
    ->create(KnowledgeBaseGuidesSeeder::class)
    ->run();

echo "Category Guide operative id={$result['categoryId']}\n";
echo 'Created: ' . (count($result['created']) ? implode(' | ', $result['created']) : '(none)') . "\n";
echo 'Updated: ' . (count($result['updated']) ? implode(' | ', $result['updated']) : '(none)') . "\n";
echo "OK\n";
