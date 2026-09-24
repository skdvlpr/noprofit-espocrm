# Quickstart: planner fixes and availability links

Local only. Outbound mail on local DDEV is already configured: send the
volunteer availability template there and read the received message.
Do not send from production. Do not apply on production from this guide.

## Prerequisites

- DDEV site up: `https://nonprofit-espocrm.ddev.site`
- Spec and plan in `specs/006-availability-magic-links/`
- Outbound address may be unset in tests; unit tests must not require a
  live SMTP send

## 1. Defects before links

After implementation:

- Installer rebuild does not run `SHOW COLUMNS` / `UPDATE` against
  `activity_offer_slot`.
- An administrator who is not in the plan cohort cannot create
  availability rows for themselves.
- Availability log lines do not contain the recipient address or the
  link secret.

## 2. Link behaviour (no live mailbox)

Run the module PHPUnit cases inside DDEV. They should show:

- Two volunteers get two different secrets; only hashes are stored.
- Submit marks the link consumed; a second submit does not change
  invites.
- A link older than 7 days does not save.
- Resend to two of three replaces only those two hashes; the third
  secret still submits.
- A secret for volunteer A cannot write volunteer B.

Command (full suite, when the owner wants a push decision later):

```bash
ddev exec bash bin/run-tests.sh
```

Filtered runs are not a substitute for that full suite.

## 3. Staff check on local DDEV

Send through the local outbound account to a mailbox you can open.
Do not print SMTP settings.

- Request availability. The received volunteer mail contains that
  person’s link to the shift dialog only. It does not ask them to log
  in to the CRM and it does not link to the planner.
- Open the link in a private window. No login screen. Submit. The plan
  shows that person’s availability.
- Open the same link again. It does not edit.
- Resend to one person. Their old link fails. Another person’s old link
  still works. Only the selected person is mailed.

## Out of this check

Live volunteer mail, production rebuild, and the public website
repository.
