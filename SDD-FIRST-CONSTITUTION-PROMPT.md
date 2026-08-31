# SDD bootstrap — first constitution session

**How to run (human):** open this repository as the Cursor workspace root (`nonprofit-espocrm`, not the parent `safehouse` monorepo). Start Agent, run `/speckit-constitution`, then attach or paste this entire file as the argument / follow-up context.

**Language:** chat with the user in **Russian**. Write constitution, progress archives, specs, code comments, and any remaining `AGENTS.md` content in **English**.

**This session does NOT implement product features.** No feature branches for CRM behaviour, no mass refactors of modules, no deploy, no push, no CI workflow edits unless the user explicitly asks mid-session. Governance + archive + slim `AGENTS.md` only. Heavy audits and `bin/` cleanup are **deferred intents** (list them under Spec Kit `Next Actions`).

---

## Mission

Move `nonprofit-espocrm` into Spec-Driven Development (Spec Kit). The durable source of truth becomes:

| Artifact | Path | Role |
|----------|------|------|
| Constitution | `.specify/memory/constitution.md` | Non-negotiable engineering & process law |
| Progress (ongoing) | `.specify/progress/` | Handoff logs replacing Notion |
| Progress (legacy) | `.specify/progress_old/` | One-time critical extract from past Notion / chat history |
| Slim AGENTS | `AGENTS.md` | User prefs + “always SDD” only — written **as if the old rulebook never existed** |
| Espo docs (offline) | `~/safehouse/espocrm-documentation` | Primary technical reference while coding/planning |
| Espo docs (online) | https://docs.espocrm.com/ and source repo https://github.com/espocrm/documentation/ | Canonical when local clone missing/stale |

Do **not** copy the old `AGENTS.md` bible into the constitution. Re-think principles against **official EspoCRM documentation**. Prefer short principles + **mandatory doc citations** (local file paths with section anchors where possible, plus online URLs).

---

## Mandatory reading order (before drafting constitution)

1. Spec Kit constitution skill / template behaviour (`.specify/templates/constitution-template.md`, `.cursor/skills/speckit-constitution/SKILL.md`).
2. **Local Espo documentation clone (REQUIRED):**  
   `~/safehouse/espocrm-documentation`  
   (clone of [espocrm/documentation](https://github.com/espocrm/documentation/); MkDocs tree under `docs/`).  
   At minimum open and use as citations:
   - `docs/development/modules.md`
   - `docs/development/extension-packages.md` (and/or related extension packaging docs)
   - `docs/development/coding-practices.md`, `docs/development/coding-rules.md`
   - `docs/development/hooks.md`, `docs/development/acl.md`, metadata docs under `docs/development/`
   - `docs/administration/extensions.md`
   - `docs/administration/app-secrets.md` ← secrets store in Admin
   - `docs/administration/entity-manager.md`, `docs/administration/roles-management.md`
   - `docs/administration/commands.md` (rebuild / extension CLI)
   - Any other pages needed for security, API, formula, upgrades
3. If a local page is missing or the clone is unavailable: fetch the matching page from https://docs.espocrm.com/ and/or browse https://github.com/espocrm/documentation/.
4. Skim current `AGENTS.md` **only as a source of user preferences and hard-won Safehouse constraints** — then discard structure, silly examples, Notion rituals, and anything that contradicts official docs.
5. Read current CI: `.github/workflows/ci.yml`, `.github/workflows/prod-provision-oneshot.yml` (and any other workflows). Note secrets handling, deploy gates, what ships to prod.
6. Inventory custom modules under `custom/Espo/Modules/` (names only + purpose) for constitution scope — **full code audit is the next spec**, not this session’s implementation work. Record high-level risks in progress notes if obvious.
7. **Historical handoff (Notion → local):** using Notion MCP and/or past agent transcripts, extract **critical** project state into `.specify/progress_old/` (create the directory). Split into a **small number of stage/feature files** (English), e.g.:
   - `01-project-context.md` — product goals, prod exists, Espo 10.x target
   - `02-extensions-map.md` — NonprofitEspocrm, GoogleIntegration, WorkflowEngine, themes, etc.
   - `03-security-and-secrets.md` — known secret locations, refuse-production, PII concerns
   - `04-ci-cd-and-deploy.md` — current GH Actions / deploy habits
   - `05-open-risks-and-debt.md` — unfinished work, known bugs, doc drift
   - add more only if needed; do **not** dump entire Notion verbatim  
   Notion project/task URLs historically used (read-only archive sources):
   - Active project: https://app.notion.com/p/38d8d469d405817cbd23f6cfb3ce32af
   - Tasks DB: https://app.notion.com/p/38d8d469d40580b8b87ee0681b9d929c
   - Projects DB: https://app.notion.com/p/2fb8d469d4058093a291fd990185824d
   - Archive Safehouse/Gomercato: https://app.notion.com/p/34e8d469d4058027af82f2ce986a6448  
   **Going forward: no Notion executor logs.** New handoffs go only to `.specify/progress/`.
8. Create `.specify/progress/` with a short `README.md` (how to append handoffs) and `000-sdd-bootstrap.md` describing this constitution session outcome.

### Espo documentation freshness (ongoing duty — encode in constitution)

- Canonical offline tree: `~/safehouse/espocrm-documentation` (git remote must remain `https://github.com/espocrm/documentation/`).
- **At least once per calendar week** (and at the start of any session that touches Espo APIs/metadata/extensions if the last pull is older than ~7 days):  
  `cd ~/safehouse/espocrm-documentation && git fetch origin && git pull --ff-only`  
  If pull fails, report why; fall back to online docs; do not silently use stale local pages when online is available.
- Prefer citing **both**: local path (e.g. `~/safehouse/espocrm-documentation/docs/development/modules.md`) **and** the matching https://docs.espocrm.com/… URL.
- During `/speckit-constitution`, `/speckit-specify`, and `/speckit-plan`, the agent **must** consult local docs first, else online, for every stack/framework/library decision — and **cite those sources in the artifact** with a short rationale (not “because we always did”).

---

## Deliverable A — Constitution (`.specify/memory/constitution.md`)

Produce a **laconic** constitution (aim: readable in one sitting; not a second AGENTS). Structure via Spec Kit template, but content must cover the principles below. Prefer “principle + link to docs” over copying Espo tutorials into the constitution.

### Required principle themes (re-articulate; do not paste AGENTS)

1. **Official-docs supremacy & Native-first**  
   Prefer Espo native mechanisms (Entity Manager, Roles, Formula, Dynamic Logic, metadata, hooks, extension packages) over custom reinvention. Never invent APIs that contradict docs. Cite coding practices / modules / extensions docs.

2. **Extensions only — never touch core**  
   No edits under `application/`, Espo core, or vendored Espo that should be upgraded upstream. All product work ships as **installable extensions** suitable for **EspoCRM 10+** (any instance, not only this nonprofit fork).  
   If behaviour depends on Nonprofit being installed: detect that module/extension and branch logic; otherwise behave correctly on stock Espo. Extensions must treat **core and arbitrary custom entities the same** unless a documented capability check exists. Minimize cross-extension coupling; if coupling is required, document it and propose a clean dependency strategy.

3. **SDD lock-in**  
   Work only through Spec Kit (`specify` / `/speckit-*`). One active feature/spec at a time. No parallel feature implementation. Leaving SDD requires an explicit user amendment to the constitution.

4. **Doc-backed planning**  
   Every `/speckit-specify` and `/speckit-plan` artifact must cite Espo (and other stack) documentation and explain *why* each non-obvious choice was made.

5. **Constitution & docs beat user whim**  
   If a request contradicts the constitution or official docs: **refuse**, explain why, propose compliant alternatives. If the user insists: implement only with a **safe rollback path** (branch, reversible migration, no silent prod damage) and record the exception in `.specify/progress/`.

6. **Security, secrets, PII**  
   No secrets in git, logs, issues, or extension ZIPs. Prefer Espo **App Secrets** (Admin) — cite `docs/administration/app-secrets.md` / https://docs.espocrm.com/administration/app-secrets/. Personal data in CRM must follow least privilege (Roles / field-level security per docs). Plan migration of misplaced secrets into App Secrets. Analyze leak paths (CI logs, smoke scripts, debug, backups).

7. **Safe online deploy (including AI-assisted)**  
   Production already runs a version of this tree — treat prod as live. Define a **repeatable safe deploy** story: what may be built/committed, what must never reach prod (smokes, oneshots, local config), how extensions are built/tested before ship, how rebuild/cache runs, how DB/schema changes require **migration plans** and explicit user approval before server apply. Review current GitHub Actions for secret exposure and unsafe deploy; constitution must require a consistent safe workflow. **Do not change CI/CD files in this session** — note gaps in progress and defer a dedicated spec if changes are needed.

8. **Git hygiene**  
   - One feature → one branch.  
   - Merge only on user request.  
   - After merge, delete leftover branches unless user says otherwise.  
   - Keep the working tree clean: no orphan untracked clutter; never leave unfinished work uncommitted without asking the user where it should go.  
   - **No commit, no push, no CI/CD workflow edits** without explicit user request. Agent **may ask** for permission; only then proceed.  
   - May **propose** `.gitignore` updates; apply only on explicit request.

9. **Builders vs release artifacts**  
   Extension **builder scripts** and packaging tooling must not live as committed release surface without user ask; prefer gitignoring builders. **Built extension ZIPs** that passed tests may be kept in the repo when the user wants release artifacts. Always ask before building. Test builds before any ship discussion.

10. **Rebuild & clear cache**  
    After code/metadata changes: rebuild + clear cache automatically in the appropriate environment (local DDEV / documented CLI), citing Espo commands docs.

11. **Schema / metadata migrations**  
    Any DB or metadata shape change: check migration need every time; propose a safe migrate-on-server procedure; never assume “just rebuild” is enough for prod data.

12. **Reworking legacy features**  
    Existing custom code is not sacred. Prefer production-ready, non-duplicative design aligned with official docs. Propose rethink/rewrite when current design fights Espo. Always cite docs.

13. **New tech**  
    When introducing libraries/frameworks: justify vs Espo-native options; list alternatives; cite sources.

14. **Tests**  
    Custom code must meet Espo-appropriate test expectations (align with project PHPUnit practices and docs). Smokes are not a substitute for proper tests long-term.

15. **Agents & models**  
    Default Cursor agent runs in **Auto**. For complex research, architecture, or hard implementation slices, the agent **must** launch subagents with stronger/more suitable models. On `/speckit-tasks`, score each task complexity **1–10** and pick models from those available. If the preferred model is unavailable (limits/tokens), **ask the user** before downgrading; if the user refuses, mark the task **blocked** until limits refresh — do not silently ship worse-quality work.

16. **Progress logging**  
    Append handoffs to `.specify/progress/` (English, handoff-ready). Do not use Notion for executor logs anymore.

17. **User communication**  
    Russian in chat; English in repo artifacts. Be direct; security-sensitive actions require explicit confirmation.

Keep the constitution **short**. Push procedural detail into cited doc paths / future `specs/`, not into endless sections.

---

## Deliverable B — Rewrite `AGENTS.md` (slim)

Replace the huge rulebook with a **minimal** file that assumes the constitution + Espo docs exist. Write it **as if the old AGENTS never existed**.

**Keep only:**

- Pointer: read `.specify/memory/constitution.md` first; never leave SDD.
- User preferences (safe ones): Russian chat; English artifacts; ask before commit/push/CI changes; one spec at a time; Auto + subagents for hard work; etc.
- Where to find docs (local path + GitHub docs repo + docs.espocrm.com).
- Where to find progress (`.specify/progress/`, archive `.specify/progress_old/`).
- Explicit: Espo behavioural detail lives in **official documentation**, not in AGENTS.

**Remove:** Notion logging protocols, long ENT/FLD/LAY copies of docs, silly examples, duplicated packaging tutorials, entity laundry lists (those belong in progress_old / future specs).

Do **not** leave AGENTS as a second constitution.

---

## Deliverable C — Session progress notes

Write `.specify/progress/000-sdd-bootstrap.md` summarizing: what was ratified, AGENTS slimmed, docs pull status, CI risks observed, deferred intents.

Optional: if you refreshed the docs clone this session, note the commit SHA of `espocrm-documentation`.

---

## Explicitly OUT OF SCOPE this session (defer — Next Actions)

Do **not** execute these now; list them for the user with suggested Spec Kit commands:

1. **`/speckit-specify` — Custom code compliance audit**  
   Full review of `custom/Espo/Modules/**` vs official docs: security holes, performance, anti-patterns, extension boundaries, secrets not in App Secrets, coupling. Produce a prioritized change proposal (next major spec after constitution).

2. **`/speckit-specify` — Tests & `bin/` hygiene**  
   Align tests with Espo expectations; delete obsolete smokes and unnecessary scripts under `bin/`; stop committing builders (gitignore + remove from GitHub history/remote only with explicit user request); keep tested release ZIPs policy; ask before every build.

3. **`/speckit-specify` (if needed) — Harden CI/CD**  
   Only after user approval: make workflows consistently safe (no secret leakage, same deploy path every time).

4. Any production deploy, migration apply, or extension upload.

---

## Quality bar for this session

- Constitution cites **local** `~/safehouse/espocrm-documentation/docs/...` paths and **online** https://docs.espocrm.com/... (and notes https://github.com/espocrm/documentation/ for pulls).
- Native-first and extensions-only are unmistakable.
- Security / App Secrets / PII / safe deploy / git gates / SDD lock-in / one-spec / model routing are present without essay-length paste.
- `AGENTS.md` is short and preference-oriented.
- `progress_old` exists with critical history only; Notion logging is retired in writing.
- Scope Guard respected: no product code churn; deferred intents listed clearly.

When finished, show the user: constitution path, AGENTS diff summary, progress paths, and the ordered **Next Actions** for the compliance-audit spec.
