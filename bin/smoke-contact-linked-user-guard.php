<?php

declare(strict_types=1);

require __DIR__ . '/lib/refuse-production.php';

/**
 * Smoke: Volunteer Contact create must not bind another User (linkedUser / portalUser).
 *
 * Static (no Espo boot): hook + RoleSetup + i18n invariants.
 * Optional Espo boot: hook is registered for Contact BeforeSave.
 *
 * Usage:
 *   php bin/smoke-contact-linked-user-guard.php
 *   ddev exec php bin/smoke-contact-linked-user-guard.php
 */

$fail = 0;
$ok = 0;

$assert = static function (string $label, bool $cond, string $detail = '') use (&$fail, &$ok): void {
    if ($cond) {
        echo "OK  {$label}" . ($detail !== '' ? " ({$detail})" : '') . PHP_EOL;
        $ok++;
    } else {
        echo "FAIL {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
        $fail++;
    }
};

$root = dirname(__DIR__);
$hookPath = $root . '/custom/Espo/Modules/NonprofitEspocrm/Hooks/Contact/ProtectLinkedUser.php';
$rolePath = $root . '/custom/Espo/Modules/NonprofitEspocrm/Tools/RoleSetup.php';

$hook = is_file($hookPath) ? (string) file_get_contents($hookPath) : '';
$role = is_file($rolePath) ? (string) file_get_contents($rolePath) : '';

$assert('ProtectLinkedUser hook file exists', $hook !== '');
$assert(
    'hook implements BeforeSave',
    str_contains($hook, 'implements BeforeSave')
);
$assert(
    'hook blocks foreign linkedUserId',
    str_contains($hook, 'linkedUserId')
    && str_contains($hook, 'cannotLinkContactToOtherUser')
    && str_contains($hook, 'Forbidden')
);
$assert(
    'hook allows self-link and unlink',
    str_contains($hook, 'getId()')
    && str_contains($hook, "newId === ''")
);
$assert(
    'hook blocks portalUser bind for non-admin',
    str_contains($hook, 'portalUserId')
    && str_contains($hook, 'cannotLinkContactToPortalUser')
);
$assert(
    'hook skips only SKIP_ALL / admin / system',
    str_contains($hook, 'SaveOption::SKIP_ALL')
    && str_contains($hook, 'isAdmin()')
    && str_contains($hook, 'isSystem()')
);

$assert(
    'RoleSetup Volunteer field-locks linkedUser edit=no',
    str_contains($role, "'linkedUser' => ['read' => 'yes', 'edit' => 'no']")
);
$assert(
    'RoleSetup Volunteer field-locks portalUser edit=no',
    str_contains($role, "'portalUser' => ['read' => 'no', 'edit' => 'no']")
);

foreach (['en_US', 'it_IT', 'ru_RU'] as $lang) {
    $i18nPath = $root . '/custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/' . $lang . '/Contact.json';
    $json = is_file($i18nPath) ? (string) file_get_contents($i18nPath) : '';
    $data = json_decode($json, true);
    $assert(
        "{$lang} Contact i18n has cannotLinkContactToOtherUser",
        is_array($data)
        && isset($data['messages']['cannotLinkContactToOtherUser'])
        && is_string($data['messages']['cannotLinkContactToOtherUser'])
        && $data['messages']['cannotLinkContactToOtherUser'] !== ''
    );
    $assert(
        "{$lang} Contact i18n has cannotLinkContactToPortalUser",
        is_array($data)
        && isset($data['messages']['cannotLinkContactToPortalUser'])
        && is_string($data['messages']['cannotLinkContactToPortalUser'])
        && $data['messages']['cannotLinkContactToPortalUser'] !== ''
    );
}

$syncPath = $root . '/custom/Espo/Modules/NonprofitEspocrm/Tools/UserContactProfileSync.php';
$sync = is_file($syncPath) ? (string) file_get_contents($syncPath) : '';
$assert(
    'UserContactProfileSync still self-links new Contact (hook allows self)',
    str_contains($sync, "'linkedUserId' => \$user->getId()")
);

echo PHP_EOL . "Passed: {$ok}; failed: {$fail}" . PHP_EOL;

exit($fail === 0 ? 0 : 1);
