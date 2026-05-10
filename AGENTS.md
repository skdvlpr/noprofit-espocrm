# AGENTS.md — SafehouseCrm EspoCRM Extension

> Single source of truth for AI executor agents. Merged from AGENTS.md + AGENTS.addinstruction.md + Research standards.

## 1. Project Overview

- **Goal:** Installable EspoCRM extension ZIP — custom entities, ACL, Google Calendar/Drive, reporting.
- **Module path:** `custom/Espo/Modules/SafehouseCrm/`
- **Target:** EspoCRM >=9.3.6 <10.0.0, PHP 8.1+, DDEV local dev.
- **Notion Project:** https://www.notion.so/34e8d469d4058027af82f2ce986a6448
- **Tasks DB:** `collection://34f8d469-d405-813c-b025-000b707ee162`
- **Projects DB:** `collection://34f8d469-d405-8139-ae4a-000bef108b0e`

## 2. Token Economy & Efficiency Rules

> **CRITICAL: Minimize token consumption at every step.**

- **User handles rebuild/cache:** After code changes, tell user to run `ddev exec php command.php rebuild` and clear cache. Do NOT run these commands via agent.
- **User handles testing:** Provide test instructions, user tests manually. Don't run test commands unless explicitly asked.
- **Batch reads:** Read multiple related files in one turn, not one per turn.
- **No verbose output:** Don't echo back full file contents in responses. Summarize changes.
- **Test files → .gitignore:** Any scratch/test files created must be added to `.gitignore`.
- **Stop before limit:** When token budget is running low, STOP immediately and create a checkpoint (save progress to Notion + local artifact). Don't try to squeeze in one more step.
- **Notion checkpoint on every significant change:** After each meaningful code change (new file, metadata change, layout update, etc.), immediately log progress to the Notion task page. This ensures another agent can seamlessly continue if this session runs out of tokens.
- **Compress responses:** Use tables, bullet points, short sentences. No filler text.
- **Skip obvious:** Don't explain EspoCRM basics or re-summarize what the user already knows.

## 3. Notion — Source of Truth

- Fetch task page **before** any work and **before** any write.
- Progress notes: **append-only**, dated: `[YYYY-MM-DD] description`.
- Never delete previous executor logs.
- Resolve all `WARNING - TO BE VERIFIED` before writing code.
- Mark acceptance criteria only when verified.
- Status flow: `Not Started → In Progress → Blocked → Done`
- Language: Notion in English, user replies in Russian.

## 4. Phase Map

| Phase | Goal                             | Tasks         |
| ----- | -------------------------------- | ------------- |
| 0     | Research & Setup                 | 0.1–0.3       |
| 1     | Custom Entities & Layouts        | 1.1–1.7       |
| 2     | Roles, ACL & Teams               | 2.1–2.2       |
| 3     | Google Integration               | 3.1–3.5       |
| 4     | Reporting & Automated Delivery   | 4.1–4.5       |
| 5     | Extension Packaging & Deployment | 5.1–5.5       |
| 6     | Time Tracking & Payroll          | 6.1–6.5 (New) |

Execute in order. Phase N+1 only after all Phase N acceptance criteria pass.

## 5. Repository Layout

```
custom/Espo/Modules/SafehouseCrm/
├── manifest.json
├── Resources/
│   ├── metadata/{entityDefs,clientDefs,layouts,aclDefs,scopes,logicDefs,app,fields,actionDefs}/
│   ├── i18n/en_US/
│   └── templates/email/
├── Services/, Controllers/, Hooks/, Jobs/, Acl/, Classes/
└── client/modules/safehouse-crm/src/
scripts/     ← BeforeInstall, AfterInstall, etc.
bin/build.sh
```

**NEVER edit:** `application/`, `vendor/`, `data/cache/`, `data/logs/`

## 6. Executor Role & Validation

You are not a blind executor. You must:

1. **Verify** the plan against actual EspoCRM code, docs, runtime.
2. **Execute** what is valid.
3. **Propose changes** if plan is incorrect/suboptimal — but always notify user first.
4. **Log** all progress to Notion.

Default mode: **verify → execute → test → log**.

### Plan Changes

- **Minor safe fix:** Notify user, apply, log to Notion.
- **Meaningful change** (architecture/behavior/deployment): STOP, propose alternative, wait for approval.
- Never silently replace the plan.

## 7. Research Before Coding

Verify before implementing:

1. **EspoCRM docs:** https://docs.espocrm.com/ (metadata, hooks, scheduled-jobs, extension-packages, acl)
2. **Forum:** https://forum.espocrm.com/
3. **Codebase:** grep `application/Espo/` for class names, interfaces, DI keys.
4. **Google API docs** (Phase 3 only).

If unverified → write `WARNING - TO BE VERIFIED` in Notion, don't code it.

## 8. Coding & Documentation Standards

### PHP

- Namespace: `Espo\Modules\SafehouseCrm\`
- PSR-4 via EspoCRM module loader. DI via constructor injection.
- **Commenting:** Use PHPDoc for all classes and methods. Describe parameters and return types.
- No direct SQL — use EntityManager/Repository.
- No static state, no `var_dump`/`error_log`/`print_r`.
- Never log tokens/secrets.

### Metadata (JSON)

- Validate JSON before commit. Field names: camelCase.
- **Layouts:** Use `Resources/layouts/` if the project already has them there, but prefer `Resources/metadata/layouts/` for new modules. Check both locations.

### Frontend (JS)

- Custom views only when metadata can't achieve the goal. Extend, don't copy core views.
- No direct Google API calls from frontend.

### EspoCRM-Native Preference Order

1. Metadata → 2. DI services → 3. Hooks → 4. Controllers → 5. Scheduled jobs → 6. Custom PHP → 7. External services

## 9. Execution Workflow

For each task:

1. FETCH Notion task page (read fully)
2. RESOLVE all WARNINGs
3. RESEARCH (docs, forum, grep)
4. DECOMPOSE into micro-steps, show user table:
   ```
   | # | Step | Why | Test method |
   ```
5. IMPLEMENT one logical unit at a time
6. STOP at checkpoints → give test instructions to user
7. Wait for user confirmation before next step
8. LOG to Notion (append dated entry)
9. CHECK acceptance criteria

**Mandatory stop points:** new file created that others depend on; metadata changed; hook/service registered; route/endpoint added; UI changed; fix applied.

**If blocked:** Write `BLOCKED: [reason]` in Notion. Stop. Surface the blocker.

## 10. Solved Cases & Knowledge Base

If a non-obvious problem is solved (e.g. layout file location mismatch, 404 controller issues), **MANDATORY**:

1. Record the case in the Notion Project page under `# Knowledge Base`.
2. Add a brief note to this section in `AGENTS.md`.

### Known Cases:

- **Layout Priority:** If `Resources/layouts/` exists, it may override `Resources/metadata/layouts/`. Always check both.
- **Controller 404:** Custom modules require a Controller class even if it's empty, to register the API endpoint.
- **Formula vs logicDefs:** Formula in `entityDefs` can override `logicDefs`. Prefer `logicDefs` for complex logic.
- **Before-save Formula Location:** In EspoCRM 9.3.6, working before-save Formula scripts are loaded from `Resources/metadata/formula/{Entity}.json` (`beforeSaveCustomScript`), not from `entityDefs.formula` or `logicDefs`.
- **Formula Validation Limits:** Regex matching is `string\test(...)`, not `regex(...)`. A general before-save `throwError()` was not verified; `recordService\throwBadRequest` is API-script-only. Use a native `FieldValidator` for UI/API validation failures.
- **Navbar Visibility:** Custom entity scopes do not automatically appear in the runtime menu. EspoCRM navbar uses `config.tabList`; extension install scripts should update it via `ConfigWriter`. Never edit `data/config.php` directly.
- **Scope Registration:** New entities must be added to `Resources/metadata/app/scopes.json` with the correct `"module": "SafehouseCrm"` to be properly discovered and associated with the module.
- **Date-Based Status:** Before-save Formula runs only when a record is saved/API-saved. Future calendar-day flips require an approved scheduled job.

## 11. Security Checklist (verify on every task)

- [ ] No unauthenticated endpoints (all require EspoCRM auth)
- [ ] ACL checked on every entity action
- [ ] IDOR prevention (users can't access others' tokens/data)
- [ ] OAuth tokens never in frontend/logs
- [ ] Google Drive SSRF: whitelist googleapis.com only
- [ ] No XSS via rich text (use native sanitizer)
- [ ] File upload: validate MIME, size, ownership
- [ ] Scheduled jobs: no secrets in payload/logs
- [ ] Calendar sync: idempotent via espoId key
- [ ] Volontario can't read financial ConteggioPasti data

## 12. Performance Rules

- All queries must have `limit`/`maxSize`. No N+1 queries.
- afterSave hooks <50ms. Heavy work → background job.
- Scheduled jobs: max 100 records/run, cursor pagination.
- Verify field indexing before adding query filters.

## 13. Stability & Safety Checklist (For 100% Reliability)

- [ ] **Automated Tests:** PHPUnit for services, Jest/Cypress for UI.
- [ ] **Error Monitoring:** Integration with Sentry or similar.
- [ ] **Log Rotation:** Ensure `data/logs/` doesn't grow indefinitely.
- [ ] **DB Backups:** Scheduled SQL dumps.
- [ ] **Environment Isolation:** Clear distinction between Local, Staging, Prod.
- [ ] **Dependency Audit:** Regular `composer audit` and `npm audit`.
- [ ] **Rate Limiting:** Protect API from brute force/abuse.
- [ ] **Session Security:** Use `HttpOnly` and `Secure` flags for cookies.
- [ ] **Encryption at Rest:** For sensitive PII (Personally Identifiable Information).

## 14. Phase 6: Time Tracking & Advanced Payroll

- **Goal:** Full Timesheet system with automated payroll calculations.
- **Key Entities:**
  - `TimesheetEntry`: Daily records of hours worked.
  - `PayrollAdjustment`: Monthly bonuses or deductions.
  - `WorkingCalendar`: Link to native EspoCRM `Calendari Lavorativi`.
- **Associations:**
  - `VolontarioDipendente` ↔ `WorkingCalendar` (Link).
  - `VolontarioDipendente` ↔ `User` (1-to-1 Link).
- **Payroll Logic:**
  - `hourlyRate`: Currency field on `VolontarioDipendente`.
  - `monthlySalary`: Calculated as `(Total Hours * hourlyRate) + Adjustments`.
  - Skip detection: Automatic "Skip" flag for missed shifts based on calendar.
- **Check-in/Out UI:** Custom buttons for easy entry recording.
- **Reporting:** Monthly PDF salary slips generation.
- **Current state:** `status` field and auto-deactivation logic implemented. Salary fields deferred to Phase 6.

## 15. Extension Packaging

- No hardcoded URLs/ports/IDs/secrets. No DDEV paths in prod code.
- Every commit installable on clean EspoCRM without manual steps.
- Build: `./bin/build.sh` → `dist/safehouse-crm-v{VERSION}.zip`

### After Code Change (USER runs these):

```bash
ddev exec php command.php rebuild
# Then: Admin → Clear Cache, Ctrl+Shift+R browser
```

## 16. Google Integration (Phase 3)

- OAuth redirect_uri: hardcoded server-side only.
- Tokens: encrypted storage, auto-refresh before API calls.
- Scopes: `calendar` (3.3, 3.4), `drive.file` (3.5).
- Drive: proxy all downloads through CRM endpoint. SSRF whitelist.

## 17. Reporting (Phase 4)

- Respect ACL. System-user jobs scoped explicitly.
- Exports in memory, streamed. Delivery logs: metadata only.

## 18. Definition of Done

- [ ] All WARNINGs resolved and documented
- [ ] All acceptance criteria checked
- [ ] Security checklist verified
- [ ] Performance rules verified
- [ ] No PHP errors in `data/logs/espo.log`
- [ ] Rebuild OK
- [ ] Installable on clean instance
- [ ] Progress note in Notion

## 19. Forbidden Actions

- Never edit `application/`, `vendor/`, `data/`
- Never invent class names — grep and verify
- Never put secrets in source code
- Never run unbounded DB queries
- Never skip security checklist
- Never overwrite executor logs in Notion
- Never mark Done with unchecked criteria
- Never log token values

## 20. Executor Workflow & Testing (Incremental)

### Core principle: verify -> execute -> test -> log.

- **One logical unit per step:** One file, one hook, or one metadata change at a time. Never combine multiple independent logical units.
- **Micro-step breakdown:** Show the user an execution plan before coding (`| # | Micro-step | Why | Test method |`).
- **Mandatory stop points:** New files, registered hooks/services, metadata changes affecting UI, new API routes. Stop and request a functional test.
- **Functional Test Request Format:**
  When stopping, provide exact instructions:
  ```text
  🧪 FUNCTIONAL TEST REQUIRED — Step [N]: [Step name]
  What was implemented: [brief info]
  How to test: [Exact UI action, API request, or CLI command]
  Expected result: [precise behavior]
  ```
  Wait for user confirmation (`✅ passed` / `❌ failed`) before continuing.

## 21. Plan Change & Notification Protocol

- **Category A (Technical mismatch):** Method doesn't exist, invalid extension point, conflicts with rules.
- **Category B (Technical degradation):** Slow UI, heavy logic, bypasses native mechanisms.
- **Rule:** If the plan should change, you must **always notify the user first**. Never silently replace.
- **Amendment Format:** If approved, append `### Plan Amendment — YYYY-MM-DD` to the Notion Task page.

## 22. Notion Progress Logging

- **Task completed/Step done:** Append to the specific **Task page**.
- **Broad issues / KB:** Append to **Project page** `# Notes`.
- **Implementation log format:**
  ```markdown
  ### Implementation Log — YYYY-MM-DD

  **Step completed:** [step]
  **Status:** ✅ Done | ⚠️ Blocked | ❌ Failed
  **What was done:** [desc]
  **Problems encountered:** [issues]
  **Next step:** [name]
  ```

## 23. Environment Variables & Config

| Variable                          | Where                         | Description                  |
| --------------------------------- | ----------------------------- | ---------------------------- |
| `GOOGLE_CLIENT_ID` / `SECRET`     | Admin → Integrations → Google | OAuth app credentials        |
| `safehousencrm.googleSyncEnabled` | CRM config                    | Toggle Calendar sync         |
| `safehousencrm.grantAlertDays`    | CRM config                    | Days before deadline alert   |
| `safehousencrm.reportRecipients`  | CRM config                    | Admin email list for reports |

## 24. Deployment, Documentation & Rollback

### [DEPLOY] Deployment Notes Format

If a change is required outside the `SafehouseCrm` module path, log in Notion:

```markdown
### [DEPLOY] Deployment Notes — [File/Config Name]

**Production location:** [full path]
**Why:** [technical reason]
**Action:** [exact steps/snippets]
```

### Commenting & Documentation

- **PHP:** Use PHPDoc for ALL classes and methods (`@param`, `@return`, `@throws`).
- **Metadata:** Keep field names in `camelCase`.

### Rollback Procedure

If a task introduces a regression:

1. Revert changed files in `custom/Espo/Modules/SafehouseCrm/`.
2. Run `ddev exec php command.php rebuild`.
3. Document rollback in Notion task page.
