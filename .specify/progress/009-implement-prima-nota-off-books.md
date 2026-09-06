# 009 — Implement Prima Nota off-books

**Date:** 2026-09-06  
**Agent:** Cursor Auto (`/speckit-implement`)

## State

- Feature implemented in NonprofitEspocrm: `DonorPocket` enum, `excludeFromDigitalReports` bool + Formula, reporting filter, Cash backfill RebuildAction, non-Stripe platform change allowed.
- Local: `ddev exec php command.php rebuild` OK; PHPStan OK; PHPUnit PrimaNota + ReportingStats **23 tests, 62 assertions**.
- T016 (prod PUT of two Metro ids) **blocked on auto-deploy** after push to `main`.

## Files

- `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/PrimaNota.json`
- `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/formula/PrimaNota.json`
- `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/PrimaNota.json`
- `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/rebuild.json`
- `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/PrimaNota/{detail,detailSmall,filters}.json`
- `custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/{it_IT,en_US}/PrimaNota.json`
- `custom/Espo/Modules/NonprofitEspocrm/Tools/Reporting/PrimaNotaStatsProvider.php`
- `custom/Espo/Modules/NonprofitEspocrm/Hooks/PrimaNota/ProtectDonationPaymentProvider.php`
- `custom/Espo/Modules/NonprofitEspocrm/Core/Rebuild/BackfillPrimaNotaDigitalExclude.php`
- `tests/integration/Espo/Modules/NonprofitEspocrm/PrimaNotaTest.php`
- `tests/integration/Espo/Modules/NonprofitEspocrm/ReportingStatsTest.php`
- `bin/smoke-prima-nota-stripe-commission.php`
- `specs/002-prima-nota-off-books/*`

## Verification

- PHPStan: no errors
- PHPUnit integration (DDEV, `TEST_DATABASE_*` via `bin/lib/test-database-env.sh`): OK
- Did not run host PHP

## Blockers

- Production tagging waits for GitHub Actions `CI` deploy on `main` (rebuild is in the workflow). Then PUT `6a931f64acfeb45c6` and `6a8c52804555b63c2`.

## Next steps

1. Commit feature (exclude unrelated constitution/AGENTS dirty files) and `git push origin main` (user asked).
2. Wait for deploy job success.
3. Metadata GET then two PUTs; do not log API key.
