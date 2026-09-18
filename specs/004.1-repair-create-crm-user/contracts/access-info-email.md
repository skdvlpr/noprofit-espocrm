# Contract: Access info email (set-password link)

**Feature**: `004.1-repair-create-crm-user`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-templates.md
https://github.com/espocrm/documentation/blob/master/docs/development/template-custom-helper.md

## Create payload

| Field | Rule |
|-------|------|
| `sendAccessInfo` | Default true when copied email is non-empty |
| `password` | Omit when send-access is true (even if the drawer had a value) |
| Email missing | send-access off; staff must add email or set a password themselves |

Native create then uses `sendAccessInfoForNewUser` (change-password
request + access-info mail), not `sendPassword`.

## Templates

Override in NonprofitEspocrm:

- `templates/accessInfo/{en_US,it_IT,ru_RU}/body.tpl` (+ subject)
- `templates/passwordChangeLink/{en_US,it_IT,ru_RU}/body.tpl` (+ subject)

`app.templates` `module` = NonprofitEspocrm so FileReader finds them.

Italian is the primary body staff expect; en_US is fallback.

## Body rules

- Include username and a control that uses `{{siteUrl}}` (core already
  appends `?entryPoint=changePassword&id=…` when a request exists).
- MUST NOT render `{{password}}` in these branded bodies.
- Logo via helper `{{safehouseLogo}}` (config site root + existing Safe
  House PNG). Do not use `{{siteUrl}}` as the image `src` (it is the
  password-change URL).
- Layout comparable to shift availability mail (logo, short greeting,
  one primary button/link, footer).

## Local SMTP

If system SMTP is not configured, core throws on send. User record MUST
still exist; owner UAT marks mail **Skip** with that reason. Do not fail
the whole Contact save if mail fails after the User is created — prefer
Espo’s existing create behaviour; if core aborts the User create, catch
and show the message without deleting the Contact.

## Out of contract

- Changing core `Sender.php`.
- Embedding CID the way ShiftEmailService does inside core send.
- Production send to real volunteers until the owner names that test.
