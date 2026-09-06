# Quickstart: Prima Nota off-books validation

## Prerequisites

- DDEV up in repo root (`nonprofit-espocrm`)
- Docs: behaviour from official Espo docs; this file is a run guide only

## Local

```bash
ddev exec php command.php rebuild
ddev exec bash bin/test-build.sh
ddev exec vendor/bin/phpunit tests/integration/Espo/Modules/NonprofitEspocrm/PrimaNotaTest.php
ddev exec vendor/bin/phpunit tests/integration/Espo/Modules/NonprofitEspocrm/ReportingStatsTest.php
```

Expected: all new tests green (DonorPocket auto-exclude, Cash still excluded, BankTransfer counted, non-Stripe platform change allowed, Stripe still locked).

## UI (DDEV site)

1. Create Expense, platform **Dalla tasca o c/c donatore**, Inviato, known amount.
2. List shows the row; Saldo digitale unchanged vs before save.
3. Change platform to **Bonifico bancario**; exclude turns off; digital totals include the amount (status rules permitting).

## Production (after push to `main` and green deploy)

1. Wait for GitHub Actions workflow **CI** → job **deploy** success (rsync + `php command.php rebuild`).
2. `GET /api/v1/Metadata?key=entityDefs.PrimaNota` — see `DonorPocket` and `excludeFromDigitalReports` ([contracts/rest-prima-nota.md](./contracts/rest-prima-nota.md)).
3. PUT the two Metro ids to `DonorPocket`.
4. GET both ids; list still shows them; digital saldo no longer includes €97.98 combined outflow.
