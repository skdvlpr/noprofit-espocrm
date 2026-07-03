<?php
/**
 * Provision Sportello digitale / legale teams and link group InboundEmail accounts.
 *
 * Usage:
 *   ddev exec php bin/setup-sportello-desks.php
 *
 * Expects group mailboxes already created in Admin → Group Email Accounts with:
 *   - sportello.digitale@safehouse.community → team Sportello digitale
 *   - sportello.legale@safehouse.community   → team Sportello legale
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Entities\InboundEmail;
use Espo\Entities\Team;
use Espo\Modules\NonprofitEspocrm\Tools\RoleSetup;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var InjectableFactory $injectableFactory */
$injectableFactory = $container->getByClass(InjectableFactory::class);

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);

$setup = $injectableFactory->create(RoleSetup::class);

echo "Provisioning roles (includes Desk)...\n";
foreach ($setup->provisionRoles() as $name => $status) {
    echo "  - $name: $status\n";
}

echo "\nProvisioning teams...\n";
foreach ($setup->provisionTeams() as $name => $status) {
    echo "  - $name: $status\n";
}

$bindings = [
    RoleSetup::TEAM_DIGITAL_DESK => [
        'emailAddress' => 'sportello.digitale@safehouse.community',
        'caseTypeDefault' => 'SportelloDigitale',
    ],
    RoleSetup::TEAM_LEGAL_DESK => [
        'emailAddress' => 'sportello.legale@safehouse.community',
        'caseTypeDefault' => 'SportelloLegale',
    ],
];

echo "\nLinking group InboundEmail accounts to teams...\n";

foreach ($bindings as $teamName => $binding) {
    $emailAddress = $binding['emailAddress'];
    $caseTypeDefault = $binding['caseTypeDefault'];
    /** @var ?Team $team */
    $team = $em->getRDBRepositoryByClass(Team::class)
        ->where(['name' => $teamName])
        ->findOne();

    if (!$team) {
        echo "  - $emailAddress: team \"$teamName\" not found\n";
        continue;
    }

    /** @var ?InboundEmail $inbound */
    $inbound = $em->getRDBRepositoryByClass(InboundEmail::class)
        ->where(['emailAddress' => $emailAddress])
        ->findOne();

    if (!$inbound) {
        echo "  - $emailAddress: InboundEmail not found (create group mailbox first)\n";
        continue;
    }

    $changed = false;

    if ($inbound->get('teamId') !== $team->getId()) {
        $inbound->set([
            'teamId' => $team->getId(),
            'teamsIds' => [$team->getId()],
            'addAllTeamUsers' => true,
        ]);
        $changed = true;
    }

    if ($inbound->get('caseTypeDefault') !== $caseTypeDefault) {
        $inbound->set('caseTypeDefault', $caseTypeDefault);
        $changed = true;
    }

    if ($changed) {
        $em->saveEntity($inbound);
        echo "  - $emailAddress: linked to \"$teamName\" (updated)\n";
    } else {
        echo "  - $emailAddress: linked to \"$teamName\" (unchanged)\n";
    }
}

echo "\nDone. Admin → Repair → Rebuild → Clear Cache recommended.\n";
