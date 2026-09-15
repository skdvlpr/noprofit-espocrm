<!--
Sync Impact Report
- Version change: 1.2.1 → 1.3.0
- Modified principles:
  - I. Official-docs supremacy & Native-first — read local clone
    `~/espocrm-documentation`; cite GitHub blob/master (not docs.espocrm.com)
  - III. Spec-Driven Development — parent-adjacent NNN.K amendments; owner
    UAT before the next main feature
  - IV. Doc-backed planning — GitHub cites in committed artifacts
  - V. Constitution & docs beat user whim — ask on doubt; do not guess
  - VI. Security — GitHub ACL/App Secrets cites; live REST must not leak keys
  - VII. Safe online deploy — keep existing CI rsync (not ZIP-only)
  - X. Rebuild — GitHub commands cite
  - XIV. Tests — keep DDEV PHPUnit for behaviour-changing custom PHP; add
    “no stupid tests / no coverage % gate / propose in tasks”
  - XV. Agents & models — owner launch-or-replace gate for advanced models
  - XVII. User communication — owner UAT chat script in Russian
  - XVIII. Stack lock — PHP 8.4, MariaDB, DDEV nginx-fpm; no FrankenPHP
  - Documentation & Stack — replaced docs.espocrm.com catalog with Read/Cite map
- Added sections / principles:
  - XIX. Native functional UI fields
  - XX. Live instance REST (explore-espo-endpoints)
  - Locked Decisions (nonprofit F-* subset; no LearnHouse / GM queue)
- Removed sections: none
- Follow-up TODOs: none
-->

# Nonprofit EspoCRM Constitution

## Core Principles

### I. Official-docs supremacy & Native-first

Every stack decision MUST prefer EspoCRM native mechanisms (Entity Manager,
Roles, Formula, Dynamic Logic, metadata, hooks, ORM, extension packages) over
custom reinvention. Agents MUST NOT invent APIs that contradict official docs.

**Read** (this turn; version-true clone; MUST NOT move, copy, or skip):

`/home/skoksharov/espocrm-documentation` (`~/espocrm-documentation`)

On every Espo decision (specify, plan, tasks, implement, CSS, API, entity,
hook, theme, formula, layout, ACL) the agent MUST **open** the matching file
under `/home/skoksharov/espocrm-documentation/docs/` **this turn**. When the
decision needs core behaviour, also read this tree’s `application/Espo/` and
`client/` (do not edit those trees — Principle II).

**Cite** in committed files (PHP, JS, CSS, tests, specs, plans, tasks,
checklists, comments; this constitution except the Read-root sentences):
GitHub, same tree as the clone (origin `https://github.com/espocrm/documentation`,
branch `master`). Mechanical map:

`/home/skoksharov/espocrm-documentation` →
`https://github.com/espocrm/documentation/blob/master`

Example: read `…/docs/development/acl.md`; cite
`https://github.com/espocrm/documentation/blob/master/docs/development/acl.md`.
Directories use `/tree/master/` instead of `/blob/master/` when pointing at a
folder.

MUST NOT:

- Commit `/home/skoksharov/espocrm-documentation` or `~/espocrm-documentation`
  paths outside this principle’s Read-root and Principle XII Read map.
- Cite `https://docs.espocrm.com` in committed artifacts (same corpus; GitHub
  path is the stable citation).
- Fetch the documentation website in preference to the local clone.
- Write, move, or copy files inside `/home/skoksharov/espocrm-documentation`.

No local file **opened** this turn → the Espo decision was **not made**.
No GitHub (or in-repo) citation in the committed artifact that needed Espo
docs → **rejected**.

The owner keeps the clone current with `git pull`. Agents MUST NOT spend turns
diffing GitHub HTML against local files. If the clone is missing, STOP and ask.

Native-first coding (open local clone this turn; cite GitHub):

- https://github.com/espocrm/documentation/blob/master/docs/development/modules.md
- https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md
- https://github.com/espocrm/documentation/blob/master/docs/development/coding-rules.md
- https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
- https://github.com/espocrm/documentation/blob/master/docs/development/metadata.md
- https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
- https://github.com/espocrm/documentation/blob/master/docs/development/extension-packages.md

MUST read structure through official ORM/Metadata (`EntityManager` defs,
`Espo\Core\Utils\Metadata`). MUST NOT hardcode SQL table names in runtime
module PHP. Prefer JSON metadata and Formula; custom PHP only when metadata
cannot express the behaviour. Style MUST match upstream Espo (DI without
Container in constructors; typed methods; throw on failure; `null` for empty;
no bool success flags).

Cursor automations: choose **Formula** (before-save / API before-save) **or**
custom PHP (Hook / Service / Job / Controller). State in the answer: path =
Formula|Code, why, and what was rejected. Formula first if expressible without
unsafe side effects; otherwise code. MUST NOT assume paid Advanced Pack BPM.

**Rationale:** Custom forks of Espo behaviour rot on upgrades; the local clone
plus GitHub cites stay version-true without website scraping.

### II. Extensions only — never touch core

Product work MUST ship as installable extensions for **EspoCRM 10+**, usable on
stock Espo as well as this nonprofit tree. MUST NOT edit `application/`, Espo
core, or vendored Espo that should upgrade upstream.

Layout (cite GitHub; read local clone):

- Backend: `custom/Espo/Modules/{Module}/`
- Frontend: `client/custom/modules/{module-hyphen}/`
- https://github.com/espocrm/documentation/blob/master/docs/development/modules.md
- https://github.com/espocrm/documentation/blob/master/docs/development/extension-packages.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/extensions.md

If behaviour needs NonprofitEspocrm: detect that module and branch; otherwise
behave correctly on stock Espo. Treat core and arbitrary custom entities the
same unless a documented capability check exists. Minimize cross-extension
coupling; required coupling MUST be documented with a clean dependency strategy.

Hook short-names MUST be unique per entity type. Duplicate module names,
namespaces, entity types, AMD prefixes, and SaveOption flags across our
modules are **forbidden** even if Espo would pick a winner.

This git root is the only write target. MUST NOT patch sibling repos or the
Espo docs clone. If a change is required outside this repo: STOP, name the
absolute path and why, wait for the owner.

Production delivery today is GitHub Actions **rsync of this tree** plus server
rebuild (Principle VII). Extensions MUST remain packable as official ZIPs;
ZIP-only ship is **not** the current prod path unless a later spec amends it.

Paid Espo packs (Intelligence, Advanced Pack Workflows/BPM/Reports, and other
paid Espo ZIPs) MUST NOT be installed unless the owner amends this
constitution. Pack docs MAY be read as analysis only.

### III. Spec-Driven Development lock-in

All feature work MUST go through Spec Kit:

`/speckit-specify` → `/speckit-plan` → `/speckit-tasks` → `/speckit-implement`

Catalog (owner-optional extras: clarify, checklist, analyze, converge):
https://github.com/github/spec-kit/blob/main/docs/quickstart.md
and https://github.com/github/spec-kit/blob/main/docs/reference/agentic-sdd.md

Leaving SDD requires an explicit user amendment to this constitution.

MUST:

- Implement **only** from the current feature `tasks.md`.
- Keep **one active spec** in this repo.
- Use main-feature directories `specs/NNN-short-name/`.
- A correction, regression fix, or owner-UAT repair for an existing main
  feature MUST use `specs/NNN.K-short-name/`, where `NNN` is the parent spec
  and `K` is the next positive amendment number (`002.1`, `002.2`, …).
  Amendments MUST stay adjacent to their parent. MUST NOT assign a distant
  global integer to a fix.
- Before implement: compliance-review this constitution and the feature
  artifacts (spec, plan, tasks).
- After implement (including hotfix implement): complete owner UAT
  (Principle XVII) before starting the next **main** feature.

**Specify-ahead exception:** Starting an additional `/speckit-specify`
(drafting sibling/follow-on specs only — not plan/tasks/implement on them) is
allowed when the agent judges that early specification would materially
reshape later steps of the active work. The agent MUST state the rationale;
the **user decides** whether to authorize that extra specify. Implementation
remains single-track.

**Owner-discretion (optional):** `/speckit-clarify`, `/speckit-checklist`,
`/speckit-analyze`, `/speckit-converge`. Run them when the owner asks, or when
the owner already opened a hole that needs that gate. MUST NOT treat a missing
optional step as a block on `/speckit-implement`. When converge appends tasks,
implement those, then converge again until it reports converged (or the owner
stops).

Hotfix: the owner MUST say hotfix (or an explicit interrupt). Capture it in a
spec (parent `NNN.K` when it repairs an existing feature), then return to the
interrupted work. MUST NOT silently open the next main feature.

### IV. Doc-backed planning

Every `/speckit-specify` and `/speckit-plan` artifact MUST cite Espo (and other
stack) documentation using the Principle I / XII map — **open the local file
this turn**, **paste the GitHub blob URL** in the committed artifact — and
state *why* each non-obvious choice was made. “Because we always did” is not
a rationale.

### V. Constitution & docs beat user whim

If a request contradicts this constitution or official docs: **refuse**,
explain, propose compliant alternatives. If the user insists: implement only
with a **safe rollback path** (branch, reversible migration, no silent
production damage) and record the exception in `.specify/progress/`.

Any fork, unknown API, docs≠this-tree code, or “A or B” MUST STOP. List
options and wait for the owner. MUST NOT pick silently. Closed Locked
Decisions below are **not** open design forks.

Legal / DPA / GDPR counsel: STOP. Operational Espo export/delete tools are in
scope; legal text is not.

### VI. Security, secrets, PII

No secrets in git, logs, issues, chat dumps, or extension ZIPs. Prefer Espo
**App Secrets** (Administration → App Secrets).

- Read: `/home/skoksharov/espocrm-documentation/docs/administration/app-secrets.md`
- Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/app-secrets.md

Personal data MUST follow least privilege via Roles and field-level security:

- https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
- https://github.com/espocrm/documentation/blob/master/docs/development/acl.md

Plan migration of misplaced secrets into App Secrets. Analyze leak paths (CI
logs, smoke scripts, debug, backups). Security-sensitive actions require
explicit user confirmation.

Incoming webhooks MUST verify signatures on the **raw** body. MUST NOT log
API keys, Bearer tokens, or passwords.

### VII. Safe online deploy

Production (`crm.safehouse.community`) is live and fronted by **Caddy with
automatic TLS/SSL**. Deploy MUST be repeatable and safe: build/test before
ship; never push smokes, oneshots, or local config to prod; run rebuild/cache
per Espo commands docs after apply (on the server, not via DDEV); DB/schema
changes require a written migration plan and **explicit user approval** before
server apply.

CI/CD MUST not leak secrets; unsafe deploy patterns MUST be fixed via a
dedicated spec (do not casually edit workflows without user request). Review
gate: tests green before production deploy when CI is used.

See Principle XVIII for how local and production runtimes relate.

Commands:

- Read: `/home/skoksharov/espocrm-documentation/docs/administration/commands.md`
- Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md

### VIII. Git hygiene

- One feature → one branch.
- Merge only on user request.
- After merge, delete leftover branches unless the user says otherwise.
- Keep the working tree clean; never leave unfinished work uncommitted without
  asking the user where it should go.

**Commits — ask first:** The agent MUST NOT run `git commit` (or `git add` for
the purpose of committing) unless the user **explicitly requested a commit in
that turn** or answered yes to a direct commit question. “Commit without push”
means commit only — never imply permission to push.

**Push — strict prohibition:** The agent MUST NOT run `git push`, `git push
--force`, or any remote publish (`gh pr create` that pushes, etc.) unless the
user **explicitly asked to push** in that same instruction. Accidental push is
a governance violation; if it happens, report immediately and do not push
again without explicit approval. When the user asks for commit only, run
**only** commit — never append push to the same command chain.

- **No CI/CD workflow edits** without explicit user request.
- Propose `.gitignore` updates; apply only on explicit request.

### IX. Builders vs release artifacts

Extension builder scripts and packaging tooling MUST NOT live as committed
release surface without user ask; prefer gitignoring builders. Built extension
ZIPs that passed tests MAY be kept when the user wants release artifacts.
Always ask before building. Test builds before any ship discussion.

### X. Rebuild & clear cache

After code or metadata changes, rebuild + clear cache in the correct
environment (`php rebuild.php`, `php clear_cache.php`, `php command.php
rebuild`):

- **Local:** MUST run inside **DDEV** (`ddev exec php …` / project helpers).
  Host PHP outside DDEV is forbidden for this project.
- **Production:** run on the server after approved deploy (no DDEV).

Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md

### XI. Schema / metadata migrations

Any DB or metadata shape change: assess migration need every time; propose a
safe migrate-on-server procedure; never assume “just rebuild” is enough for
production data. Hard rebuild (`php command.php rebuild --hard`) is
destructive — backup first (commands docs).

### XII. Reworking legacy features

Existing custom code is not sacred. Prefer production-ready, non-duplicative
design aligned with official docs. Propose rethink/rewrite when current design
fights Espo. Always cite GitHub (Principle I).

### XIII. New technology

When introducing libraries or frameworks: justify vs Espo-native options; list
alternatives; cite sources. Prefer zero new surface when native covers the
need.

### XIV. Tests

Custom code that changes behaviour MUST have Espo-appropriate tests (project
PHPUnit unit and/or integration on isolated `db_test`). How to run:

https://github.com/espocrm/documentation/blob/master/docs/development/tests.md

Local PHPUnit/integration/PHPStan MUST run via DDEV (Principle XVIII). Smokes
are temporary probes, not a long-term substitute. Never weaken tests to
greenwash broken behaviour.

After `/speckit-tasks` the agent MUST **propose** tests for real logic that
rebuild and owner UAT cannot prove (services, hooks, migrators, ACL, HMAC,
validators, API clients). Each such task MUST include “test: yes/no” plus one
why. The owner MAY drop test rows at the post-tasks wait. Silence is not
“cover the whole module”.

MUST NOT require a coverage percentage, PCOV/Xdebug line threshold, or
`includeUncoveredFiles` as a gate. MUST NOT write stupid tests: getters
without logic; JSON roundtrip; “HTTP 200” without a contract; i18n/HTML
snapshots of whole templates; tests of Espo core; metadata dumps without an
invariant. If the agent cannot name the bug the test would catch, the test
MUST NOT be written.

PHPUnit does **not** replace owner UAT (Principle XVII).

### XV. Agents & models

Default Cursor agent runs in **Auto**. For complex research, architecture, or
hard implementation slices, the agent MUST **propose** stronger or more
suitable models/subagents.

Every `/speckit-tasks` item MUST include complexity **1–10**, a proposed
model or subagent, and for non-trivial rows one short line of why.

After `/speckit-tasks` (and after `/speckit-plan` when `tasks.md` already
exists), print in owner chat a table: task ID, one-line work, `[C#]`, proposed
model, short why. Wait for the owner to keep, change, or drop proposals.

Before spawning any **advanced** model/subagent the owner has not already
approved for that row, ask openly (task ID + model): **Launch** the proposed
model, or **Replace** with another named model? Silence, “continue”,
“implement”, or “ok” without naming launch vs replace is **not** approval.
Suggested models in `tasks.md` are proposals, never a launch order.

If the preferred model is unavailable (limits/tokens), **ask the user** before
downgrading; if the user refuses, mark the task **blocked** until limits
refresh — do not silently ship worse-quality work.

Mechanical rows (inventory, grep, manifest bump, checklist ticks) SHOULD be
proposed as Auto or a fast composer — not because tokens are scarce, but
because a heavier model adds little judgment.

### XVI. Progress logging

Append handoffs to `.specify/progress/` (English, handoff-ready: state, files,
verification, blockers, next steps). Notion is **retired** for executor logs.
Legacy critical extracts live only in `.specify/progress_old/`.

### XVII. User communication

Chat with the user in **Russian**. Repository artifacts (constitution, specs,
progress, code comments) in **English**. Be direct; confirm before
security-sensitive or irreversible actions.

Product UI ships **Italian primary**, English secondary, plus existing
`ru_RU` where already present. New module strings MUST include `en_US` and
`it_IT`. Chat/UAT scripts stay Russian even when quoting Italian labels.

**Next Actions footer (mandatory):** Every substantive agent turn that
finishes a Spec Kit step, governance update, or implementation milestone MUST
end with a short **Next Actions** section listing realistic next options.
Do not silently jump to a different feature’s implement path.

**Owner user-test handshake:** After `/speckit-implement` (including a hotfix
spec), the agent MUST NOT call the feature accepted and MUST NOT open the next
**main** feature until the owner has run and reported a user-test checklist.

MUST:

- Write a durable English checklist at
  `specs/NNN-*/checklists/owner-user-tests.md` (or `NNN.K-*`) and keep
  `quickstart.md` aligned.
- In the same turn as “implement done”, paste that checklist in owner chat in
  **Russian**, numbered. Include menu path, URL hash when useful, exact
  button/label (quote the Italian UI string + English name if they differ),
  expected result, and what to send back (Pass / Fail / Skip + message or
  screenshot).
- Wait. Fail → triage (hotfix `NNN.K` if needed). Skip → record the reason.
- Mark steps that need **production** or another live external system as
  **Skip until the owner approves that exact action**.
- Keep PHPUnit evidence as supporting notes. They do not close the handshake.
- **Cursor-browser / live UI automation:** in the implement-done turn the
  agent MUST **offer** it (what screens, why) and **wait**. MUST NOT drive
  DDEV or production CRM UI unless the owner explicitly agrees **that turn**.
  PHPUnit and instance CLI/ORM seeds do not require that ask.

`/speckit-checklist` remains optional for extra quality lists. The owner
user-test handshake is **not** optional for product UI/behaviour features.
Pure documentation/audit specs MAY use a single owner-acceptance checkbox
instead of a full UI script.

### XVIII. Local ↔ production runtime parity

Local and production application stacks are intended to match **as closely as
practical**. The **primary intentional difference** is **DDEV**: local work
runs inside DDEV; production does not.

Locked CRM stack (MUST NOT deviate without an amendment):

| Role | Technology |
| :--- | :--- |
| CRM core | EspoCRM **10+** (this tree **10.0.3**) |
| PHP | **8.4** (pin) |
| Database | MariaDB **10.11** locally (DDEV); official also allows MySQL 8 / PostgreSQL 15 |
| Local web | DDEV `nginx-fpm`. URL `https://nonprofit-espocrm.ddev.site` |
| MUST NOT | FrankenPHP / php-zts for this project’s mail/IMAP path |
| Production HTTP | Caddy on the host with **automatic SSL** (`crm.safehouse.community`) |

**MUST:**

- Use DDEV for all local PHP / Espo / Composer / PHPUnit / rebuild work on
  this and the user’s similar PHP projects — not optional; no host-PHP
  fallback for project commands.
- Never treat production as a local substitute; never SSH/run maintenance on
  prod without explicit approval for that exact command (Principle VII).
- Prefer configurations that keep local≈prod except for DDEV wrapping and
  environment-specific secrets/config (not committed).

**Rationale:** Parity reduces “works on my machine” drift; DDEV standardizes
local PHP without inventing a second stack.

### XIX. Native functional UI fields

Product UI MUST use the **strongest native Espo field type** that matches the
data, justified from official field docs this turn:

https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md

MUST:

- Prefer enum / link / url / bool / date / int-with-range / App Secret over
  undifferentiated varchar. `displayAsLabel` + `style` when the value is a
  status or type staff must see at a glance.
- Put every product field on the **layout the user actually loads**. For
  **standard** entity types, module layout JSON is ignored unless
  `metadata/app/layouts.json` sets `module` (Espo ≥8.1):
  https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-layouts.md

MUST NOT default new settings to varchar/int text “because Config is a
string”.

### XX. Live instance REST

For diagnosis, UAT triage, and reading/writing **records on a running
instance**, the agent MUST follow the **explore-espo-endpoints** skill:
generic `/api/v1/{EntityType}` only, `select` + `maxSize` ≤ 200, `X-Api-Key`
(or HMAC) per

https://github.com/espocrm/documentation/blob/master/docs/development/api.md

and

https://github.com/espocrm/documentation/blob/master/docs/development/api/crud.md

Credentials come from **Cursor MCP env / local `mcp.json` only**. MUST NOT
ask the owner to paste secrets again if they are already in that env. MUST
NOT commit API keys, HMAC secrets, or passwords. MUST NOT log them.

Default local instance: `https://nonprofit-espocrm.ddev.site`. Production
(`https://crm.safehouse.community`) REST is allowed only with **explicit
owner approval** for that exact action (Principle VII).

Live REST is **instance data**, not vendor law. MUST NOT skip Principle I
because an API user exists. MUST NOT treat live JSON as a substitute for
`fields.md` / `hooks.md` / `orm.md`. Destructive calls still need explicit
owner confirm.

## Documentation & Stack

**Read root (mandatory every Espo decision; MUST NOT move or copy):**
`/home/skoksharov/espocrm-documentation` (`~/espocrm-documentation`)

**Cite root (committed files; clone origin, branch `master`):**
`https://github.com/espocrm/documentation/blob/master`

Mechanical map: replace the Read root with the Cite root, keep the rest of
the path. Folder links use `…/tree/master/…`.

The owner pulls the clone. Agents open local files; they MUST NOT prefer
`https://docs.espocrm.com`. If pull is needed and the last fetch is older
than ~7 days, they MAY run:

```bash
cd /home/skoksharov/espocrm-documentation && git fetch origin && git pull --ff-only
```

If pull fails: report why; still read the local clone; do not silently
switch to the website.

Decision map (open the local file this turn; paste the GitHub URL in git):

| Topic | Read (local) | Cite (GitHub) |
| :--- | :--- | :--- |
| Docs home | `/home/skoksharov/espocrm-documentation/docs/index.md` | https://github.com/espocrm/documentation/blob/master/docs/index.md |
| Modules | `/home/skoksharov/espocrm-documentation/docs/development/modules.md` | https://github.com/espocrm/documentation/blob/master/docs/development/modules.md |
| Coding rules | `/home/skoksharov/espocrm-documentation/docs/development/coding-rules.md` | https://github.com/espocrm/documentation/blob/master/docs/development/coding-rules.md |
| Coding practices | `/home/skoksharov/espocrm-documentation/docs/development/coding-practices.md` | https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md |
| ORM | `/home/skoksharov/espocrm-documentation/docs/development/orm.md` | https://github.com/espocrm/documentation/blob/master/docs/development/orm.md |
| Metadata | `/home/skoksharov/espocrm-documentation/docs/development/metadata.md` | https://github.com/espocrm/documentation/blob/master/docs/development/metadata.md |
| Hooks | `/home/skoksharov/espocrm-documentation/docs/development/hooks.md` | https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md |
| Tests | `/home/skoksharov/espocrm-documentation/docs/development/tests.md` | https://github.com/espocrm/documentation/blob/master/docs/development/tests.md |
| API | `/home/skoksharov/espocrm-documentation/docs/development/api.md` | https://github.com/espocrm/documentation/blob/master/docs/development/api.md |
| Extension packages | `/home/skoksharov/espocrm-documentation/docs/development/extension-packages.md` | https://github.com/espocrm/documentation/blob/master/docs/development/extension-packages.md |
| Fields | `/home/skoksharov/espocrm-documentation/docs/administration/fields.md` | https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md |
| Entity Manager / Formula | `/home/skoksharov/espocrm-documentation/docs/administration/entity-manager.md`, `formula.md` | https://github.com/espocrm/documentation/blob/master/docs/administration/entity-manager.md |
| Roles | `/home/skoksharov/espocrm-documentation/docs/administration/roles-management.md` | https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md |
| App Secrets | `/home/skoksharov/espocrm-documentation/docs/administration/app-secrets.md` | https://github.com/espocrm/documentation/blob/master/docs/administration/app-secrets.md |
| Commands | `/home/skoksharov/espocrm-documentation/docs/administration/commands.md` | https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md |
| Layouts | `/home/skoksharov/espocrm-documentation/docs/development/metadata/app-layouts.md` | https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-layouts.md |

This instance: `application/Espo/`, `client/`, `tests/` under this repo
(read-only for core; write only `custom/` + `client/custom/`).

#### Spec Kit

| Doc | URL |
| :--- | :--- |
| Repo | https://github.com/github/spec-kit |
| Quickstart | https://github.com/github/spec-kit/blob/main/docs/quickstart.md |
| Agentic SDD | https://github.com/github/spec-kit/blob/main/docs/reference/agentic-sdd.md |

Inside this repo: `.cursor/skills/speckit-*/SKILL.md`, `.specify/templates/`,
`.specify/scripts/bash/`.

#### PHP / CRM tooling

| Topic | URL |
| :--- | :--- |
| PHP 8.4 | https://www.php.net/releases/8.4/en.php |
| Composer | https://getcomposer.org/doc/ |
| PHPUnit 11 | https://docs.phpunit.de/en/11.5/ |
| PHPStan | https://phpstan.org/user-guide/getting-started |
| DDEV | https://ddev.readthedocs.io/en/stable/ |
| Caddy | https://caddyserver.com/docs/ |

**In-scope custom modules:** NonprofitEspocrm, GoogleIntegration,
WorkflowEngine, BugTracker, SafehouseAuroraThemes — under
`custom/Espo/Modules/` (+ matching `client/custom/modules/` where applicable).

**Slim agent prefs:** `AGENTS.md` points here; it MUST NOT duplicate this
constitution or Espo tutorials.

## Locked Decisions

Closed. MUST NOT reopen as questions.

| ID | Decision |
| :--- | :--- |
| PAID-ESPO | No paid Espo extensions unless the owner amends this file |
| F-FACTCHECK | Every Espo decision: **open** matching file under `/home/skoksharov/espocrm-documentation/docs/` this turn; **cite** `https://github.com/espocrm/documentation/blob/master` + same relative path. MUST NOT commit home paths. MUST NOT cite docs.espocrm.com in git |
| F-SPEC-SERIAL | One active spec; no parallel implement; specify-ahead only with owner OK |
| F-SPEC-SHAPE | Main features `specs/NNN-name/`; fixes `specs/NNN.K-name/` next to the parent |
| F-OWNER-UAT | After product implement/hotfix: English `owner-user-tests.md` + numbered Russian chat script; wait for Pass/Fail/Skip; PHPUnit does not replace owner UAT |
| F-LIVE-ESPO-REST | Live records via explore-espo-endpoints; keys from MCP/env only; prod REST needs explicit approval |
| F-UI-NATIVE-FIELDS | Strongest native Espo field type from fields.md this turn |
| F-CROSS-REPO | Write only this git root; MUST NOT edit the Espo docs clone |
| F-AGENTS-MD | `AGENTS.md` is rails only; product law = this constitution |
| F-I18N | UI: IT primary, EN secondary; keep existing `ru_RU`; chat/UAT in Russian |
| F-GDPR | Operational Espo export/delete in scope; legal DPA is not code — STOP |
| PHP-84 | PHP 8.4 pin |
| DDEV-LOCAL | All local PHP/Espo/Composer/PHPUnit/rebuild via DDEV |
| PROD-CADDY | Production = Caddy + automatic TLS at `crm.safehouse.community` |

## Development Workflow

1. Read this constitution and open the matching local Espo doc this turn.
2. Ensure **DDEV** is up for local PHP work (Principle XVIII).
3. One active Spec Kit feature at a time: specify → plan → tasks → implement
   (Principle III; specify-ahead only with user OK).
4. End each milestone reply with **Next Actions** (Principle XVII).
5. Implement only in extensions; native-first; cite **GitHub** in artifacts.
6. Rebuild + clear cache via `ddev exec` locally (Principles X, XVIII).
7. Add/extend PHPUnit for behaviour changes (Principle XIV) under DDEV.
8. Owner UAT handshake for product features (Principle XVII).
9. Append `.specify/progress/` handoff; ask before commit/push/CI edits.
10. Production apply only with explicit user approval and a migration plan
    when schema/data shape changes (Caddy host, no DDEV).

## Governance

This constitution supersedes informal habits and any leftover rulebook prose.
Amendments require: (1) user request or Spec Kit constitution command,
(2) semantic version bump, (3) Sync Impact Report in the HTML comment at the
top of this file, (4) progress note in `.specify/progress/`.

**Versioning:**

- MAJOR — remove/redefine non-negotiable principles incompatibly
- MINOR — add or materially expand a principle/section
- PATCH — clarifications, wording, non-semantic refinements

`LAST_AMENDED_DATE` is the calendar day of the amendment (ISO `YYYY-MM-DD`).
`RATIFICATION_DATE` stays the original adoption date unless the owner
restates adoption.

Compliance: every `/speckit-*` and implementation session MUST verify work
against Principles I–XX and the Locked Decisions table. Violations are
defects. Runtime guidance for agents is this file + official Espo docs
(read local, cite GitHub) + active specs under `.specify/` / `specs/` — not
a second AGENTS bible.

Dependent Spec Kit templates and commands read this file at runtime and MUST
NOT be edited by the constitution command.

**Version**: 1.3.0 | **Ratified**: 2026-08-31 | **Last Amended**: 2026-09-15
