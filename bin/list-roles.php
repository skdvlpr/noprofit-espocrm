<?php
/**
 * Diagnostic: list every Role currently in the database with its scope-level
 * permissions for the Safehouse domain entities.
 *
 * Read-only. Safe to run anytime.
 *
 * Usage:
 *   php bin/list-roles.php
 *   ddev exec php bin/list-roles.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Entities\Role;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);

$roles = $em->getRDBRepositoryByClass(Role::class)->find();

$domainEntities = [
    'Account', 'AccountWebsite', 'Contact', 'Opportunity',
    'VolontarioDipendente', 'Associati', 'ConteggioPasti', 'Document', 'Lead', 'Case',
];

$count = 0;
foreach ($roles as $role) {
    $count++;
    echo "============================================================\n";
    echo "Role: " . $role->get('name') . " (id=" . $role->getId() . ")\n";

    $data = $role->get('data') ?? new \stdClass();
    $fieldData = $role->get('fieldData') ?? new \stdClass();

    echo "Scope-level access (only domain entities shown):\n";
    foreach ($domainEntities as $entity) {
        if (!isset($data->$entity)) {
            echo "  - $entity: (default)\n";
            continue;
        }
        $value = $data->$entity;
        if (is_object($value)) {
            $parts = [];
            foreach (get_object_vars($value) as $k => $v) {
                $parts[] = "$k=$v";
            }
            echo "  - $entity: " . implode(', ', $parts) . "\n";
        } else {
            echo "  - $entity: " . var_export($value, true) . "\n";
        }
    }

    $hasFieldData = false;
    foreach ($domainEntities as $entity) {
        if (isset($fieldData->$entity)) {
            $hasFieldData = true;
            break;
        }
    }

    if ($hasFieldData) {
        echo "Field-level ACL:\n";
        foreach ($domainEntities as $entity) {
            if (!isset($fieldData->$entity)) {
                continue;
            }
            echo "  - $entity:\n";
            foreach (get_object_vars($fieldData->$entity) as $field => $cfg) {
                $parts = [];
                foreach (get_object_vars($cfg) as $k => $v) {
                    $parts[] = "$k=$v";
                }
                echo "      * $field: " . implode(', ', $parts) . "\n";
            }
        }
    }
}

if ($count === 0) {
    echo "(No Role records exist in the database yet.)\n";
}
echo "\nTotal roles: $count\n";
