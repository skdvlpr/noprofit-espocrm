# Data Model: Custom Code Compliance Audit

**Feature**: `001-custom-code-audit`  
**Date**: 2026-08-31

Logical entities for the audit report (file-based; not Espo ORM entities).

## ComplianceFinding

A single ranked compliance issue.

| Field | Type | Rules |
|-------|------|-------|
| id | string | Stable within report, e.g. `F-001` |
| title | string | Short; suitable as future `/speckit-specify` title |
| severity | enum | `Critical` \| `High` \| `Medium` \| `Low` (see research R3) |
| module | string \| list | One or more of the five modules, or `cross-cutting` |
| category | enum | `secrets` \| `acl-pii` \| `extension-boundary` \| `native-first` \| `performance` \| `packaging` \| `other` |
| impact | string | Plain-language business/security impact |
| evidence | string | Path(s) or area; no secret values |
| expectation | string | What official docs/constitution require |
| citation_local | string | Path under `~/safehouse/espocrm-documentation/...` |
| citation_online | string | `https://docs.espocrm.com/...` URL |
| action_type | enum | `fix` \| `migrate-secret` \| `rewrite` \| `accept-risk` \| `open-follow-on-spec` \| `blocked-needs-prod` |
| backlog_rank | int \| null | Position in ordered remediation backlog (1 = next) |
| notes | string | Optional methodology / limits |

**Validation**: Critical/High MUST have both citations and impact (SC-003).  
**Transitions**: draft → included-in-report → (later) mapped-to-spec (outside this feature).

## ModuleSummary

| Field | Type | Rules |
|-------|------|-------|
| module_name | string | Exact inventory name |
| backend_path | string | e.g. `custom/Espo/Modules/GoogleIntegration/` |
| frontend_path | string \| `none` | Matching client tree or explicit none |
| coupling | enum | `standalone-safe` \| `soft-depends` \| `hard-depends` |
| summary_status | enum | `pass` \| `findings` \| `not-applicable` |
| finding_ids | list | Related `ComplianceFinding.id` |
| notes | string | Coverage limits for this module |

**Validation**: All five modules required (SC-001 / FR-001).

## SecretLocationCandidate

| Field | Type | Rules |
|-------|------|-------|
| id | string | e.g. `S-001` |
| kind | string | e.g. OAuth client secret, API key, VAPID private key |
| location | string | Path / config key / entity field — **no value** |
| in_git | bool | Suspected committed |
| in_package | bool | Suspected in extension ZIP |
| recommended_store | string | Prefer Espo App Secrets per docs |
| related_finding_id | string \| null | Link to `ComplianceFinding` |

## RemediationBacklogItem

| Field | Type | Rules |
|-------|------|-------|
| rank | int | 1..N unique |
| finding_id | string | FK to `ComplianceFinding` |
| proposed_spec_title | string | 1:1 mappable to `/speckit-specify` (SC-005) |
| depends_on | list | Other ranks or follow-on spec names |
| blocked | bool | True if needs prod approval etc. |

## ComplianceReport (aggregate)

| Field | Type | Rules |
|-------|------|-------|
| title | string | Fixed pattern: Compliance Audit Report |
| created | date | ISO |
| docs_commit | string | Espo documentation clone SHA |
| methodology | string | Inventory + risk-based deep dive |
| executive_summary | string | Short |
| module_summaries | list | ModuleSummary × 5 |
| security_section | object | SecretLocationCandidates + ACL/PII narrative |
| extension_map | object | Coupling table |
| findings | list | ComplianceFinding |
| backlog | list | RemediationBacklogItem ordered |
| out_of_scope_notes | string | bin/CI deferred, etc. |
| coverage_limits | string | FR-011 |

## Relationships

```text
ComplianceReport
  ├── ModuleSummary (1..5)
  ├── SecretLocationCandidate (0..*)
  ├── ComplianceFinding (0..*)
  └── RemediationBacklogItem (0..*) ──► ComplianceFinding
```
