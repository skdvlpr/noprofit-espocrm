# Feature Specification: Website admission leads and socio PDF

**Feature Branch**: `005-website-lead-admission`

**Created**: 2026-09-22

**Status**: Draft

**Input**: Owner: the Safe House website already creates a Lead after a
successful «Diventa socio» email (best-effort). Prepare the CRM so that
Lead is visible and printable as the official *Domanda di ammissione a
socio*, and so a later «Diventa volontario» step can create a Volunteer
Lead with the same rules. Do not add contact types. Do not change the
website. Conversion to Contact stays manual. The admission PDF exists
only for Associato, is filled by staff for the board section, and moves
to the Contact on convert.

Parent: [`../004.3-contact-types-lead-convert/spec.md`](../004.3-contact-types-lead-convert/spec.md).

Research cite (Espo native; local clone opened this turn):
https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/layout-manager.md
https://github.com/espocrm/documentation/blob/master/docs/development/api.md

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Socio application is a readable Lead (Priority: P1)

A person applies on the site as **Diventa socio**. The site already
sends the email first. It then tries to create one Lead. Staff open
that Lead and see the application, not an empty type-only card.

The Lead type is **Associato** (`MemberContact` — label already
*Associato*). One type. The card shows, next to the type:

- name, email, phone;
- street, city, province (`addressState`), postal code, country Italy;
- **Codice Fiscale**, **Data di nascita**, **Luogo di nascita**,
  **Prov. di nascita**;
- description (statute accepted, mission accepted, fee commitment,
  newsletter *acconsente* or *non acconsente*);
- source **Web Site**, status **New**;
- assigned user when the site integration has one configured.

Codice Fiscale on the Lead is shown as received (16 letters or digits,
uppercase). The Lead does **not** apply the stricter Italian fiscal-code
check used on Contact. A code the site accepted must not be rejected
here. A code that is not a real fiscal code stays visible so staff can
see it before conversion.

None of address, fiscal code, or birth fields are required on every
Lead. Volunteer applications do not have them.

Creating this Lead does **not** create a Contact. Earlier automatic
Contacts duplicated people.

**Why this priority**: Today the site can lose the Lead silently after
the email succeeds, and staff cannot see fiscal code or birth data on
the card even when the Lead is stored.

**Independent Test**: Create a Lead with the socio field set and type
Associato only. The detail and edit cards show Codice Fiscale, Data di
nascita, Luogo di nascita, and Prov. di nascita beside the type. No
Contact is created. A 16-character code that is not a valid Italian
fiscal code still saves on the Lead.

**Acceptance Scenarios**:

1. **Given** a new Lead with type Associato and the socio fields filled,
   **When** staff open detail or edit, **Then** fiscal code, birth date,
   birth place, and birth province are visible with the Italian labels
   above, next to the type.
2. **Given** that Lead, **When** it is saved, **Then** no Contact exists
   yet.
3. **Given** fiscal code `AAAAAA00A00A000A` (16 letters/digits, not a
   real Italian code), **When** it is saved on the Lead, **Then** save
   succeeds and the value is shown unchanged except uppercase.
4. **Given** a Lead with no fiscal code and no birth data, **When** it
   is saved, **Then** save succeeds.

---

### User Story 2 - The website user may create that Lead (Priority: P1)

The user account the site already uses must be allowed to create a Lead
and to write every socio field the site already sends, including
assigned user when that id is sent. It must not need permission to edit
the board section.

If the CRM refuses a payload the site already sends, the email has
already gone out and the Lead disappears with no message to the
applicant. This feature makes a correct socio payload succeed.

**Why this priority**: The site will not be changed in this feature. The
CRM has to accept the payload that already exists.

**Independent Test**: As the website integration user, create a Lead
with the socio payload. The Lead exists, status New, source Web Site,
type Associato. The same user cannot set the board decision.

**Acceptance Scenarios**:

1. **Given** the existing website integration user, **When** they create
   a Lead with the socio fields, type Associato, source Web Site, status
   New, **Then** the Lead is stored.
2. **Given** that user, **When** they also send an assigned user id the
   integration is configured with, **Then** the Lead is assigned to that
   user.
3. **Given** that user, **When** they try to set the board decision,
   **Then** they cannot.

---

### User Story 3 - Volunteer application can use the same card later (Priority: P2)

The site does **not** create a Volunteer Lead yet; it only sends email.
The Lead card must already accept what the next site step will send:

- first name, last name, email, phone;
- description = the candidate’s message;
- type **Volontario** only (`Volunteer`);
- source **Web Site**, status **New**.

No address, fiscal code, or birth date. Those stay optional so this
payload saves, and so a socio Lead is not blocked by volunteer rules.
Employee is not part of either website application.

**Why this priority**: Socio is live. Volunteer should not require a
second card redesign, but it must not make socio fields mandatory.

**Independent Test**: Create a Lead with only the volunteer field set.
It saves. No admission PDF. No Contact.

**Acceptance Scenarios**:

1. **Given** type Volontario only plus name, email, phone, and a
   message, **When** the Lead is saved, **Then** it is stored and the
   message is in the description.
2. **Given** that Lead, **When** it is saved without address or fiscal
   code, **Then** save succeeds and no admission PDF is attached.
3. **Given** a website application, **When** staff look at the type,
   **Then** it is Associato or Volontario, not Dipendente.

---

### User Story 4 - Staff convert after review, and the strict code check happens then (Priority: P1)

A staff member converts the Lead to a Contact only after they have
checked it. Conversion copies onto the Contact, into the fields that
already exist there:

- type (Associato and/or Volontario as stored on the Lead);
- address;
- Codice Fiscale, birth date, birth place, birth province.

The Contact keeps today’s Italian fiscal-code check. If the code on the
Lead is not a real fiscal code, conversion fails and the person stays a
Lead so staff can correct it. A valid code is copied and the Contact
saves.

Account and Opportunity checkboxes stay as they are today. This feature
does not create a Contact at Lead-create time.

**Why this priority**: Duplicates came from creating the Contact too
early. The strict code check belongs on the person record, where staff
can see the problem on the Lead first.

**Independent Test**: Convert an Associato Lead with a valid fiscal code.
The new Contact has the same type, address, and birth fields. Convert
the same shape with an invalid 16-character code: no Contact, Lead
unchanged, staff see the failure.

**Acceptance Scenarios**:

1. **Given** an Associato Lead with a valid fiscal code and birth data,
   **When** staff convert to Contact, **Then** the Contact has Associato,
   the same address, fiscal code, birth date, birth place, and birth
   province.
2. **Given** an Associato Lead whose fiscal code is 16 letters/digits
   but not a valid Italian code, **When** staff convert, **Then** no
   Contact is created and the Lead remains.
3. **Given** a new Lead of either website type, **When** it is first
   saved, **Then** zero Contacts are created by that save.
4. **Given** a Volontario Lead with no birth data, **When** staff
   convert, **Then** the Contact is Volontario and the empty birth
   fields stay empty.

---

### User Story 5 - Associato Lead prints the official admission form (Priority: P1)

For a Lead whose type includes Associato, the CRM produces one PDF that
matches the official module *Domanda di ammissione a socio* (Safe House
ETS). Letterhead is fixed text, not data from the Lead:

- title **DOMANDA DI AMMISSIONE A SOCIO**;
- SAFE HOUSE ETS, Codice Fiscale 96629270586, RUNTS Rep. n. 156768;
- Sede legale Via Delleani 26, 00042 Anzio (RM); sede operativa Torino
  (Piemonte).

Section 1 prints the Lead: sottoscritto/a (name), nato/a a and province,
birth date, C.F., residente a and province, street, CAP, phone, email.

Section 2 prints the three fixed declarations (mission and values;
statute and regulations; annual fee). The site only sends the Lead when
the applicant has accepted all three, and the description records that.

Section 3 prints the GDPR notice and marks **ACCONSENTO** or **NON
ACCONSENTO** from the description line `Newsletter: acconsente` or
`Newsletter: non acconsente`. The applicant date and **Firma del
Richiedente** stay blank lines for a person to sign.

The board section **RISERVATO AL CONSIGLIO DIRETTIVO** prints the staff
fields from User Story 6, or empty boxes when they are not filled yet.
**Firma del Presidente (Matteo Grossi)** stays a blank signature line.
The president’s name is printed as on the paper form; it is not a
separate person to pick.

Volunteer-only Leads do not get this PDF. One current PDF per Associato
Lead: a later save replaces it rather than stacking copies. The PDF is
produced when the Lead is created by the site as well as when staff
save it in the CRM.

After the board fields are filled, the PDF shows those values. A person
may download or print it and send it for signature. This feature does
not email the PDF and does not collect an electronic signature.

**Why this priority**: The paper module is what the board expects. Staff
should not retype the application into a word processor.

**Independent Test**: Save an Associato Lead whose description contains
`Newsletter: acconsente`. Open the PDF: anagrafica matches the Lead, the
consent box is ACCONSENTO, NON ACCONSENTO is empty, signature lines are
blank. A Volontario Lead has no such PDF.

**Acceptance Scenarios**:

1. **Given** an Associato Lead with name, address, birth data, fiscal
   code, and `Newsletter: acconsente` in the description, **When** the
   PDF is opened, **Then** section 1 matches those fields and only
   ACCONSENTO is marked.
2. **Given** `Newsletter: non acconsente`, **When** the PDF is opened,
   **Then** only NON ACCONSENTO is marked.
3. **Given** a Volontario-only Lead, **When** it is saved, **Then** no
   admission PDF is attached.
4. **Given** an Associato Lead that is saved again, **When** staff look
   at attachments, **Then** there is still one admission PDF, showing
   the latest data.
5. **Given** the PDF, **When** staff read the signature lines, **Then**
   applicant and president lines are blank and the president line names
   Matteo Grossi.

---

### User Story 6 - The board fills the reserved section without contradictory ticks (Priority: P1)

On an Associato Lead, an administrator or a person who already has the
Member role sees a block for the part of the form the website does not
collect:

- date of the board meeting (*Domanda esaminata nella seduta del*);
- outcome: **Approvata** or **Respinta**, or still empty;
- number in the members’ book (*Annotazione nel Libro Soci*);
- fee paid: **Sì** or **No**, or still empty;
- receipt / card number (*Ricevuta/Tessera N.*).

Approvata and Respinta cannot both be set. Sì and No cannot both be set.
Choosing one clears the other. Empty is allowed until the board has
met. The block is hidden for Leads that are not Associato.

The website integration user cannot edit this block. Other staff who
are neither admin nor Member cannot edit it.

When any of these values change, the admission PDF updates.

**Why this priority**: The reserved section is the only part still
missing after the site application. Contradictory ticks would make the
printed module false.

**Independent Test**: As admin, on an Associato Lead set Approvata, then
try Respinta: only Respinta remains. Set quota Sì, then No: only No
remains. The PDF shows the latest single choices. A Volontario Lead does
not show the block.

**Acceptance Scenarios**:

1. **Given** an Associato Lead, **When** an admin sets the meeting date,
   Approvata, a book number, Sì, and a receipt number, **Then** those
   values are stored and the PDF shows them in the reserved section.
2. **Given** outcome Approvata, **When** they set Respinta, **Then** the
   outcome is only Respinta.
3. **Given** fee Sì, **When** they set No, **Then** the fee is only No.
4. **Given** a Volontario-only Lead, **When** a Member opens it, **Then**
   the board block is not shown.
5. **Given** the website integration user, **When** they create the
   socio Lead, **Then** the board fields stay empty.

---

### User Story 7 - Conversion moves the PDF onto the Contact (Priority: P1)

When staff convert an Associato Lead into a Contact that is also
Associato, the admission PDF is attached to that Contact and removed
from the Lead. The Contact does not keep a second copy on the Lead.

If the Lead is not Associato, there is no admission PDF to move. If
staff convert in a way that the new Contact is not Associato, the PDF
stays on the Lead.

Volunteer data on a mixed Associato+Volontario Lead still copies as in
004.3. The PDF rule follows Associato only.

**Why this priority**: The signed module belongs with the person, not
with the prospect, once staff have accepted the conversion.

**Independent Test**: Convert Associato → Contact Associato. The Contact
has the PDF; the Lead no longer does. Convert Volontario only: no PDF
on either record from this feature.

**Acceptance Scenarios**:

1. **Given** an Associato Lead with an admission PDF, **When** staff
   convert to an Associato Contact, **Then** that PDF is on the Contact
   and not on the Lead.
2. **Given** a Volontario-only Lead, **When** staff convert, **Then**
   neither record gains an admission PDF.
3. **Given** the moved PDF, **When** staff open it from the Contact,
   **Then** it is the same module, including board values that were
   filled before conversion.

---

### Edge Cases

- The site has already sent the email. A CRM rejection still drops the
  Lead on the site side. This feature’s job is that a payload the site
  already sends is accepted. Changing the site is out of scope.
- Description without a `Newsletter:` line: both consent boxes stay
  empty; the rest of the PDF still prints.
- Fiscal code with spaces: stored uppercase without spaces when the
  value is only letters, digits, and spaces. The Lead still does not
  run the Contact fiscal-code check.
- Fiscal code longer than 16 characters, or with other symbols: the Lead
  still saves and shows it. Conversion to Contact fails the existing
  Contact check.
- Board outcome and fee may stay empty. The PDF prints empty boxes.
- Saving the board section never sets both outcomes or both fee answers.
- Replacing the PDF does not delete unrelated Lead attachments.
- Conversion failure (invalid fiscal code, duplicate email, cancelled
  user review) leaves the PDF on the Lead.
- Employee is not set by these applications. Existing legal pairs
  (Volontario+Associato, Dipendente+Associato) stay as in 004.3 if staff
  add a second type by hand. The PDF is produced whenever Associato is
  one of the types.
- Assigned user omitted: the Lead is simply unassigned.
- Production is not updated by this feature until the owner names that
  deployment. No live volunteer or applicant mail is sent from the CRM.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: A Lead created for Diventa socio MUST be type Associato
  only, source Web Site, status New, and MUST show on detail and edit,
  next to the type, Codice Fiscale, Data di nascita, Luogo di nascita,
  and Prov. di nascita, plus address, phone, email, and description.
- **FR-002**: Those birth and fiscal-code fields MUST NOT be required
  on Lead. A Volunteer Lead without them MUST save.
- **FR-003**: Lead MUST NOT apply Contact’s Italian fiscal-code check.
  A 16-character uppercase letter/digit code MUST save on the Lead even
  when it is not a valid Italian fiscal code. Contact MUST keep that
  stricter check at conversion.
- **FR-004**: Saving a new Lead MUST NOT create a Contact.
- **FR-005**: The existing website integration user MUST be able to
  create a Lead and write first name, last name, email, phone, street,
  city, province, postal code, country, fiscal code, birth date, birth
  place, birth province, type, source, status, description, and
  assigned user. That user MUST NOT be able to write the board fields.
- **FR-006**: A Volunteer website payload (name, email, phone,
  description, type Volontario, source Web Site, status New) MUST save
  with no address and no birth data, and MUST NOT produce an admission
  PDF.
- **FR-007**: Manual conversion MUST copy type, address, fiscal code,
  birth date, birth place, and birth province onto the new Contact.
  Invalid fiscal code on that check MUST block the Contact and leave
  the Lead unconverted.
- **FR-008**: An Associato Lead MUST have one current admission PDF
  matching the official module: fixed letterhead and section 2–3 legal
  text, section 1 from the Lead, newsletter boxes from the description
  line `Newsletter: acconsente` or `Newsletter: non acconsente`, blank
  applicant and president signature lines, president line labelled
  Matteo Grossi.
- **FR-009**: Regenerating the PDF MUST replace the previous admission
  PDF and MUST leave other attachments in place. Volunteer-only Leads
  MUST NOT receive this PDF.
- **FR-010**: Associato Leads MUST show a board block editable by an
  administrator or by a user with the Member role: meeting date;
  outcome Approvata or Respinta or empty; members’ book number; fee
  Sì or No or empty; receipt/card number. The two outcomes MUST be
  mutually exclusive. The two fee answers MUST be mutually exclusive.
- **FR-011**: Changing the board block MUST refresh the admission PDF.
  The website user and users who are neither admin nor Member MUST NOT
  edit the block. The block MUST be hidden when the Lead is not
  Associato.
- **FR-012**: On conversion, when both the Lead and the new Contact
  include Associato, the admission PDF MUST be attached to the Contact
  and removed from the Lead. Otherwise it MUST stay where it is.
- **FR-013**: This feature MUST NOT add contact-type values, MUST NOT
  change the website, and MUST NOT send the PDF by email or collect an
  electronic signature. A person downloads or prints the finished PDF
  and may send it for signature themselves.
- **FR-014**: No new fields beyond the socio payload, the volunteer
  payload, and the five board values. Signature dates and signature
  images are not stored; they remain blank lines on the PDF.

### Key Entities

- **Lead**: Website prospect. Associato (socio) or Volontario. Holds
  the application text and, for Associato, the board section and one
  admission PDF.
- **Contact**: Person created only when staff convert. Receives the
  copied identity fields and, for Associato, the admission PDF.
- **Admission PDF**: One printed module per Associato Lead, then moved
  to the Associato Contact. Not used for Volontario-only.
- **Website integration user**: Existing account the site uses to
  create Leads. Create and the socio/volunteer fields only.
- **Board editor**: Administrator, or a user who has the Member role.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In owner tests, a socio application identical to what
  the site already sends is stored as one Associato Lead, and staff see
  fiscal code and birth fields on the card.
- **SC-002**: That save creates 0 Contacts. A Volunteer payload with no
  address and no birth data also saves, with 0 admission PDFs.
- **SC-003**: A 16-character non-Italian code saves on the Lead and
  fails conversion, leaving 0 new Contacts, in owner tests.
- **SC-004**: The Associato PDF matches the official module section by
  section (letterhead, anagrafica, three declarations, one newsletter
  box, blank signatures, board section). After a board edit there is
  still exactly one admission PDF, and it shows the new values.
- **SC-005**: Approvata+Respinta and Sì+No cannot be stored together.
  In owner tests, setting the opposite leaves exactly one choice.
- **SC-006**: After converting Associato to Associato, the Contact has
  the PDF and the Lead has zero admission PDFs. A Volontario conversion
  adds no admission PDF.
- **SC-007**: Staff can open the finished PDF and hand it to a person
  for signature without retyping. The CRM does not send that email
  itself.

## Assumptions

- The site already creates the socio Lead only after both emails
  succeed, and ignores CRM errors. This feature does not change that
  site. The description lines are already:
  `Domanda di ammissione socio dal sito.`, `Statuto accettato: sì.`,
  `Mission accettata: sì.`, `Quota: impegno al versamento.`, and
  `Newsletter: acconsente` or `Newsletter: non acconsente`.
- Fiscal code on the site is 16 characters `A–Z` / `0–9` after
  uppercasing. That is not the Contact Italian fiscal-code check.
- `addressState` is the residence province. `birthProvince` is the
  birth province. Country sent by the site is Italy. The paper form
  has no country row, so the PDF does not add one.
- Associato means contact type MemberContact. Volontario means
  Volunteer. Labels in Italian already exist. No new type values.
- Legal type pairs from 004.3 stay. These website applications send
  one type. Employee is not sent.
- “Member” who may edit the board block means the existing Member
  role, or an administrator. No new role.
- Matteo Grossi is the printed president name on the signature line,
  matching the current paper module. Changing that name later is a
  template text change, not a new field.
- Signature and “send for signature” are done by a person outside this
  feature. Blank lines are the signature method.
- 004.3 owner UAT is still open. The owner ordered this feature in the
  same instruction as committing 004.3. This spec does not close 004.3.
- Production apply and any live mail from the CRM wait until the owner
  names them.
- The website repository is not modified.
