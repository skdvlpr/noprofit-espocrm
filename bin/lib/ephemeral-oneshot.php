<?php

/**
 * Helpers for ephemeral one-shot CRM scripts (local or explicitly approved).
 *
 * Usage at top of a disposable script:
 *   require __DIR__ . '/lib/refuse-production.php';
 *   require __DIR__ . '/lib/ephemeral-oneshot.php';
 *   safehouse_ephemeral_oneshot_register(__FILE__);
 *
 * After successful completion the script file deletes itself unless
 * SAFEHOUSE_KEEP_ONESHOT=1 is set (debug only).
 */

declare(strict_types=1);

function safehouse_ephemeral_oneshot_register(string $scriptPath): void
{
    $real = realpath($scriptPath) ?: $scriptPath;

    register_shutdown_function(static function () use ($real): void {
        if (getenv('SAFEHOUSE_KEEP_ONESHOT') === '1') {
            fwrite(STDERR, "KEEP: SAFEHOUSE_KEEP_ONESHOT=1 — not deleting {$real}\n");

            return;
        }

        $status = function_exists('http_response_code') ? null : null;
        $exit = 0;
        if (function_exists('error_get_last')) {
            $last = error_get_last();
            if (is_array($last) && in_array($last['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                fwrite(STDERR, "KEEP: fatal error — not deleting {$real}\n");

                return;
            }
        }

        // Only delete when process ends with success (0). PHP does not expose
        // exit code in shutdown reliably before 8.4; track via global.
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
