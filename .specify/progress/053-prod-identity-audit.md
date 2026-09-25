# 053 — Production identity audit (no local data copy)

**Date:** 2026-09-25  
**Feature:** `specs/007.1-restore-channel-sync`

Read-only audit then safe fills on **production pairs only**. DDEV rows were
not copied.

18 linked Contact/User pairs. Names already matched. Copied missing phones
from Contact onto User (12 succeeded after leftover Member metadata was
removed). One email conflict left on purpose: Rossella contact stub vs User
gmail. Maria Carla still points at a **deleted** User (`maria.carla.caracciolo`).

Leftover `Member` / `VolunteerEmployee` module files on the server (rsync
without delete) made User save query dropped tables. Those files were
removed; rebuild; scopes now null. Tables `member` / `volunteer_employee`
were already gone.

## Next

Owner: put Rossella’s real address on the Contact, or restore/clear Maria
Carla’s deleted User. Do not enable rsync `--delete` unless named.
