# Data model

No new entity. Contact already has the six board fields and `admissionForm` from 005.1.

Rebuild copy, only when the contact value is empty:

| From Lead | To Contact |
|-----------|------------|
| admissionBoardDate | admissionBoardDate |
| admissionOutcome | admissionOutcome |
| memberBookNumber | memberBookNumber |
| admissionFeePaid | admissionFeePaid |
| admissionReceiptNumber | admissionReceiptNumber |
| newsletterConsent | newsletterConsent |
| admissionFormId / Name | same, if contact has no file |

Only when Lead status is Converted, `createdContactId` is set, and the contact type includes Associato.
