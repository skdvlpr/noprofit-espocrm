# Feature Specification: Prima Nota Off-Books Entries

**Feature Branch**: `002-prima-nota-off-books`

**Created**: 2026-09-06

**Status**: Draft

**Input**: User description: "Prima Nota must record all organisation movements including purchases paid by a person or third party whose money never passed through the organisation account. Those rows must stay visible in the ledger list, but must not change Saldo digitale or dashlet money totals. Hybrid: new payment-platform option (IT: Dalla tasca o c/c donatore) plus an exclude-from-reports flag (not include). Two existing Metro expenses must be marked via API. Future reporting modules must have almost no way to count these by accident."

## Clarifications

### Session 2026-09-06

- Q: Hybrid of explicit reporting flag + dedicated payment platform? → A: Yes. New platform label (IT) **Dalla tasca o c/c donatore** plus a boolean **exclude from reports** (exclude, not include), with Italian wording.
- Q: Existing two Metro rows? → A: In scope — set payment platform to the new option and exclude = true (via API user after implement).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Record a purchase paid privately without changing digital totals (Priority: P1)

A bookkeeper records that a volunteer or donor bought supplies (e.g. Metro containers) for the organisation. The invoice/story must appear in Prima Nota like any other movement. **Saldo digitale**, month/year/day totals, and ledger dashlets must **not** change because the organisation bank/digital channel never moved.

**Why this priority**: This is the missing case; list already works; totals currently overstate digital outflow.

**Independent Test**: Create one such movement of a known amount; list shows it; digital saldo and dashlets stay unchanged versus before that save.

**Acceptance Scenarios**:

1. **Given** a user with Prima Nota create rights, **When** they save an expense with payment platform **Dalla tasca o c/c donatore**, **Then** the row appears in the list with amount and parties, and **exclude from digital reports** is on.
2. **Given** that row is saved, **When** they open dashlets / period totals / Saldo digitale, **Then** those monetary indicators ignore this row (same as today’s Contanti exclusion, plus this new case).
3. **Given** they later change the platform to a normal digital/bank platform, **Then** exclude turns off and the amount **does** enter digital totals (subject to existing Inviato / Planned rules).

---

### User Story 2 - Future reporting cannot “forget” the special platform (Priority: P1)

A later reporting or analytics feature reads Prima Nota money fields. It must have **one** obvious rule: if exclude-from-digital-reports is on, do not add the amount to digital/org-account indicators. It must **not** need a growing list of magic payment-platform values.

**Why this priority**: User asked for minimum chance of error in other modules.

**Independent Test**: A second reader (human or spec) can state the digital-total rule in one sentence using only the exclude flag plus existing payment status rules — without naming Cash vs the new platform.

**Acceptance Scenarios**:

1. **Given** digital totals are calculated, **When** any new dashboard or report is added later, **Then** the documented contract is: count only rows that are **not** excluded from digital reports **and** still pass today’s status rules (Inviato vs Planned vs cancelled/refunded/etc.).
2. **Given** payment platform is Contanti **or** Dalla tasca o c/c donatore, **When** the record is saved, **Then** exclude-from-digital-reports is **automatically on** so staff cannot leave a “private pay” row counting by forgetting a second click.
3. **Given** payment platform is a normal digital/bank option (e.g. Stripe, bonifico), **When** the record is saved, **Then** exclude is **automatically off**, unless the user is recording a non-channel case (they use the dedicated platform instead).

---

### User Story 3 - Fix the two known Metro rows (Priority: P2)

The two August 2026 Metro purchases paid by Mauro Latona (€61.56) and Clotilde Fierro (€36.42) today use bonifico + Donazione + Inviato, so they **subtract** from Saldo digitale. After this feature they must be tagged as donor-pocket / exclude so they remain in the list but drop out of digital figures.

**Why this priority**: Concrete production mismatch; smaller than the general data model.

**Independent Test**: After tagging, those two ids still exist in the list; digital saldo is higher by their combined net outflow versus before tagging (all else equal).

**Acceptance Scenarios**:

1. **Given** the two production records identified in research, **When** this feature is applied, **Then** each has the new payment platform and exclude-from-digital-reports on.
2. **Given** those two rows, **When** a user opens the list, **Then** they still see description, dates, amounts, and parties.

---

### Edge Cases

- What if someone uses Contanti for organisation cash? Exclude stays on (same as today: Contanti is already out of Saldo digitale). Existing Contanti rows must keep not counting after the switch to the exclude flag as the **only** digital filter.
- What if exclude is on but status is Inviato? Still **out** of digital totals (exclude wins for digital indicators).
- What if a real bank expense is mis-tagged as Dalla tasca…? Totals would understate; users correct by changing platform (auto clears exclude).
- What if Stripe ingest creates a row? Stripe remains a digital channel; exclude stays off.
- Soft-deleted rows stay out of totals as today.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Prima Nota MUST offer a payment-platform option whose Italian label is **Dalla tasca o c/c donatore** (English label equivalent: paid from the donor/person’s own pocket or their own account — not the organisation’s digital/bank channel).
- **FR-002**: Prima Nota MUST offer a boolean **exclude from digital reports** (Italian label required, meaning: do not count in Saldo digitale and dashlet/period money figures). Default for new ordinary rows: **not excluded**.
- **FR-003**: Choosing **Dalla tasca o c/c donatore** or **Contanti** MUST automatically set exclude-from-digital-reports **on** at save (staff MUST NOT need a second manual step for the normal case).
- **FR-004**: Choosing any other payment platform MUST automatically set exclude-from-digital-reports **off** at save.
- **FR-005**: **Saldo digitale**, Prima Nota list/period totals, and Prima Nota ledger dashlets MUST ignore rows where exclude-from-digital-reports is on. They MUST NOT rely on “is Contanti?” alone after this feature.
- **FR-006**: Existing payment-status rules stay: Inviato (and legacy empty status) count in realised digital totals; Planned stays in forecast; Cancelled / Refunded / Disputed / Problematic stay out of those totals.
- **FR-007**: Excluded rows MUST remain fully visible and filterable in the Prima Nota list (not hidden, not cancelled).
- **FR-008**: All existing rows that today are excluded from digital totals solely because platform is Contanti MUST remain excluded (exclude flag on after rollout). All other currently counted digital rows MUST remain counted (exclude flag off), except the two Metro rows in FR-010.
- **FR-009**: Any future money aggregation for “organisation digital/bank position” in this product MUST use the exclude flag (plus status rules), not an ad-hoc list of platform names.
- **FR-010**: The two known expenses (2026-08-28 Mauro / Metro €61.56 and 2026-08-24 Clotilde / Metro €36.42) MUST be set to the new platform and exclude on, using the API user, as part of delivery of this feature (production apply only with explicit user approval at implement time).
- **FR-011**: Italian and English UI labels and a short tooltip MUST explain: list = all movements; Saldo digitale / dashlets = only money that went through the organisation’s digital/bank channel.

### Key Entities

- **Prima Nota movement**: A ledger row with type (income/expense), amounts, date, parties, payment platform, payment status, internal classification, and **exclude from digital reports**.
- **Digital position indicators**: Saldo digitale, day/month/year money totals, ledger dashlets — organisation digital/bank channel only.
- **Off-books / donor-pocket movement**: Same entity; platform = Dalla tasca o c/c donatore; exclude on; documented activity, no digital cash impact.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A bookkeeper can record a privately paid expense in under 2 minutes using the new platform option, without a second obscure setting for the normal path (exclude turns on by itself).
- **SC-002**: After the two Metro rows are tagged, Saldo digitale no longer includes their combined €97.98 outflow; both rows still appear in the list.
- **SC-003**: Creating a new Dalla tasca… expense of amount X leaves Saldo digitale and dashlet realised totals unchanged (±€0.00 vs immediately before save), while the list count increases by 1.
- **SC-004**: A Contanti row that was already out of Saldo digitale remains out after rollout (no regression of today’s cash exclusion).
- **SC-005**: A reviewer can describe the digital-total rule in one sentence using **exclude from digital reports** + payment status — without listing platform names.
- **SC-006**: 100% of Prima Nota digital money widgets in this product (saldo, period banners, ledger dashlets) obey FR-005.

## Assumptions

- “Digital reports” here means organisation **digital/bank position** (Saldo digitale and related dashlets/period sums), not deleting rows from the ledger and not hiding them from accountants.
- Existing Inviato / Planned / cancelled-family behaviour is kept.
- Hybrid is intentional: platform is for **people**; exclude flag is the **contract for every total**.
- Automatic exclude on/off from platform (FR-003/FR-004) is the anti-mistake mechanism; independent override of the checkbox against the platform is **out of scope** for v1.
- Production tagging of the two rows requires explicit user approval at implement/deploy time.
- Feature `001-custom-code-audit` remains the prior spec; this specify-ahead was authorised by the user for this ledger gap.

## Documentation Citations *(constitution IV)*

| Topic | Local | Online | Why this choice |
|-------|-------|--------|-----------------|
| Enum + Boolean fields | `~/safehouse/espocrm-documentation/docs/administration/fields.md` | https://docs.espocrm.com/administration/fields/ | Native field types; no custom entity |
| Entity Manager / Formula | `~/safehouse/espocrm-documentation/docs/administration/entity-manager.md`, `formula.md` | https://docs.espocrm.com/administration/entity-manager/, https://docs.espocrm.com/administration/formula/ | Auto-set exclude from platform without a new API |
| Dynamic Logic (labels/visibility if needed) | `~/safehouse/espocrm-documentation/docs/administration/dynamic-logic.md` | https://docs.espocrm.com/administration/dynamic-logic/ | Keep the form understandable |
| Extensions only | `~/safehouse/espocrm-documentation/docs/development/modules.md` | https://docs.espocrm.com/development/modules/ | Changes stay in the nonprofit extension |

**Why this shape:** Today digital totals already treat Contanti as a **channel**, not as “fake Cancelled”. A dedicated platform names the real-world case; a single exclude flag is what reporting must read so a future module cannot “forget OutOfPocket in a where-clause”.
