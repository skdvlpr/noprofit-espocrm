<?php

/**
 * Helpers for ephemeral one-shot CRM scripts (roles, data migrations, one-off fixes).
 *
 * MANDATORY for any script that creates/rewrites Roles, runs data migrations,
 * seeds, or other one-off maintenance — even when the user explicitly asked for it.
 * After a successful run the script file MUST self-delete. No long-lived migrate-*
 * / setup-roles / import-export CLIs in the repo.
 *
 * Usage at top of a disposable script:
 *   require __DIR__ . '/lib/refuse-production.php';
 *   require __DIR__ . '/lib/ephemeral-oneshot.php';
 *   safehouse_ephemeral_oneshot_register(__FILE__);
 *
 * Always finish with:
 *   safehouse_ephemeral_oneshot_exit(0); // success → deletes this file
 *   safehouse_ephemeral_oneshot_exit(1); // failure → keeps file for retry
 *
 * There is no keep/bypass flag. Success always unlinks the script.
 */

declare(strict_types=1);

function safehouse_ephemeral_oneshot_register(string $scriptPath): void
{
    $real = realpath($scriptPath) ?: $scriptPath;

    register_shutdown_function(static function () use ($real): void {
        if (function_exists('error_get_last')) {
            $last = error_get_last();
            if (is_array($last) && in_array($last['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                fwrite(STDERR, "KEEP: fatal error — not deleting {$real}\n");

                return;
            }
        }

        $code = $GLOBALS['__safehouse_oneshot_exit'] ?? 0;
        if ((int) $code !== 0) {
            fwrite(STDERR, "KEEP: non-zero exit — not deleting {$real}\n");

            return;
        }

        if (is_file($real) && @unlink($real)) {
            fwrite(STDERR, "ONESHOT: deleted {$real}\n");
        } else {
            fwrite(STDERR, "WARN: failed to delete oneshot {$real}\n");
        }
    });
}

/**
 * Call before exit(n) so shutdown knows whether to self-delete.
 */
function safehouse_ephemeral_oneshot_exit(int $code = 0): never
{
    $GLOBALS['__safehouse_oneshot_exit'] = $code;
    exit($code);
}
