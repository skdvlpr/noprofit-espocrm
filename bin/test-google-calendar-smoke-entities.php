<?php

/**
 * E2E Google Calendar test ONLY on smoke entities (GCalSmoke*).
 * See bin/provision-gcal-smoke-entities.php for setup.
 *
 * Usage:
 *   ddev exec php bin/test-google-calendar-smoke-entities.php
 */

declare(strict_types=1);

$_SERVER['GCAL_SMOKE_TEST_ONLY'] = '1';

require __DIR__ . '/test-google-calendar-all-entities.php';
