<?php
/**
 * Deprecated: ActivityOffer publish flow was replaced by weekly shift planning.
 *
 * Use: ddev exec php bin/smoke-shift-planning.php
 */

require __DIR__ . '/lib/refuse-production.php';

fwrite(STDERR, "DEPRECATED: use bin/smoke-shift-planning.php instead.\n");
exit(1);
