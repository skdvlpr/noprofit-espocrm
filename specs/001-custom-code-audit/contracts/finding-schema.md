# Contract: Finding Schema

**Feature**: `001-custom-code-audit`  
**Format**: Markdown subsection or table row matching [data-model.md](../data-model.md).

## Template (per finding)

```markdown
### F-NNN — {title}

| Field | Value |
|-------|-------|
| Severity | Critical \| High \| Medium \| Low |
| Module | {name or cross-cutting} |
| Category | secrets \| acl-pii \| extension-boundary \| native-first \| performance \| packaging \| other |
| Impact | {plain language} |
| Evidence | `{path or area}` (no secret values) |
| Expectation | {what docs/constitution require} |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/...` |
| Citation (online) | https://docs.espocrm.com/... |
| Action type | fix \| migrate-secret \| rewrite \| accept-risk \| open-follow-on-spec \| blocked-needs-prod |
| Backlog rank | {n or —} |
```

## Backlog row template

| Rank | Finding | Proposed `/speckit-specify` title | Blocked? |
|------|---------|-----------------------------------|----------|
| 1 | F-00N | {title} | no \| yes (reason) |

## Validation

- IDs unique within the report.
- Rank numbers unique contiguous starting at 1 for included backlog items.
- `open-follow-on-spec` items MUST name which deferred theme (tests/bin, CI, or other).
