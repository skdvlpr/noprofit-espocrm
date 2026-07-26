<?php

/**
 * Hard block for any CRM bin script when running on production.
 *
 * Include as the first executable line after `<?php` in every `bin/*.php` script:
 *   require __DIR__ . '/lib/refuse-production.php';
 *
 * From `bin/lib/*.php` helpers that bootstrap work:
 *   require __DIR__ . '/refuse-production.php';
 *
 * There is intentionally NO bypass flag. Production changes must be:
 *   - ephemeral one-shot scripts (self-delete after success), run only with
 *     explicit human approval, OR
 *   - code deployed via normal release (metadata/PHP modules), not ad-hoc CLI.
 */

declare(strict_types=1);

(static function (): void {
    // This file lives in bin/lib/ → project root is ../..
    $root = dirname(__DIR__, 1); // bin
    if (basename($root) === 'bin') {
        $root = dirname($root);
    } elseif (basename($root) === 'lib') {
        $root = dirname($root, 2);
    }

    $reasons = [];

    $realRoot = realpath($root) ?: $root;
    if (str_starts_with($realRoot, '/var/www/safehouse-crm')) {
        $reasons[] = "filesystem path is production CRM ({$realRoot})";
    }

    $configFile = $realRoot . '/data/config.php';
    if (is_file($configFile)) {
        /** @var mixed $config */
        $config = include $configFile;
        if (is_array($config)) {
            $siteUrl = (string) ($config['siteUrl'] ?? '');
            if ($siteUrl !== '' && preg_match('/(^|\.)safehouse\.community/i', $siteUrl)) {
                $reasons[] = "siteUrl is production ({$siteUrl})";
            }
        }
    }

    if ($reasons === []) {
        return;
    }

    $script = $_SERVER['SCRIPT_FILENAME'] ?? ($_SERVER['argv'][0] ?? 'bin-script');
    fwrite(STDERR, "REFUSED: blocked on production.\n");
    fwrite(STDERR, '  script: ' . $script . "\n");
    foreach ($reasons as $reason) {
        fwrite(STDERR, '  reason: ' . $reason . "\n");
    }
    fwrite(STDERR, "  policy: run smokes/seeds/migrations only on local DDEV.\n");
    fwrite(STDERR, "  one-shots: write ephemeral script, get explicit approval, self-delete after success.\n");
    exit(78);
})();
