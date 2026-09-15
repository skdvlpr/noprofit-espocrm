# Owner user tests: Standalone Google calendar; retire WorkflowEngine

**Feature**: `003-google-standalone`  
**Instance**: local DDEV only. Production uninstall/deploy is **Skip** until the owner approves that exact action.  
**UI language**: Italian primary. Chat answers: Pass / Fail / Skip + note or screenshot.

Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/extensions.md

PHPUnit and `bin/smoke-google-integration.php` support this list; they do not close it.

## Checklist

- [ ] U1 Open `#CalendarTemplate`, create or edit a template, set target entity (e.g. Meeting / Riunione). In the description/summary text field, use the placeholder dropdown and **Insert**. Expected: screen loads; a `{{…}}` token appears; no missing-module error in the browser console.
- [ ] U2 Open a Meeting (or Opportunity / Grants) detail that has the Google Calendar panel. Open per-date event settings, insert a placeholder in the description override. Expected: helper appears; insert works.
- [ ] U3 Hard-refresh (`Ctrl+Shift+R`) after rebuild. Expected: Google screens still load (stale AMD cache cleared).
- [ ] U4 Administration → Extensions. Expected: **WorkflowEngine** is not listed / not installed.
- [ ] U5 Search navbar / Administration for Workflow / WorkflowDefinition. Expected: those screens are gone.
- [ ] U6 (optional) Nonprofit template-text field if you still have a custom screen that uses it. Expected: empty-entity hint is a Global message, not a missing WorkflowDefinition key.
- [ ] U7 Production: uninstall WorkflowEngine / rsync deploy. **Skip** until the owner names that action.

## What to send back

For each U#: **Pass**, **Fail** (what you saw), or **Skip** (why).
