# Quickstart: Restore contact–user sync

**Feature**: `007.1-restore-channel-sync`

Local DDEV only. Do not apply production. Do not send live volunteer
mail.

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md

## After implement (not this command)

1. `ddev exec php command.php rebuild` if hook/Tool PHP changed.
2. `ddev exec vendor/bin/phpunit tests/unit/Espo/Modules/NonprofitEspocrm/ContactUserChannelSyncTest.php`
3. Owner UAT on local Rossella (`Fagioli Rosa` / `fagioli_rosa`):
   - Put a second email and a second phone on the Contact. Save. Open
     the User: both sets match, including primary.
   - Change first name on the Contact. Save. User first name matches.
   - Change last name on the User. Save. Contact matches.
   - Clear the Contact email and save: User login email stays.
   - Put the User’s existing email back on the Contact: save succeeds.
4. Volunteer pair: one email change still copies (004.1 regression).
5. Help-seeker email change does not rewrite a User.

PDF and convert are already OK; do not retest as this feature.

## Out of this quickstart

- Production rebuild / rsync
- `006` magic links
- Recreating table `member`
