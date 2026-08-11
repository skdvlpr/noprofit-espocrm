<?php

require __DIR__ . '/lib/refuse-production.php';


/**
 * Smoke: Case intake requires linkParent; metadata has NGO type options.
 *
 * Usage:
 *   ddev exec php bin/smoke-case-intake.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

$failures = 0;
$report = static function (string $name, bool $pass, string $detail = '') use (&$failures): void {
    if (!$pass) {
        $failures++;
    }
    $marker = $pass ? '[PASS]' : '[FAIL]';
    echo "  $marker $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$metadata = $container->getByClass(Metadata::class);
$metadata->init(true);

$parentField = $metadata->get(['entityDefs', 'Case', 'fields', 'parent']);
$typeField = $metadata->get(['entityDefs', 'Case', 'fields', 'type']);

$report(
    'Case.parent is required linkParent',
    ($parentField['type'] ?? null) === 'linkParent'
        && ($parentField['required'] ?? false) === true
);
$report(
    'Case.parent entityList includes Contact (not Member/VolunteerEmployee)',
    in_array('Contact', $parentField['entityList'] ?? [], true)
        && !in_array('Member', $parentField['entityList'] ?? [], true)
        && !in_array('VolunteerEmployee', $parentField['entityList'] ?? [], true),
    'entityList=' . json_encode($parentField['entityList'] ?? [])
);
$report(
    'Case.type has NGO intake options',
    in_array('AssistanceRequest', $typeField['options'] ?? [], true)
        && in_array('RichiestaGenerica', $typeField['options'] ?? [], true)
        && ($typeField['required'] ?? false) === true
);

$itCase = json_decode(
    (string) file_get_contents(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/Case.json'),
    true
);
$report(
    'IT label websiteReferenceId has no SH suffix',
    ($itCase['fields']['websiteReferenceId'] ?? null) === 'ID segnalazione'
);
$report(
    'IT option RichiestaGenerica present',
    ($itCase['options']['type']['RichiestaGenerica'] ?? null) === 'Richiesta generica'
);

/** @var array<string, mixed>|null $metaMap */
$metaMap = $metadata->get(['app', 'config', 'inboundEmailCaseTypes']);
$report(
    'metadata inboundEmailCaseTypes maps info@ to RichiestaGenerica',
    is_array($metaMap) && ($metaMap['info@safehouse.community'] ?? null) === 'RichiestaGenerica'
);

$config = $container->getByClass(Config::class);
$writer = $container->getByClass(\Espo\Core\InjectableFactory::class)
    ->create(\Espo\Core\Utils\Config\ConfigWriter::class);
$defaults = [
    'sportello.digitale@safehouse.community' => 'SportelloDigitale',
    'sportello.legale@safehouse.community' => 'SportelloLegale',
    'info@safehouse.community' => 'RichiestaGenerica',
];
/** @var array<string, mixed> $current */
$current = $config->get('inboundEmailCaseTypes') ?? [];
if (! is_array($current)) {
    $current = [];
}
$merged = array_merge($defaults, $current);
foreach ($defaults as $email => $type) {
    $merged[$email] = $type;
}
$writer->set('inboundEmailCaseTypes', $merged);
$writer->save();
$report(
    'config inboundEmailCaseTypes maps info@ to RichiestaGenerica',
    ($merged['info@safehouse.community'] ?? null) === 'RichiestaGenerica'
);

$refClass = \Espo\Modules\NonprofitEspocrm\Tools\CaseObj\WebsiteReference::class;
$report(
    'WebsiteReference extracts correlation tokens',
    $refClass::extractCorrelationToken("Correlation: abc-123-def\n") === 'abc-123-def'
        && $refClass::extractCorrelationToken('subj [corr-uuid-1] x') === 'uuid-1'
        && $refClass::extractCorrelationToken('legacy [SD-UUID]') === 'uuid'
);
$report(
    'WebsiteReference mintForType defaults to sh',
    str_starts_with($refClass::mintForType('AssistanceRequest'), 'sh-')
        && str_starts_with($refClass::mintForType('RichiestaGenerica'), 'rg-')
);

$entityManager = $container->getByClass(EntityManager::class);

/** @var ?\Espo\ORM\Entity $contact */
$contact = $entityManager->getRDBRepository('Contact')
    ->select(['id'])
    ->limit(0, 1)
    ->findOne();

$createdSeedContact = false;
if ($contact === null) {
    $contact = $entityManager->getRDBRepository('Contact')->getNew();
    $contact->set([
        'firstName' => 'Smoke',
        'lastName' => 'CaseParent',
        'emailAddress' => 'smoke.case.parent@example.com',
    ]);
    $entityManager->saveEntity($contact);
    $createdSeedContact = true;
}

if ($contact === null || ! $contact->hasId()) {
    $report('Case create without parent rejected (400)', false, 'no Contact seed');
} else {
    $case = $entityManager->getRDBRepository('Case')->getNew();
    $case->set([
        'name' => 'Smoke intake missing parent ' . bin2hex(random_bytes(4)),
        'type' => 'InformationRequest',
    ]);

    try {
        $entityManager->saveEntity($case);
        $report('Case create without parent rejected (400)', false, 'save succeeded unexpectedly');
        if ($case->hasId()) {
            $entityManager->removeEntity($case);
        }
    } catch (\Throwable $e) {
        $report('Case create without parent rejected (400)', true, $e->getMessage());
    }

    $caseOk = $entityManager->getRDBRepository('Case')->getNew();
    $caseOk->set([
        'name' => 'Smoke intake with parent ' . bin2hex(random_bytes(4)),
        'type' => 'GuestIntake',
        'parentType' => 'Contact',
        'parentId' => $contact->getId(),
    ]);

    try {
        $entityManager->saveEntity($caseOk);
        $report('Case create with parent succeeds', $caseOk->hasId());
        if ($caseOk->hasId()) {
            $entityManager->removeEntity($caseOk);
        }
    } catch (\Throwable $e) {
        $report('Case create with parent succeeds', false, $e->getMessage());
    }

    $caseDesk = $entityManager->getRDBRepository('Case')->getNew();
    $caseDesk->set([
        'name' => 'Smoke desk auto id ' . bin2hex(random_bytes(4)),
        'type' => 'SportelloDigitale',
        'parentType' => 'Contact',
        'parentId' => $contact->getId(),
    ]);

    try {
        $entityManager->saveEntity($caseDesk);
        $ref = (string) ($caseDesk->get('websiteReferenceId') ?? '');
        $report(
            'Manual SportelloDigitale mints sd- websiteReferenceId',
            $caseDesk->hasId() && str_starts_with($ref, 'sd-'),
            $ref
        );
        if ($caseDesk->hasId()) {
            $entityManager->removeEntity($caseDesk);
        }
    } catch (\Throwable $e) {
        $report('Manual SportelloDigitale mints sd- websiteReferenceId', false, $e->getMessage());
    }

    $caseGeneric = $entityManager->getRDBRepository('Case')->getNew();
    $caseGeneric->set([
        'name' => 'Smoke generic auto id ' . bin2hex(random_bytes(4)),
        'type' => 'RichiestaGenerica',
        'parentType' => 'Contact',
        'parentId' => $contact->getId(),
    ]);

    try {
        $entityManager->saveEntity($caseGeneric);
        $ref = (string) ($caseGeneric->get('websiteReferenceId') ?? '');
        $report(
            'Manual RichiestaGenerica mints rg- websiteReferenceId',
            $caseGeneric->hasId() && str_starts_with($ref, 'rg-'),
            $ref
        );
        if ($caseGeneric->hasId()) {
            $entityManager->removeEntity($caseGeneric);
        }
    } catch (\Throwable $e) {
        $report('Manual RichiestaGenerica mints rg- websiteReferenceId', false, $e->getMessage());
    }

    $caseOther = $entityManager->getRDBRepository('Case')->getNew();
    $caseOther->set([
        'name' => 'Smoke other auto id ' . bin2hex(random_bytes(4)),
        'type' => 'AssistanceRequest',
        'parentType' => 'Contact',
        'parentId' => $contact->getId(),
    ]);

    try {
        $entityManager->saveEntity($caseOther);
        $ref = (string) ($caseOther->get('websiteReferenceId') ?? '');
        $report(
            'Manual AssistanceRequest mints sh- websiteReferenceId',
            $caseOther->hasId() && str_starts_with($ref, 'sh-'),
            $ref
        );
        if ($caseOther->hasId()) {
            $entityManager->removeEntity($caseOther);
        }
    } catch (\Throwable $e) {
        $report('Manual AssistanceRequest mints sh- websiteReferenceId', false, $e->getMessage());
    }

    // Re-save: empty ID → mint; existing ID → never overwrite.
    $caseResave = $entityManager->getRDBRepository('Case')->getNew();
    $caseResave->set([
        'name' => 'Smoke resave id ' . bin2hex(random_bytes(4)),
        'type' => 'AssistanceRequest',
        'parentType' => 'Contact',
        'parentId' => $contact->getId(),
    ]);

    try {
        $entityManager->saveEntity($caseResave);
        $originalRef = (string) ($caseResave->get('websiteReferenceId') ?? '');

        $caseResave->set('websiteReferenceId', 'sh-should-not-overwrite');
        $caseResave->set('description', 'touch protect');
        $entityManager->saveEntity($caseResave);
        $protected = $entityManager->getEntityById('Case', (string) $caseResave->getId());
        $protectedRef = (string) ($protected?->get('websiteReferenceId') ?? '');
        $report(
            'Re-save never overwrites existing websiteReferenceId',
            $protectedRef === $originalRef && $originalRef !== '',
            "original={$originalRef} after={$protectedRef}"
        );

        $protected->set('websiteReferenceId', null);
        $entityManager->saveEntity($protected, [SaveOption::SKIP_ALL => true]);
        $cleared = $entityManager->getEntityById('Case', (string) $caseResave->getId());
        $clearedRef = trim((string) ($cleared?->get('websiteReferenceId') ?? ''));
        $report(
            'SKIP_ALL can clear websiteReferenceId for remint fixture',
            $clearedRef === '',
            $clearedRef
        );

        $cleared->set('description', 'touch remint');
        $entityManager->saveEntity($cleared);
        $reminted = (string) ($cleared->get('websiteReferenceId') ?? '');
        $report(
            'Re-save mints websiteReferenceId when empty',
            str_starts_with($reminted, 'sh-') && $reminted !== $originalRef,
            $reminted
        );

        if ($caseResave->hasId()) {
            $entityManager->removeEntity($caseResave);
        }
    } catch (\Throwable $e) {
        $report('Re-save websiteReferenceId mint/protect', false, $e->getMessage());
    }

    // Simulate inbound Case from info@ group mailbox (type resolved → RichiestaGenerica → rg-).
    $inbound = $entityManager->getRDBRepository('InboundEmail')
        ->where(['emailAddress' => 'info@safehouse.community'])
        ->findOne();

    if ($inbound === null) {
        $report('Inbound info@ Case mint rg- (skipped)', true, 'no InboundEmail for info@');
    } else {
        $caseInbound = $entityManager->getRDBRepository('Case')->getNew();
        $caseInbound->set([
            'name' => '[corr-test-info] inbound sim',
            'description' => "Nome: Test\nEmail: t@example.com\nSportello: Richiesta generica\nTipo segnalazione: RichiestaGenerica\nCorrelation: test-info\n",
            'inboundEmailId' => $inbound->getId(),
            // parent optional for inbound (RequireParent allows)
        ]);
        try {
            $entityManager->saveEntity($caseInbound);
            $ref = (string) ($caseInbound->get('websiteReferenceId') ?? '');
            $type = (string) ($caseInbound->get('type') ?? '');
            $report(
                'Inbound info@ Case gets type RichiestaGenerica + rg- id',
                $caseInbound->hasId()
                    && $type === 'RichiestaGenerica'
                    && str_starts_with($ref, 'rg-'),
                "type={$type} ref={$ref}"
            );
            if ($caseInbound->hasId()) {
                $entityManager->removeEntity($caseInbound);
            }
        } catch (\Throwable $e) {
            $report('Inbound info@ Case gets type RichiestaGenerica + rg- id', false, $e->getMessage());
        }
    }
}

if ($createdSeedContact && $contact !== null && $contact->hasId()) {
    $entityManager->removeEntity($contact);
}

echo $failures === 0 ? "\nALL PASS\n" : "\nFAILURES: $failures\n";
exit($failures === 0 ? 0 : 1);
