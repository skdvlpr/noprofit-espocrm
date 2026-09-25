# Data model: board answers on the contact

Contact gains the same board fields the lead already has. Names and types match so convert copies the values.

| Field | Type | Notes |
|-------|------|--------|
| admissionBoardDate | date | Meeting date |
| admissionOutcome | enum `""`, Approved, Rejected | Empty allowed |
| memberBookNumber | varchar | |
| admissionFeePaid | enum `""`, Yes, No | Empty allowed |
| admissionReceiptNumber | varchar | |
| newsletterConsent | enum `""`, Yes, No | Empty allowed |
| admissionForm | file | Already on Contact. One file, also still on the Lead |

The Consiglio Direttivo panel and the PDF preview are visible only when the contact type includes Associato (`MemberContact`).

Convert does not clear the lead file. A non-Associato contact does not show the panel.

No new entity.
