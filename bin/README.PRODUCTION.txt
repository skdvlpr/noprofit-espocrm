PRODUCTION bin/ POLICY
======================

This CRM must NOT keep long-lived maintenance or packaging CLIs on production.

Allowed on the server (examples):
- refuse-production.php / ephemeral-oneshot.php libraries (if present)
- this README
- stock Espo `bin/command`

Forbidden to leave runnable on production:
- smoke-*.php, seed-*.php, migrate-*.php, setup-*.php, provision-*.php
- test-*.php, cleanup-*.php, import-*.php, export-*.php, qa-*.php
- build*.sh, packaging/, dist/*.zip (after Extension Manager install — delete ZIP)
- Any role/ACL rewrite or data-migration oneshot (if briefly present, must self-delete via ephemeral-oneshot)

Deploy rsync excludes those globs (see deploy/rsync-excludes.txt).
If you find leftovers under /var/www/safehouse-crm/bin/, quarantine or delete them.

Extension ZIPs:
1. Build only on local/CI (`bin/build*.sh` → dist/).
2. Upload → Extension Manager install → Rebuild.
3. Delete the ZIP from the server immediately after install unpacks files.

Production changes:
1. Prefer code deploy + php command.php rebuild (or ZIP upgrade + rebuild).
2. One-shots only with explicit human approval for the exact command;
   MUST use bin/lib/ephemeral-oneshot.php and self-delete on success (no keep flag).
3. Never mass-overwrite Role ACL / create test users on production via long-lived CLIs.
