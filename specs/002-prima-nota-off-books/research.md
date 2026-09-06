# Research: Prima Nota Off-Books Entries

**Feature**: `002-prima-nota-off-books`  
**Date**: 2026-09-06

## Docs freshness

- **Decision**: Pull `~/safehouse/espocrm-documentation` before plan (constitution freshness duty).
- **Evidence**: Fast-forward to `764af58c` (2026-09-05), remote https://github.com/espocrm/documentation/.
- **Alternatives**: Online-only — fallback only.

## R1 — Hybrid fields (enum + exclude bool)

- **Decision**: Add enum option **`DonorPocket`** on existing `donationPaymentProvider`. Add bool **`excludeFromDigitalReports`**, `default: false`, `readOnly: true`, `audited: true`.
- **Rationale**: Spec hybrid. Enum is for people; bool is the reporting contract (FR-009). Read-only so staff cannot fight Formula (v1: no independent override). Boolean + Enum are native field types.
- **Citations**: local `~/safehouse/espocrm-documentation/docs/administration/fields.md` + https://docs.espocrm.com/administration/fields/; entityDefs `~/safehouse/espocrm-documentation/docs/development/metadata/entity-defs.md` + https://docs.espocrm.com/development/metadata/entity-defs/. Docs note: a new field default is applied by the DB to **existing** rows.
- **Alternatives considered**: New entity — rejected (same movement). Include-in-reports default true — rejected (spec: exclude, not include). Staff-editable checkbox — rejected (spec v1 auto-only).

## R2 — Auto-set exclude via Formula

- **Decision**: Append to `metadata/formula/PrimaNota.json` `beforeSaveCustomScript`:

  ```
  if (donationPaymentProvider == 'Cash' || donationPaymentProvider == 'DonorPocket') {
      excludeFromDigitalReports = true;
  } else {
      excludeFromDigitalReports = false;
  }
  ```

- **Rationale**: Constitution I — Formula before-save is the native place (entity-manager “Before-save custom script”). Operators `||`, `==`, `if/else` are documented.
- **Citations**: `~/safehouse/espocrm-documentation/docs/administration/formula.md` + https://docs.espocrm.com/administration/formula/; entity-manager https://docs.espocrm.com/administration/entity-manager/.
- **Alternatives**: PHP hook duplicating Formula — extra surface. Workflow — not needed for always-on save rule.

## R3 — Digital totals filter

- **Decision**: `PrimaNotaStatsProvider::bankChannelWhere()` becomes “not excluded”: `excludeFromDigitalReports != true` **or** null (legacy). Keep method name as a compatibility alias; document the contract in `contracts/digital-totals.md`. **Stop** using “provider != Cash” as the sole filter (FR-005).
- **Rationale**: One sentence for future modules (SC-005). Status rules unchanged (`incomeCountedWhere` / `plannedCountedWhere`).
- **Alternatives**: Dual filter (exclude AND not Cash) forever — safer during rollout but violates FR-009 letter. Chosen: exclude-only **after** Cash backfill (R4).

## R4 — Existing Contanti rows (FR-008)

- **Decision**: Rebuild action `BackfillPrimaNotaDigitalExclude` updates `PrimaNota` where provider is `Cash` or `DonorPocket` and exclude is not true. Registered in `metadata/app/rebuild.json`. Runs on every rebuild (idempotent). Uses ORM `UpdateBuilder` (no per-row hooks/Formula).
- **Rationale**: Formula does not run on existing rows. Default `false` would otherwise make Cash **start counting**. Production CI already runs `php command.php rebuild` after rsync.
- **Citations**: rebuild/commands `~/safehouse/espocrm-documentation/docs/administration/commands.md` + https://docs.espocrm.com/administration/commands/; rebuild actions already used in this module (`BumpAppTimestamp`, etc.).
- **Alternatives**: One-shot SSH SQL — not repeatable. API mass-update of all Cash — unnecessary if rebuild runs.

## R5 — Platform immutability vs retag

- **Decision**: `ProtectDonationPaymentProvider` still blocks **Stripe** create (non-API) and **any change involving Stripe**. Non-Stripe platforms **may** change after create (BankTransfer → DonorPocket, DonorPocket → BankTransfer). UI `clientDefs` currently marks `donationPaymentProvider` readOnly whenever `id` is set — change readOnly to “provider == Stripe” so US1.3 works in the form.
- **Rationale**: FR-010 and US1.3 require changing platform on existing non-Stripe rows. Stripe ingest remains locked.
- **Alternatives**: Skip hooks on API only — weaker, UI still stuck. SKIP_ALL one-shot — not a product path.

## R6 — Production apply order

- **Decision**: Implement locally → PHPUnit → commit → **push `main`** (user asked; CI deploys only on `main`) → wait for GitHub Actions `deploy` (rebuild is in the workflow) → verify metadata on prod (`GET /api/v1/Metadata?key=entityDefs.PrimaNota`) → `PUT /api/v1/PrimaNota/{id}` with enum **key** `DonorPocket` for the two Metro ids. Do not send the read-only bool (Formula sets it).
- **Ids**: `6a931f64acfeb45c6` (€61.56, 2026-08-28), `6a8c52804555b63c2` (€36.42, 2026-08-24).
- **MCP mapping**: `espo.get` / `espo.update` (explore-espo-endpoints Workflow D/H). Enum keys not labels. Capture `X-Status-Reason` on 400.
- **Alternatives**: Tag before deploy — 400 invalid enum / unknown field.

## R7 — i18n

- **Decision**: IT label for enum: `Dalla tasca o c/c donatore`. Bool: `Escludi dai report digitali`. Tooltips ASCII-only in `it_IT/PrimaNota.json` (existing smoke forbids non-ASCII).
- **Rationale**: Spec FR-001/FR-011; smoke `IT PrimaNota i18n is ASCII-only`.

## R8 — Layout / list

- **Decision**: Show `excludeFromDigitalReports` on detail + detailSmall next to platform; add to filters. **Do not** hide excluded rows from list (FR-007). List columns unchanged.
- **Rationale**: List already shows all movements; filter is optional for bookkeepers.

## Unresolved clarifications

None. Spec has no `[NEEDS CLARIFICATION]`.
