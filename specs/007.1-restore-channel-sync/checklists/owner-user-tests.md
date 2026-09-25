# Owner checks: contact and user identity stay in step

Local DDEV `https://nonprofit-espocrm.ddev.site`. Hard-refresh. Do not
apply production.

Person: Rossella / Fagioli Rosa, Associato, linked CRM user `fagioli_rosa`.

- [ ] V1 Open the contact. Add a second email and a second phone. Save.
      Open the user: both sets match, including which row is primary.
- [ ] V2 Change first name on the contact. Save. User first name matches.
- [ ] V3 Change last name on the user. Save. Contact last name matches.
- [ ] V4 Prefix (salutation) on either card updates the other after save.
- [ ] V5 Clear the contact email and save. User login email stays. Put
      the user’s existing email back on the contact. Save succeeds.
- [ ] V6 Volunteer (or Employee) linked pair: one email still copies
      (004.1 must not regress).
- [ ] V7 Help-seeker email change does not rewrite a user.

PDF and convert are already OK; skip.

Cite: [quickstart.md](../quickstart.md)
