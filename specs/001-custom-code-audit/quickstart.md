# Quickstart: Validate Compliance Audit Deliverable

**Feature**: `001-custom-code-audit`  
**Purpose**: Prove the audit report meets spec success criteria without implementing remediations.

## Prerequisites

- Repo root: `nonprofit-espocrm`
- **DDEV installed and project started** (`ddev start`) — mandatory for any PHP/Espo CLI
- Spec artifacts present under `specs/001-custom-code-audit/`
- Local Espo docs: `~/safehouse/espocrm-documentation` (pull if stale)
- Constitution: `.specify/memory/constitution.md`
- Know prod context: **Caddy + automatic SSL** (do not probe without approval)

## Setup (docs freshness)

```bash
cd ~/safehouse/espocrm-documentation
git fetch origin && git pull --ff-only
git log -1 --format='%H %ci'
```

Record the SHA into the report metadata.

## Setup (DDEV)

```bash
cd /home/skoksharov/safehouse/nonprofit-espocrm
ddev describe   # or ddev start if not running
# All PHP/Espo commands MUST use ddev exec — never host php
```

## Run (implement phase — outline)

1. Inventory five modules under `custom/Espo/Modules/` and matching
   `client/custom/modules/` (note Aurora themes asset paths).
2. Deep-dive in order: secrets → ACL/PII → coupling → native-first → performance.
3. For metadata/CLI evidence: `ddev exec php command.php …` / rebuild as needed.
4. Write canonical report per [contracts/compliance-report.md](./contracts/compliance-report.md).
5. Append `.specify/progress/` handoff noting SC-006 awaiting user acceptance.

Do **not** run production SSH or paste secrets into files. Do **not** use host PHP.

## Validation checklist (owner / second agent)

| Check | Expected |
|-------|----------|
| SC-001 | Five module summaries present |
| SC-002 | Rank 1 backlog item is an obvious next specify title |
| SC-003 | Every Critical/High has local + online citation + impact |
| SC-004 | Security & secrets section complete or explicit none-found |
| SC-005 | ≥10 backlog rows (or all findings) are specify-titled |
| SC-007 | Methodology lists DDEV for PHP steps; notes Caddy+SSL prod |
| FR-009 | No mass fix PRs claimed as this feature’s Done |
| FR-013 | No host-PHP commands in handoff |
| R5 | No raw secrets in report |
| Contracts | Section order matches compliance-report contract |

## Example DDEV probes (optional evidence)

```bash
ddev exec php command.php rebuild
# or project helper if present:
ddev exec bash bin/dev-rebuild.sh
```

Skip probes that need unavailable services; document as coverage limit—still do
not fall back to non-DDEV PHP.

## Done for this feature

User confirms report sufficiency (SC-006) in chat or progress note. Then
remediations start as **new** Spec Kit features from the backlog—not as silent
continuations inside this audit.
