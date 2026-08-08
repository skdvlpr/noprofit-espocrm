<?php

declare(strict_types=1);


require __DIR__ . '/lib/refuse-production.php';


/**
 * Smoke: PrimaNota commission triangle + Stripe sourced-field lock + legacy migration helper.
 *
 * Usage: ddev exec php bin/smoke-prima-nota-stripe-commission.php
 *
 * Does NOT delete QA-STRIPE-MOCK* manual-test rows.
 * Does NOT run the legacy migration (call bin/migrate-prima-nota-legacy-gross.php separately).
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Repository\Option\SaveOption;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
$em = $container->get('entityManager');
/** @var Metadata $metadata */
$metadata = $container->get('metadata');

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    echo '  [' . ($pass ? 'PASS' : 'FAIL') . '] ' . $name . ($detail !== '' ? " — $detail" : '') . "\n";
};

$isBadRequestMsg = static function (string $message, string ...$needles): bool {
    foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($message, $needle)) {
            return true;
        }
    }

    return false;
};

echo "PrimaNota commission / Stripe lock\n";

$ok('amount is readOnly', (bool) $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'amount', 'readOnly']) === true);
$ok('amountGross field', $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'amountGross', 'type']) === 'currency');
$ok('commissionAmount default 0', (float) ($metadata->get(['entityDefs', 'PrimaNota', 'fields', 'commissionAmount', 'default']) ?? -1) === 0.0);
$ok('commissionPercent default 0', (float) ($metadata->get(['entityDefs', 'PrimaNota', 'fields', 'commissionPercent', 'default']) ?? -1) === 0.0);
$ok(
    'ProtectDonationPaymentProvider hook exists',
    is_file(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Hooks/PrimaNota/ProtectDonationPaymentProvider.php')
);
$ok(
    'provider is enum',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'donationPaymentProvider', 'type']) === 'enum'
);
$ok(
    'provider default Other',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'donationPaymentProvider', 'default']) === 'Other'
);
$providerOptions = $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'donationPaymentProvider', 'options']) ?? [];
$ok('provider options include Stripe', in_array('Stripe', $providerOptions, true));
$ok('provider options include SatispayDirect', in_array('SatispayDirect', $providerOptions, true));
$ok('provider options include GoFundMe', in_array('GoFundMe', $providerOptions, true));
$ok('provider options include FivePerMille', in_array('FivePerMille', $providerOptions, true));
$itMessages = json_decode(
    (string) file_get_contents(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/PrimaNota.json'),
    true
);
$ok(
    'IT locale has commissionExceedsGross ASCII',
    ($itMessages['messages']['commissionExceedsGross'] ?? '') === "La commissione non puo' superare l'importo lordo."
);
$ok(
    'IT PrimaNota i18n is ASCII-only',
    preg_match('/[^\x00-\x7F]/', (string) file_get_contents(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/PrimaNota.json')) === 0
);
$ok(
    'formula does not autofill transactionDate with today',
    !preg_match(
        '/if\s*\(\s*transactionDate\s*==\s*null\s*\)\s*\{[^}]*datetime\\\\today/',
        (string) file_get_contents(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/formula/PrimaNota.json')
    )
);
$ok(
    'transactionDate field is required',
    (bool) $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'transactionDate', 'required']) === true
);
$ok(
    'stripePaymentMethodType field exists',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'stripePaymentMethodType', 'type']) === 'enum'
);
$ok(
    'stripeChargeId field exists',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'stripeChargeId', 'type']) === 'varchar'
);
$ok(
    'stripeChargeId NOT entityDefs readOnly (API ingest must write)',
    ! (bool) $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'stripeChargeId', 'readOnly'])
);
$ok(
    'stripeBillingEmail NOT entityDefs readOnly (API ingest must write)',
    ! (bool) $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'stripeBillingEmail', 'readOnly'])
);
$ok(
    'stripeDetails panel dynamicLogic',
    $metadata->get(['clientDefs', 'PrimaNota', 'dynamicLogic', 'panels', 'stripeDetails', 'visible', 'conditionGroup', 0, 'value']) === 'Stripe'
);
$ok(
    'refreshFromStripe detail button exists',
    $metadata->get(['clientDefs', 'PrimaNota', 'menu', 'detail', 'buttons', 1, 'name']) === 'refreshFromStripe'
        || in_array(
            'refreshFromStripe',
            array_column($metadata->get(['clientDefs', 'PrimaNota', 'menu', 'detail', 'buttons']) ?? [], 'name'),
            true
        )
);
$ok(
    'refreshFromStripe handler file',
    is_file(__DIR__ . '/../client/custom/modules/nonprofit-espocrm/src/handlers/prima-nota/refresh-from-stripe.js')
);
$ok(
    'StripeRefreshService class',
    class_exists(\Espo\Modules\NonprofitEspocrm\Tools\PrimaNota\StripeRefreshService::class)
);

$listInlineEdit = file_get_contents(
    __DIR__ . '/../client/custom/modules/nonprofit-espocrm/src/views/record/list-inline-edit.js'
) ?: '';
$ok(
    'PrimaNota list inline edit locks online-payment sourced fields',
    str_contains($listInlineEdit, '_isOnlinePaymentFieldLocked')
        && str_contains($listInlineEdit, 'paymentStatus')
        && str_contains($listInlineEdit, 'Stripe')
);
$ok(
    'Stripe refresh handler reports snapshot field count',
    str_contains(
        file_get_contents(__DIR__ . '/../client/custom/modules/nonprofit-espocrm/src/handlers/prima-nota/refresh-from-stripe.js') ?: '',
        'snapshotFieldCount'
    )
);
$ok(
    'StripeRefreshService returns snapshotSynced',
    str_contains(
        file_get_contents(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Tools/PrimaNota/StripeRefreshService.php') ?: '',
        'snapshotSynced'
    )
);
$ok(
    'stripeChargeId UI readOnly via clientDefs when Stripe',
    $metadata->get(['clientDefs', 'PrimaNota', 'dynamicLogic', 'fields', 'stripeChargeId', 'readOnly', 'conditionGroup', 0, 'value']) === 'Stripe'
);
$ok(
    'donationFrequency is enum',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'donationFrequency', 'type']) === 'enum'
);
$freqOpts = $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'donationFrequency', 'options']) ?? [];
$ok('donationFrequency options OneTime', in_array('OneTime', $freqOpts, true));
$ok('donationFrequency options Recurring', in_array('Recurring', $freqOpts, true));
$ok(
    'donationFrequency default OneTime',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'donationFrequency', 'default']) === 'OneTime'
);
$ok(
    'stripeRadarRiskLevel is enum with styles',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'stripeRadarRiskLevel', 'type']) === 'enum'
        && ($metadata->get(['entityDefs', 'PrimaNota', 'fields', 'stripeRadarRiskLevel', 'style', 'normal']) === 'success')
        && ($metadata->get(['entityDefs', 'PrimaNota', 'fields', 'stripeRadarRiskLevel', 'style', 'highest']) === 'danger')
);
$ok(
    'stripePaymentMethodType is enum with styles',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'stripePaymentMethodType', 'type']) === 'enum'
        && in_array('link', $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'stripePaymentMethodType', 'options']) ?? [], true)
        && ($metadata->get(['entityDefs', 'PrimaNota', 'fields', 'stripePaymentMethodType', 'style', 'link']) === 'info')
);
$ok(
    'paymentStatus has label styles',
    (bool) $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'paymentStatus', 'displayAsLabel'])
        && ($metadata->get(['entityDefs', 'PrimaNota', 'fields', 'paymentStatus', 'style', 'Inviato']) === 'success')
);
$ok(
    'paymentStatus UI readOnly when Stripe',
    $metadata->get(['clientDefs', 'PrimaNota', 'dynamicLogic', 'fields', 'paymentStatus', 'readOnly', 'conditionGroup', 0, 'value']) === 'Stripe'
);
$ok(
    'paymentStatus is enum',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'paymentStatus', 'type']) === 'enum'
);
$paymentStatusOpts = $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'paymentStatus', 'options']) ?? [];
foreach (['Planned', 'Inviato', 'Cancelled', 'Refunded', 'Disputed', 'Problematic'] as $statusOpt) {
    $ok("paymentStatus option $statusOpt", in_array($statusOpt, $paymentStatusOpts, true));
}
$ok(
    'paymentStatus Paid/PaidOut removed',
    ! in_array('Paid', $paymentStatusOpts, true)
        && ! in_array('PaidOut', $paymentStatusOpts, true)
);
$ok(
    'paymentStatus tooltip enabled',
    (bool) $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'paymentStatus', 'tooltip'])
);
$ok(
    'stripeInvoiceId field exists',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'stripeInvoiceId', 'type']) === 'varchar'
);
$ok(
    'stripePayoutId field exists',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'stripePayoutId', 'type']) === 'varchar'
);
$ok(
    'stripePayoutPaidAt field exists',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'stripePayoutPaidAt', 'type']) === 'datetime'
);
$ok(
    'paymentStatus Refunded style warning',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'paymentStatus', 'style', 'Refunded']) === 'warning'
);
$ok(
    'paymentStatus Disputed style danger',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'paymentStatus', 'style', 'Disputed']) === 'danger'
);
$ok(
    'PrimaNota stats income filter Inviato-only',
    is_file(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Tools/Reporting/PrimaNotaStatsProvider.php')
        && str_contains(
            (string) file_get_contents(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Tools/Reporting/PrimaNotaStatsProvider.php'),
            "'paymentStatus' => 'Inviato'"
        )
        && ! str_contains(
            (string) file_get_contents(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Tools/Reporting/PrimaNotaStatsProvider.php'),
            "'paymentStatus' => 'Paid'"
        )
);
$ok(
    'PrimaNota stats income filter Paid-only helper',
    is_file(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Tools/Reporting/PrimaNotaStatsProvider.php')
        && str_contains(
            (string) file_get_contents(__DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Tools/Reporting/PrimaNotaStatsProvider.php'),
            'incomeCountedWhere'
        )
);
$ok(
    'paymentStatus default Inviato',
    $metadata->get(['entityDefs', 'PrimaNota', 'fields', 'paymentStatus', 'default']) === 'Inviato'
);
$protectStripeHookPath = __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Hooks/PrimaNota/ProtectStripeSourcedFields.php';
$protectStripeHookSrc = is_file($protectStripeHookPath) ? (string) file_get_contents($protectStripeHookPath) : '';
$ok(
    'paymentStatus is Stripe-locked (trusted sync user bypasses)',
    $protectStripeHookSrc !== ''
        && (bool) preg_match("/'paymentStatus'/", $protectStripeHookSrc)
        && str_contains($protectStripeHookSrc, 'isTrustedStripeSyncUser')
);
$ok(
    'refuse-production guard exists',
    is_file(__DIR__ . '/lib/refuse-production.php')
);

$created = [];

$manualBase = static function (string $suffix) use ($em, &$created): \Espo\ORM\Entity {
    $row = $em->getNewEntity('PrimaNota');
    $row->set([
        'description' => 'Smoke manual ' . $suffix,
        'entryType' => 'Income',
        'internalClassification' => 'Donation',
        'donationPaymentProvider' => 'Other',
        'donationPaymentReference' => 'SMOKE-MANUAL-' . $suffix . '-' . date('His'),
        'amountGross' => 100.0,
        'amountGrossCurrency' => 'EUR',
        'transactionDate' => date('Y-m-d'),
    ]);

    return $row;
};

$fromPercent = $manualBase('pct');
$fromPercent->set('commissionPercent', 2.9);
$em->saveEntity($fromPercent);
$created[] = $fromPercent;
$ok('net from gross+percent', abs((float) $fromPercent->get('amount') - 97.1) < 0.001, 'amount=' . $fromPercent->get('amount'));
$ok('fee from percent', abs((float) $fromPercent->get('commissionAmount') - 2.9) < 0.001, 'fee=' . $fromPercent->get('commissionAmount'));

$fromFee = $manualBase('fee');
$fromFee->set([
    'amountGross' => 50.0,
    'commissionAmount' => 1.55,
    'commissionAmountCurrency' => 'EUR',
]);
$em->saveEntity($fromFee);
$created[] = $fromFee;
$ok('net from gross+fee', abs((float) $fromFee->get('amount') - 48.45) < 0.001, 'amount=' . $fromFee->get('amount'));
$ok('percent derived from fee', abs((float) $fromFee->get('commissionPercent') - 3.1) < 0.01, 'pct=' . $fromFee->get('commissionPercent'));

$emptyFee = $manualBase('empty');
$emptyFee->set('amountGross', 80.0);
$em->saveEntity($emptyFee);
$created[] = $emptyFee;
$ok('empty commission → fee 0', abs((float) $emptyFee->get('commissionAmount')) < 0.001);
$ok('empty commission → net equals gross', abs((float) $emptyFee->get('amount') - 80.0) < 0.001);

$noDateFailed = false;
try {
    $noDate = $em->getNewEntity('PrimaNota');
    $noDate->set([
        'description' => 'Smoke manual without date must fail',
        'entryType' => 'Income',
        'internalClassification' => 'Other',
        'amountGross' => 10.0,
        'amountGrossCurrency' => 'EUR',
    ]);
    $em->saveEntity($noDate);
    $created[] = $noDate;
} catch (\Throwable $e) {
    $msg = $e->getMessage();
    $noDateFailed = $e instanceof BadRequest
        && $isBadRequestMsg($msg, 'transactionDateRequired', 'Date is required', "La data e'", 'movement date');
}
$ok('manual create without transactionDate → validation error', $noDateFailed);

$newNoGrossFailed = false;
try {
    $noGross = $em->getNewEntity('PrimaNota');
    $noGross->set([
        'description' => 'Smoke new without gross must fail',
        'entryType' => 'Income',
        'amount' => 10.0,
        'amountCurrency' => 'EUR',
        'transactionDate' => date('Y-m-d'),
    ]);
    $em->saveEntity($noGross);
    $created[] = $noGross;
} catch (BadRequest $e) {
    $newNoGrossFailed = $isBadRequestMsg($e->getMessage(), 'Gross amount', 'importo lordo', 'grossRequired');
}
$ok('new without gross → BadRequest', $newNoGrossFailed);

$zeroNet = $manualBase('zeronet');
$zeroNet->set([
    'amountGross' => 10.0,
    'commissionAmount' => 10.0,
    'commissionAmountCurrency' => 'EUR',
]);
$em->saveEntity($zeroNet);
$created[] = $zeroNet;
$ok('net zero when fee equals gross', abs((float) $zeroNet->get('amount')) < 0.001);

$editFee = $manualBase('editfee');
$editFee->set('commissionPercent', 2.9);
$em->saveEntity($editFee);
$created[] = $editFee;
$editFee = $em->getEntityById('PrimaNota', $editFee->getId());
$editFee->set('commissionAmount', 5.0);
$editFee->set('commissionAmountCurrency', 'EUR');
$em->saveEntity($editFee);
$editFee = $em->getEntityById('PrimaNota', $editFee->getId());
$ok('edit fee → percent recalculated', abs((float) $editFee->get('commissionPercent') - 5.0) < 0.01);
$ok('edit fee → net recalculated', abs((float) $editFee->get('amount') - 95.0) < 0.001);

$editPct = $manualBase('editpct');
$editPct->set([
    'commissionAmount' => 2.9,
    'commissionAmountCurrency' => 'EUR',
]);
$em->saveEntity($editPct);
$created[] = $editPct;
$editPct = $em->getEntityById('PrimaNota', $editPct->getId());
$editPct->set('commissionPercent', 4.0);
$em->saveEntity($editPct);
$editPct = $em->getEntityById('PrimaNota', $editPct->getId());
$ok('edit percent → fee recalculated', abs((float) $editPct->get('commissionAmount') - 4.0) < 0.001);
$ok('edit percent → net recalculated', abs((float) $editPct->get('amount') - 96.0) < 0.001);

$editGross = $manualBase('editgross');
$editGross->set('commissionPercent', 10.0);
$em->saveEntity($editGross);
$created[] = $editGross;
$editGross = $em->getEntityById('PrimaNota', $editGross->getId());
$editGross->set('amountGross', 200.0);
$editGross->set('amountGrossCurrency', 'EUR');
$em->saveEntity($editGross);
$editGross = $em->getEntityById('PrimaNota', $editGross->getId());
$ok('edit gross → fee from percent', abs((float) $editGross->get('commissionAmount') - 20.0) < 0.001);
$ok('edit gross → net', abs((float) $editGross->get('amount') - 180.0) < 0.001);

$clearZero = $manualBase('clear0');
$clearZero->set('commissionPercent', 10.0);
$em->saveEntity($clearZero);
$created[] = $clearZero;
$clearZero = $em->getEntityById('PrimaNota', $clearZero->getId());
$clearZero->set('amountGross', 0.0);
$clearZero->set('amountGrossCurrency', 'EUR');
$em->saveEntity($clearZero);
$clearZero = $em->getEntityById('PrimaNota', $clearZero->getId());
$ok('clear gross=0 → fee 0', abs((float) $clearZero->get('commissionAmount')) < 0.001);
$ok('clear gross=0 → percent 0', abs((float) $clearZero->get('commissionPercent')) < 0.001);
$ok('clear gross=0 → net 0', abs((float) $clearZero->get('amount')) < 0.001);

$feeTooHigh = false;
try {
    $bad = $manualBase('feebad');
    $bad->set([
        'amountGross' => 10.0,
        'commissionAmount' => 20.0,
        'commissionAmountCurrency' => 'EUR',
    ]);
    $em->saveEntity($bad);
    $created[] = $bad;
} catch (BadRequest $e) {
    $feeTooHigh = $isBadRequestMsg($e->getMessage(), 'cannot exceed', 'non può superare', 'commissionExceedsGross');
}
$ok('fee > gross → BadRequest', $feeTooHigh);

// Stripe create via UI/system user must be blocked; ingest uses type=api or SKIP_ALL
$manualStripeBlocked = false;
try {
    $blocked = $em->getNewEntity('PrimaNota');
    $blocked->set([
        'description' => 'Smoke block manual Stripe',
        'entryType' => 'Income',
        'internalClassification' => 'Donation',
        'donationPaymentProvider' => 'Stripe',
        'donationPaymentReference' => 'SMOKE-BLOCK-STRIPE-' . date('His'),
        'amountGross' => 10.0,
        'amountGrossCurrency' => 'EUR',
        'transactionDate' => date('Y-m-d'),
    ]);
    $em->saveEntity($blocked);
    $created[] = $blocked;
} catch (BadRequest $e) {
    $manualStripeBlocked = $isBadRequestMsg($e->getMessage(), 'Stripe platform can only be set', 'può essere impostata solo', 'stripeManualCreateBlocked');
}
$ok('manual create with Stripe → BadRequest', $manualStripeBlocked);

// Stripe create OK; later money/subject edit blocked; Espo field (modelD) OK
$stripe = $em->getNewEntity('PrimaNota');
$stripe->set([
    'description' => 'Smoke Stripe lock',
    'entryType' => 'Income',
    'internalClassification' => 'Donation',
    'donationPaymentProvider' => 'Stripe',
    'donationPaymentReference' => 'SMOKE-STRIPE-LOCK-' . date('His'),
    'amountGross' => 100.0,
    'amountGrossCurrency' => 'EUR',
    'commissionPercent' => 2.9,
    'commissionAmount' => 2.9,
    'commissionAmountCurrency' => 'EUR',
    'amount' => 97.1,
    'amountCurrency' => 'EUR',
    'subjectName' => 'Donor From Stripe',
    'transactionDate' => date('Y-m-d'),
    'stripeChargeId' => 'ch_smoke_lock',
]);
$em->saveEntity($stripe, [SaveOption::SKIP_ALL => true]);
$created[] = $stripe;
$ok('Stripe create via SKIP_ALL (ingest) allowed', $stripe->getId() !== null);

$platformChangeBlocked = false;
try {
    $other = $manualBase('platimmut');
    $em->saveEntity($other);
    $created[] = $other;
    $other = $em->getEntityById('PrimaNota', $other->getId());
    $other->set('donationPaymentProvider', 'Cash');
    $em->saveEntity($other);
} catch (BadRequest $e) {
    $platformChangeBlocked = $isBadRequestMsg($e->getMessage(), 'cannot be changed', 'non può essere modificata', 'platformImmutable');
}
$ok('platform change after create → BadRequest', $platformChangeBlocked);

$stripeMoneyBlocked = false;
try {
    $stripe = $em->getEntityById('PrimaNota', $stripe->getId());
    $stripe->set('commissionAmount', 9.0);
    $stripe->set('commissionAmountCurrency', 'EUR');
    $em->saveEntity($stripe);
} catch (BadRequest $e) {
    $stripeMoneyBlocked = $isBadRequestMsg($e->getMessage(), 'Stripe', 'stripeSourcedReadOnly');
}
$ok('Stripe money edit → BadRequest', $stripeMoneyBlocked);

// Incomplete Stripe ingest (no charge id): allow settlement backfill once
$incomplete = $em->getNewEntity('PrimaNota');
$incomplete->set([
    'description' => 'Smoke Stripe incomplete backfill',
    'entryType' => 'Income',
    'internalClassification' => 'Donation',
    'donationPaymentProvider' => 'Stripe',
    'donationPaymentReference' => 'SMOKE-STRIPE-INCOMPLETE-' . date('His'),
    'amountGross' => 5.0,
    'amountGrossCurrency' => 'EUR',
    'commissionAmount' => 0,
    'commissionAmountCurrency' => 'EUR',
    'commissionPercent' => 0,
    'amount' => 5.0,
    'amountCurrency' => 'EUR',
    'transactionDate' => date('Y-m-d'),
]);
$em->saveEntity($incomplete, [SaveOption::SKIP_ALL => true]);
$created[] = $incomplete;
$incomplete = $em->getEntityById('PrimaNota', $incomplete->getId());
$incomplete->set([
    'commissionAmount' => 0.33,
    'commissionAmountCurrency' => 'EUR',
    'commissionPercent' => 6.6,
    'amount' => 4.67,
    'amountCurrency' => 'EUR',
    'stripeChargeId' => 'ch_smoke_backfill',
    'stripeBalanceTransactionId' => 'txn_smoke_backfill',
    'stripePaymentMethodType' => 'link',
]);
$em->saveEntity($incomplete);
$incomplete = $em->getEntityById('PrimaNota', $incomplete->getId());
$ok(
    'incomplete Stripe row allows fee backfill',
    abs((float) $incomplete->get('commissionAmount') - 0.33) < 0.001
        && abs((float) $incomplete->get('amount') - 4.67) < 0.001
        && $incomplete->get('stripeChargeId') === 'ch_smoke_backfill'
);
$incompleteLocked = false;
try {
    $incomplete->set('commissionAmount', 1.0);
    $em->saveEntity($incomplete);
} catch (BadRequest $e) {
    $incompleteLocked = $isBadRequestMsg($e->getMessage(), 'Stripe', 'stripeSourcedReadOnly');
}
$ok('after backfill Stripe money edit → BadRequest', $incompleteLocked);

$stripeSubjectBlocked = false;
try {
    $stripe = $em->getEntityById('PrimaNota', $stripe->getId());
    $stripe->set('subjectName', 'Hacker Rename');
    $em->saveEntity($stripe);
} catch (BadRequest $e) {
    $stripeSubjectBlocked = $isBadRequestMsg($e->getMessage(), 'Stripe', 'stripeSourcedReadOnly');
}
$ok('Stripe subjectName edit → BadRequest', $stripeSubjectBlocked);

$stripeDateBlocked = false;
try {
    $stripe = $em->getEntityById('PrimaNota', $stripe->getId());
    $stripe->set('transactionDate', '2020-01-01');
    $em->saveEntity($stripe);
} catch (BadRequest $e) {
    $stripeDateBlocked = $isBadRequestMsg($e->getMessage(), 'Stripe', 'stripeSourcedReadOnly');
}
$ok('Stripe transactionDate edit → BadRequest', $stripeDateBlocked);

$stripeStatusBlocked = false;
try {
    $stripe = $em->getEntityById('PrimaNota', $stripe->getId());
    // Must differ from current (default Inviato) or isAttributeChanged is false.
    $stripe->set('paymentStatus', 'Planned');
    $em->saveEntity($stripe);
} catch (BadRequest $e) {
    $stripeStatusBlocked = $isBadRequestMsg($e->getMessage(), 'Stripe', 'stripeSourcedReadOnly');
}
$ok('Stripe paymentStatus edit → BadRequest', $stripeStatusBlocked);

$stripe = $em->getEntityById('PrimaNota', $stripe->getId());
$stripe->set('modelDClassification', 'C');
$em->saveEntity($stripe);
$stripe = $em->getEntityById('PrimaNota', $stripe->getId());
$ok('Stripe modelDClassification still editable', $stripe->get('modelDClassification') === 'C');

$formulaPath = __DIR__ . '/../custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/formula/PrimaNota.json';
$formulaScript = (string) ((json_decode((string) file_get_contents($formulaPath), true) ?: [])['beforeSaveCustomScript'] ?? '');
$ok('formula uses grossCleared only on grossChanged', str_contains($formulaScript, 'if ($grossChanged)'));
$ok('formula zeros net on clear', str_contains($formulaScript, "amount = 0;\n} else {"));

foreach ($created as $row) {
    if ($row->getId()) {
        $em->removeEntity($row);
    }
}

$qaLeft = $em->getRDBRepository('PrimaNota')
    ->where(['donationPaymentReference*' => 'QA-STRIPE-MOCK%'])
    ->count();
// Optional local fixtures — absence is OK (not created by this smoke).
echo '  [INFO] QA Stripe mock rows present: ' . $qaLeft . "\n";

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
