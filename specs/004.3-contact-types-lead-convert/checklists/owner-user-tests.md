# Owner user tests: 004.3 contact types, Lead convert, unique Contact email

**Feature**: `004.3-contact-types-lead-convert`  
**Environment**: DDEV `https://nonprofit-espocrm.ddev.site`  
**Language**: Italian UI labels  
**Prod / live volunteer mail**: Skip until the owner names them

Cite:
https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md

Mark each step Pass / Fail / Skip. Attach a screenshot on Fail.

## Setup

- [ ] Logged in as Espo **admin**
- [ ] Soft rebuild already applied on DDEV
- [ ] `copyContactTypeEnumToMulti --apply` already run (Volunteer Contacts show type as a one-item list)
- [ ] Roles named Volunteer, Employee, Member exist
- [ ] Contact email collisions listed in progress; colliding rows Skip or cleaned

## Flows

1. **Volunteer + Member (US1 + US2)**  
   Create Contact: types Volontario + Associato. Both panels visible; Codice Fiscale / nascita once on Overview. Crea utente CRM on → review shows Roles Volunteer **and** Member → Confirm.  
   Filters **Volontari** and **Associati** both list the person.  
   Result: Pass / Fail / Skip

2. **Illegal mix (US1)**  
   Volunteer + Employee refused. Member + Help-seeker refused.  
   Result: Pass / Fail / Skip

3. **Member-only create user (US2)**  
   Associato only + Crea utente CRM → review Role Member (same 004.2 panel).  
   Result: Pass / Fail / Skip

4. **Non-admin (US1)**  
   Staff login cannot add Volunteer or Employee.  
   Result: Pass / Fail / Skip

5. **Add Member on existing Volunteer+User (US2)**  
   Edit: no Crea utente CRM. Save. Linked User gains Role Member; no second User.  
   Result: Pass / Fail / Skip

6. **Lead Volunteer convert + user (US3–US4)**  
   Lead type Volontario only (no volunteer panels on Lead). Convert: Contact panel shows volunteer fields. Crea utente CRM on → review → Contact Volunteer + User Volunteer; Lead Converted.  
   Result: Pass / Fail / Skip

7. **Cancel review on convert / create (US4)**  
   Convert with checkbox on, dismiss review. Lead status unchanged; 0 new Contact; 0 new User. Convert again: review reopens. Same on Contact create Save after closing the review.  
   Result: Pass / Fail / Skip

8. **Lead Generic (US3–US4)**  
   Tipo Generico; convert checkbox off → Contact Other; no User.  
   Result: Pass / Fail / Skip

9. **Duplicate Contact email (FR-014)**  
   Second Contact with an email another Contact already uses → save/convert fails (not skippable). Linked Contact + its User may share the same email.  
   Result: Pass / Fail / Skip

10. **User mirror (US5)**  
    Volunteer+Member Contact: User detail shows competences and joinDate; shared tax/birth once; fields read-only.  
    Result: Pass / Fail / Skip

11. **Create Volunteer/Employee type sticks (FR-015 / SC-009)**  
    `#Contact/create`: select only Volontario — type stays, volunteer fields and Crea utente CRM appear. Repeat with only Dipendente. Then Associato + Volontario both stay.  
    Result: Pass / Fail / Skip

12. **Lead two types copy on convert (FR-007 / FR-008)**  
    Lead Volontario + Associato (no extra panels on Lead). Convert: Contact has both types and both field sets. Dipendente option exists; Volunteer+Employee refused.  
    Result: Pass / Fail / Skip

13. **Convert empty Tipo contratto (FR-016)**  
    Volunteer or Volunteer+Member convert, fill visible fields, confirm user review. Convert succeeds (no backend `contractType` / `valid` error). Contact + User exist.  
    Result: Pass / Fail / Skip

14. **User Is Active off (FR-017)**  
    On that User, uncheck **Attivo** / Is Active and save. Linked Contact **Stato** is Inactive. Contact is not deleted.  
    Result: Pass / Fail / Skip

## Skip (owner-named)

- Production apply of 004.3  
- Live volunteer access-info mail  
- Merging colliding Contact emails
