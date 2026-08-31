# Agent preferences (nonprofit-espocrm)

## Always Spec-Driven

1. Read `.specify/memory/constitution.md` first. Obey it.
2. Do not leave Spec Kit (`/speckit-*`). One active feature/spec at a time (specify-ahead only with user OK — see constitution III).
3. End substantive replies with a **Next Actions** list (constitution XVII).
4. Espo behaviour lives in **official documentation**, not here.

## Docs

| Source | Path / URL |
|--------|------------|
| Constitution | `.specify/memory/constitution.md` |
| Espo docs (offline) | `~/safehouse/espocrm-documentation` (pull weekly / before Espo API work) |
| Espo docs (online) | https://docs.espocrm.com/ |
| Docs source repo | https://github.com/espocrm/documentation/ |
| Progress (current) | `.specify/progress/` |
| Progress (legacy archive) | `.specify/progress_old/` |

## User preferences

- Chat language: **Russian**. Artifacts (specs, progress, constitution, code comments): **English**.
- Ask before `git commit`, `git push`, PR creation, or CI/CD workflow edits.
- No production CLI / deploy / migration apply without explicit approval for that exact action.
- **Local PHP / Espo / Laravel-like work: DDEV only** (`ddev exec …`) — not optional; do not use host PHP for project commands.
- **Production**: Caddy with automatic SSL (e.g. `crm.safehouse.community`); never treat prod as the local runtime.
- Default agent: **Auto**. For hard research/architecture/implementation, launch stronger subagents; on `/speckit-tasks` score complexity 1–10 and ask before downgrading models.
- Be direct. Confirm security-sensitive actions.
