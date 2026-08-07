PRODUCTION bin/ POLICY
======================

This CRM must NOT keep long-lived maintenance CLIs on production.

Allowed on the server (examples):
- packaging helpers used only by Extension Manager (if any)
- refuse-production.php / ephemeral-oneshot.php libraries (if present)
- this README

Forbidden to leave runnable on production:
- smoke-*.php, seed-*.php, migrate-*.php, setup-*.php, provision-*.php
- test-*.php, cleanup-*.php, import-*.php, export-*.php, qa-*.php

Deploy rsync excludes those globs (see deploy/rsync-excludes.txt).
If you find leftovers under /var/www/safehouse-crm/bin/, quarantine or delete them.

Production changes:
1. Prefer code deploy + php command.php rebuild.
2. One-shots only with explicit human approval for the exact command;
   prefer self-deleting ephemeral scripts.
3. Never mass-overwrite Role ACL / create test users on production.
