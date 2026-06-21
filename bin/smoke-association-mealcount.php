<?php
/**
 * Smoke: AssociationMealCount entity + reporting layer (Task 7.4).
 *
 * Verifies:
 *   - entityDefs/scopes metadata present
 *   - CRUD via ORM with formula name generation
 *   - AssociationMealCountStatsProvider summary/totals
 *   - ReportingProfileRegistry profile
 *
 * Usage: ddev exec php bin/smoke-association-mealcount.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Metadata;
use Espo\Modules\SafehouseCrm\Tools\Reporting\AssociationMealCountStatsProvider;
use Espo\Modules\SafehouseCrm\Tools\Reporting\ReportingProfileRegistry;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $mark = $pass ? 'PASS' : 'FAIL';
    echo "  [$mark] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

/** @var Metadata $metadata */
$metadata = $container->getByClass(Metadata::class);
$em = $container->get('entityManager');
/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);

echo "Metadata\n";

$scope = $metadata->get(['scopes', 'AssociationMealCount']);
$ok('scopes AssociationMealCount entity=true', ($scope['entity'] ?? false) === true);
$ok('scopes AssociationMealCount tab=false', ($scope['tab'] ?? true) === false);

$fields = $metadata->get(['entityDefs', 'AssociationMealCount', 'fields']) ?? [];
$ok('entityDefs portionCount field', isset($fields['portionCount']));
$ok('entityDefs account link', isset($fields['account']));

$registry = $injectableFactory->create(ReportingProfileRegistry::class);
$profile = $registry->getProfile('AssociationMealCount');
$ok('ReportingProfileRegistry profile', $profile !== null);
$ok(
    'Profile sum attributes',
    $profile !== null && $profile->sumAttributes === ['portionCount']
);

echo "\nCRUD + formula\n";

$created = [];
$prefix = 'SMOKE-Assoc-' . date('Ymd') . '-';
$today = date('Y-m-d');

try {
    /** @var \Espo\ORM\Entity|null $account */
    $account = $em->getRDBRepository('Account')->where(['deleted' => false])->findOne();

    if ($account === null) {
        $account = $em->getNewEntity('Account');
        $account->set('name', $prefix . 'Account');
        $em->saveEntity($account);
        $created[] = $account;
    }

    $expectedPortions = 0;

    foreach ([12, 8] as $i => $portions) {
        $entity = $em->getNewEntity('AssociationMealCount');
        $entity->set([
            'accountId' => $account->getId(),
            'date' => $today,
            'portionCount' => $portions,
        ]);
        $em->saveEntity($entity);
        $created[] = $entity;

        $expectedPortions += $portions;

        $ok(
            "Row $i name auto-generated",
            is_string($entity->get('name')) && $entity->get('name') !== '',
            'name=' . ($entity->get('name') ?? 'null')
        );
    }

    $ids = array_map(static fn ($entity) => $entity->getId(), array_filter(
        $created,
        static fn ($entity) => $entity->getEntityType() === 'AssociationMealCount'
    ));

    echo "\nAssociationMealCountStatsProvider\n";

    $provider = $injectableFactory->create(AssociationMealCountStatsProvider::class);
    $filterWhere = ['id' => $ids];

    $totals = $provider->getTotals(null, $filterWhere);
    $ok(
        'getTotals portionCount',
        (int) ($totals['portionCount'] ?? 0) === $expectedPortions,
        'got=' . ($totals['portionCount'] ?? 'null') . ' expected=' . $expectedPortions
    );

    $summary = $provider->getSummary();
    $ok('summary has today', isset($summary->today->portionCount));
    $ok('summary has month', isset($summary->month->portionCount));
    $ok('summary has year', isset($summary->year->portionCount));
    $ok(
        'summary metricList',
        ($summary->metricList ?? []) === ['portionCount']
    );

    echo "\nLayouts\n";

    $layoutBase = 'custom/Espo/Modules/SafehouseCrm/Resources/layouts/AssociationMealCount';
    $ok('list.json exists', is_readable("$layoutBase/list.json"));
    $ok('detail.json exists', is_readable("$layoutBase/detail.json"));
    $ok('filters.json exists', is_readable("$layoutBase/filters.json"));

    echo "\nFrontend list view\n";

    $listView = 'client/custom/modules/safehouse-crm/src/views/association-meal-count/list.js';
    $ok('association-meal-count list.js exists', is_readable($listView));
} finally {
    foreach ($created as $entity) {
        $em->removeEntity($entity);
    }
}

echo "\n" . ($fail === 0 ? 'ALL PASS' : "FAILED ($fail)") . "\n";
exit($fail > 0 ? 1 : 0);
