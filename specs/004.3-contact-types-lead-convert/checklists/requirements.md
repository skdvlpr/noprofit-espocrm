# Specification Quality Checklist: Contact multi-type and Lead convert with CRM user

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-09-18
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

- Amendment `004.3` of the 004 contact-first family (constitution III
  `NNN.K`). Specify-ahead authorized by owner after 004.2 UAT Pass and
  production transfer of existing rows.
- Validation 2026-09-18: first draft cited a technical planning path in
  Assumptions; removed. Remaining Espo documentation URLs in the spec
  header are constitution Principle I/IV cites, not product HOW.
- Validation 2026-09-21: added FR-015 / SC-009 / US1 scenario 7 after
  create-form Volunteer/Employee type-clear hotfix (owner Pass).
- Validation 2026-09-21 (later): Lead is type-only (FR-007); convert
  copies one or both types (FR-008); review must reopen after Cancel
  (FR-009). Employee added on Lead.
- Validation 2026-09-21 (convert UAT): FR-016 empty `contractType` on
  Volunteer convert; FR-017 User Is Active off → Contact Inactive.
- No `[NEEDS CLARIFICATION]` markers. Defaults: Generic Lead → Contact
  Other; create-user default off on Generic; Member create-user uses the
  004.2 review; Volunteer/Employee remain admin-only.
- Owner 2026-09-18 plan input: Contact email uniqueness is **hard**,
  same family as User (FR-014 / SC-008). Native skippable duplicate
  dialog alone is not enough.
