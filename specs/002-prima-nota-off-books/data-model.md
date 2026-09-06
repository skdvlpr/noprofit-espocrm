# Data Model: Prima Nota Off-Books Entries

## Entity: PrimaNota (existing)

Ledger movement. No new entity type.

### New / changed fields

| Field | Type | Default | Read-only | Notes |
|-------|------|---------|-----------|--------|
| `donationPaymentProvider` | enum | `Other` | UI: only when value is `Stripe` | **Add option** `DonorPocket` after `Cash`. Existing options unchanged: Stripe, SatispayDirect, GoFundMe, FivePerMille, BankTransfer, Cash, Other. |
| `excludeFromDigitalReports` | bool | `false` | yes (Formula-owned) | True = out of Saldo digitale / dashlets / period money totals. Audited. |

### Formula (before save)

| Provider | `excludeFromDigitalReports` |
|----------|-----------------------------|
| `Cash` | `true` |
| `DonorPocket` | `true` |
| any other (including null → else branch) | `false` |

Stripe ingest stays `Stripe` → exclude `false`.

### Validation / hooks

- Manual create still cannot set `Stripe` (API ingest can).
- After create: changing platform **to or from Stripe** still `BadRequest` (`platformImmutable`).
- After create: non-Stripe ↔ non-Stripe (including `DonorPocket`) is allowed so Formula can flip exclude.

### Relationships

Unchanged (subject/beneficiary link-parent, teams, assigned user).

### State: payment status (unchanged)

Digital **realised** totals: `paymentStatus` Inviato **or** null, **and** not excluded.  
Digital **planned**: `Planned` **and** not excluded.  
Cancelled / Refunded / Disputed / Problematic: out (existing).

### Migration

1. Rebuild adds column `exclude_from_digital_reports` default false (all existing rows).
2. RebuildAction sets true where `donationPaymentProvider` in (`Cash`, `DonorPocket`).
3. Production: two Metro rows `BankTransfer` → `DonorPocket` via PUT (Formula sets exclude true).

### Production rows (FR-010)

| id | Date | Amount | From | To |
|----|------|--------|------|-----|
| `6a931f64acfeb45c6` | 2026-08-28 | 61.56 | BankTransfer | DonorPocket |
| `6a8c52804555b63c2` | 2026-08-24 | 36.42 | BankTransfer | DonorPocket |
