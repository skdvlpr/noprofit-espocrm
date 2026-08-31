<!--
Sync Impact Report
- Version change: 1.1.0 → 1.2.0
- Modified principles:
  - VII. Safe online deploy (prod = Caddy + auto TLS; cross-ref XVIII)
  - X. Rebuild & clear cache (MUST use DDEV locally via ddev exec)
- Added sections / principles:
  - XVIII. Local ↔ production runtime parity (DDEV vs bare metal; Caddy both sides)
- Removed sections: none
- Follow-up TODOs: none
-->

# Nonprofit EspoCRM Constitution

## Core Principles

### I. Official-docs supremacy & Native-first
Every stack decision MUST prefer EspoCRM native mechanisms (Entity Manager,
Roles, Formula, Dynamic Logic, metadata, hooks, extension packages) over custom
reinvention. Agents MUST NOT invent APIs that contradict official docs.

**Cite before deciding:**
- Local: `~/safehouse/espocrm-documentation/docs/development/modules.md`,
  `coding-practices.md`, `coding-rules.md`, `hooks.md`, `acl.md`,
  `metadata.md`, `extension-packages.md`
- Online: https://docs.espocrm.com/development/modules/,
  https://docs.espocrm.com/development/coding-practices/,
  https://docs.espocrm.com/development/hooks/,
  https://docs.espocrm.com/development/acl/,
  https://docs.espocrm.com/administration/entity-manager/,
  https://docs.espocrm.com/administration/roles-management/,
  https://docs.espocrm.com/administration/formula/

**Rationale:** Custom forks of Espo behaviour rot on upgrades; docs-backed
native paths survive Espo major versions.

### II. Extensions only — never touch core
Product work MUST ship as installable extensions for **EspoCRM 10+**, usable on
stock Espo as well as this nonprofit tree. MUST NOT edit `application/`, Espo
core, or vendored Espo that should upgrade upstream.

Layout (docs):
- Backend: `custom/Espo/Modules/{Module}/`
- Frontend: `client/custom/modules/{module-hyphen}/`
- Package: https://docs.espocrm.com/development/extension-packages/ and
  https://docs.espocrm.com/administration/extensions/

If behaviour needs NonprofitEspocrm: detect that module and branch; otherwise
behave correctly on stock Espo. Treat core and arbitrary custom entities the
same unless a documented capability check exists. Minimize cross-extension
coupling; required coupling MUST be documented with a clean dependency strategy.

### III. Spec-Driven Development lock-in
All feature work MUST go through Spec Kit (`/speckit-specify`, `/speckit-plan`,
`/speckit-tasks`, `/speckit-implement`, related commands). Leaving SDD requires
an explicit user amendment to this constitution.

**One in progress:** While a feature/spec is active (draft through implement),
agents MUST NOT switch to implementing or advancing a different feature. Prefer
finishing the current Spec Kit chain for that feature.

**Specify-ahead exception:** Starting an additional `/speckit-specify` (drafting
sibling/follow-on specs only — not plan/tasks/implement on them) is allowed when
the agent judges that early specification would materially reshape later steps
of the active work. The agent MUST state the rationale; the **user decides**
whether to authorize that extra specify. Implementation remains single-track.

### IV. Doc-backed planning
Every `/speckit-specify` and `/speckit-plan` artifact MUST cite Espo (and other
stack) documentation — local path and matching https://docs.espocrm.com/ URL —
and state *why* each non-obvious choice was made. “Because we always did” is
not a rationale.

### V. Constitution & docs beat user whim
If a request contradicts this constitution or official docs: **refuse**, explain,
propose compliant alternatives. If the user insists: implement only with a
**safe rollback path** (branch, reversible migration, no silent production
damage) and record the exception in `.specify/progress/`.

### VI. Security, secrets, PII
No secrets in git, logs, issues, chat dumps, or extension ZIPs. Prefer Espo
**App Secrets** (Administration → App Secrets).

- Local: `~/safehouse/espocrm-documentation/docs/administration/app-secrets.md`
- Online: https://docs.espocrm.com/administration/app-secrets/

Personal data MUST follow least privilege via Roles and field-level security
(https://docs.espocrm.com/administration/roles-management/,
https://docs.espocrm.com/development/acl/). Plan migration of misplaced secrets
into App Secrets. Analyze leak paths (CI logs, smoke scripts, debug, backups).
Security-sensitive actions require explicit user confirmation.

### VII. Safe online deploy
Production (`crm.safehouse.community`) is live and fronted by **Caddy with
automatic TLS/SSL**. Deploy MUST be repeatable and safe: build/test extensions
before ship; never push smokes, oneshots, or local config to prod; run
rebuild/cache per Espo commands docs after apply (on the server, not via DDEV);
DB/schema changes require a written migration plan and **explicit user
approval** before server apply.

CI/CD MUST not leak secrets; unsafe deploy patterns MUST be fixed via a
dedicated spec (do not casually edit workflows without user request). Review
gate: tests green before production deploy when CI is used.

See Principle XVIII for how local and production runtimes relate.

Commands reference:
- Local: `~/safehouse/espocrm-documentation/docs/administration/commands.md`
- Online: https://docs.espocrm.com/administration/commands/

### VIII. Git hygiene
- One feature → one branch.
- Merge only on user request.
- After merge, delete leftover branches unless the user says otherwise.
- Keep the working tree clean; never leave unfinished work uncommitted without
  asking the user where it should go.
- **No commit, no push, no CI/CD workflow edits** without explicit user request.
  The agent MAY ask for permission; only then proceed.
- Propose `.gitignore` updates; apply only on explicit request.

### IX. Builders vs release artifacts
Extension builder scripts and packaging tooling MUST NOT live as committed
release surface without user ask; prefer gitignoring builders. Built extension
ZIPs that passed tests MAY be kept when the user wants release artifacts.
Always ask before building. Test builds before any ship discussion.

### X. Rebuild & clear cache
After code or metadata changes, rebuild + clear cache in the correct
environment, citing https://docs.espocrm.com/administration/commands/
(`php rebuild.php`, `php clear_cache.php`, `bin/command rebuild`):

- **Local:** MUST run inside **DDEV** (`ddev exec php …` / project helpers).
  Host PHP outside DDEV is forbidden for this project.
- **Production:** run on the server after approved deploy (no DDEV).

### XI. Schema / metadata migrations
Any DB or metadata shape change: assess migration need every time; propose a
safe migrate-on-server procedure; never assume “just rebuild” is enough for
production data. Hard rebuild (`bin/command rebuild --hard`) is destructive —
backup first (commands docs).

### XII. Reworking legacy features
Existing custom code is not sacred. Prefer production-ready, non-duplicative
design aligned with official docs. Propose rethink/rewrite when current design
fights Espo. Always cite docs.

### XIII. New technology
When introducing libraries or frameworks: justify vs Espo-native options; list
alternatives; cite sources. Prefer zero new surface when native covers the need.

### XIV. Tests
Custom code MUST meet Espo-appropriate test expectations (project PHPUnit unit +
integration on isolated `db_test`; align with
https://docs.espocrm.com/development/tests/). Local PHPUnit/integration MUST
run via DDEV (Principle XVIII). Smokes are temporary probes, not a long-term
substitute for proper tests. Never weaken tests to greenwash broken behaviour.

### XV. Agents & models
Default Cursor agent runs in **Auto**. For complex research, architecture, or
hard implementation slices, the agent MUST launch subagents with stronger or
more suitable models. On `/speckit-tasks`, score each task complexity **1–10**
and pick models from those available. If the preferred model is unavailable
(limits/tokens), **ask the user** before downgrading; if the user refuses, mark
the task **blocked** until limits refresh — do not silently ship worse-quality
work.

### XVI. Progress logging
Append handoffs to `.specify/progress/` (English, handoff-ready: state, files,
verification, blockers, next steps). Notion is **retired** for executor logs.
Legacy critical extracts live only in `.specify/progress_old/`.

### XVII. User communication
Chat with the user in **Russian**. Repository artifacts (constitution, specs,
progress, code comments) in **English**. Be direct; confirm before
security-sensitive or irreversible actions.

**Next Actions footer (mandatory):** Every substantive agent turn that finishes
a Spec Kit step, governance update, or implementation milestone MUST end with a
short **Next Actions** section listing realistic next options (e.g. continue
current chain `/speckit-plan` → `/speckit-tasks` → `/speckit-implement`,
`/speckit-clarify`, append progress, ask to commit, or — only under Principle
III’s specify-ahead exception — draft a related `/speckit-specify`). Do not
silently jump to a different feature’s implement path.

### XVIII. Local ↔ production runtime parity
Local and production application stacks are intended to match **as closely as
practical**. The **primary intentional difference** is **DDEV**: local work
runs inside DDEV; production does not.

**Both environments use Caddy** as the HTTP front door with TLS:

- **Local:** Caddy runs **inside DDEV** (project router / HTTPS as configured
  by DDEV). Agents MUST treat `ddev` URLs and `ddev exec` as the local path.
- **Production:** Caddy on the host with **automatic SSL** (e.g.
  `crm.safehouse.community`).

**MUST:**

- Use DDEV for all local PHP / Espo / Composer / PHPUnit / rebuild work on this
  and the user’s similar PHP (e.g. Laravel) projects — not optional; no host-PHP
  fallback for project commands.
- Never treat production as a local substitute; never SSH/run maintenance on
  prod without explicit approval for that exact command (Principle VII).
- Prefer configurations and docs that keep local≈prod except for DDEV wrapping
  and environment-specific secrets/config (not committed).

**Rationale:** Parity reduces “works on my machine” drift; DDEV standardizes
local PHP+Caddy without inventing a second stack.

## Documentation & Stack

**Primary offline docs:** `~/safehouse/espocrm-documentation`
(git remote MUST remain `https://github.com/espocrm/documentation/`).

**Freshness duty:** At least once per calendar week, and at the start of any
session that touches Espo APIs/metadata/extensions if the last pull is older
than ~7 days:

```bash
cd ~/safehouse/espocrm-documentation && git fetch origin && git pull --ff-only
```

If pull fails: report why; fall back to https://docs.espocrm.com/; do not
silently use stale local pages when online is available. Prefer citing **both**
local path and matching online URL.

**In-scope custom modules (inventory; audit is a separate spec):**
NonprofitEspocrm, GoogleIntegration, WorkflowEngine, BugTracker,
SafehouseAuroraThemes — under `custom/Espo/Modules/` (+ matching
`client/custom/modules/` where applicable).

**Slim agent prefs:** `AGENTS.md` points here; it MUST NOT duplicate this
constitution or Espo tutorials.

## Development Workflow

1. Read this constitution and relevant Espo docs (local first).
2. Ensure **DDEV** is up for local PHP work (Principle XVIII).
3. One active Spec Kit feature at a time: specify → plan → tasks → implement
   (Principle III; specify-ahead only with user OK).
4. End each milestone reply with **Next Actions** (Principle XVII).
5. Implement only in extensions; native-first; cite docs in artifacts.
6. Rebuild + clear cache via `ddev exec` locally (Principles X, XVIII).
7. Add/extend PHPUnit for behaviour changes (Principle XIV) under DDEV.
8. Append `.specify/progress/` handoff; ask before commit/push/CI edits.
9. Production apply only with explicit user approval and a migration plan when
   schema/data shape changes (Caddy host, no DDEV).

## Governance

This constitution supersedes informal habits and any leftover rulebook prose.
Amendments require: (1) user request or Spec Kit constitution command,
(2) semantic version bump, (3) Sync Impact Report in the HTML comment at the
top of this file, (4) progress note in `.specify/progress/`.

**Versioning:**
- MAJOR — remove/redefine non-negotiable principles incompatibly
- MINOR — add or materially expand a principle/section
- PATCH — clarifications, wording, non-semantic refinements

Compliance: every `/speckit-*` and implementation session MUST verify work
against Principles I–XVIII. Violations are defects. Runtime guidance for agents
is this file + official Espo docs + active specs under `.specify/` / `specs/` —
not a second AGENTS bible.

**Version**: 1.2.0 | **Ratified**: 2026-08-31 | **Last Amended**: 2026-08-31
