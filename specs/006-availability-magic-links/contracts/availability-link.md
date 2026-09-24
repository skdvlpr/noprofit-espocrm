# Contract: availability link

The public page is an Espo entry point, not a new REST resource and not
a CRM session.

Cite: https://github.com/espocrm/documentation/blob/master/docs/development/entry-points.md

## Open

`GET ?entryPoint=shiftAvailability&token={secret}`

| Condition | What the person sees |
|-----------|----------------------|
| Live link | Only the CRM availability dialog: shift ticks, nothing else (no menu, description, comment, Save, Cancel). Full width on a phone. |
| Replaced, expired, or unknown | Italian message that the link is no longer valid. No dialog. No data change. |

## Submit

`POST` same entry point with the same secret, the checked shift ids, and
the optional comment.

| Condition | Result |
|-----------|--------|
| Live, plan still collecting or planned | Stores that tick immediately. Link stays live. A short saved confirmation. The dialog stays open. |
| Not live | Same invalid-link message. Availability unchanged. |
| Shift outside their competences | Ignored, same as the current save. The tick does not stick. |

The secret is not written to the log.

## Staff send and resend (already authenticated)

Unchanged actions:

- Request availability for the plan’s current audience.
- Resend to `userIds` (and optional shift ids) already implemented on
  the plan.

Added result for both: each emailed person has a new live link; their
previous live link is replaced; people not in the send are not emailed
and keep their link.

Skipped (no address, inactive) stay visible to staff as they are today.
Those people get no link.
