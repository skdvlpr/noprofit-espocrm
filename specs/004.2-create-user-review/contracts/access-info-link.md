# Contract: Access-info is a set-password link

**Feature**: `004.2-create-user-review`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md

gm-edu pattern (Educational ZIP only; do not patch core): omit `password`
when `sendAccessInfo` is true so core `User::create` uses
`sendAccessInfoForNewUser`.

## This panel

| Invite checkbox | Password fields | Payload | Mail |
| :--- | :--- | :--- | :--- |
| on (default) | hidden / absent | `sendAccessInfo` true, no password | Access info + set-password link |
| off | still absent | `sendAccessInfo` false, no password | none |

MUST NOT send `{{password}}`. Templates from 004.1 stay. MUST NOT change
Administration → Users `sendAccessInfo` i18n.

Italian custom label (this panel only):
`Invia all'utente un'email con un link per creare una password`.
