# Data model: personal availability link

## AvailabilityAccessLink (new)

One row per issued link. Not a CRM login. Not shown on volunteer layouts.

| Field | Rule |
|-------|------|
| name | Internal label, not secret |
| activityOffer | Required link to the week plan |
| user | Required link to the volunteer user |
| tokenHash | Required. Hash of the secret. Never the secret itself |
| expiresAt | Required. Creation time plus 7 days |
| replacedAt | Empty until a newer link is issued for the same user and plan |

**Live** when `replacedAt` is empty and `expiresAt` is in the future.

**Transitions**:

- Create (send or resend) → live. Any previous live row for the same
  user and plan becomes replaced at the same moment.
- Each tick on a live link updates that volunteer’s availability and
  leaves the link live.
- Clock past `expiresAt` → dead. No write. Row stays for audit.
- Resend → previous live row replaced; new row live.

A volunteer with no email gets no row.

## ActivityInvite (existing answer)

Unchanged meaning: one volunteer’s “I can do this shift” on one slot,
plus the comment. The public submit writes the same records the logged-in
save writes today, for the user on the link, limited to published shifts
whose category is in that user’s competences.

Two browsers ticking the same shift: the later tick wins. The link stays
live until it expires or is replaced.

## ActivityOffer (existing)

No new status. Sending still requires at least one published shift and
the current audience rules (`requestAvailability` /
`requestAvailabilityForUsers`).

## What is not stored

The email secret, the full public URL, and other volunteers’ names on
the public page.
