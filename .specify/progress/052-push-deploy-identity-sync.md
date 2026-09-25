# 052 — Push 007.1 and 005.x to production via CI

**Date:** 2026-09-25  
**Feature:** `specs/007.1-restore-channel-sync` (+ 005.1/005.2 already UAT’d)

Owner asked to push and deploy. Rsync is code from GitHub, not the local
DDEV database. `deploy/rsync-excludes.txt` keeps `data/` server-owned.
Do not copy local contacts/users onto production.

Owner UAT: local identity sync and PDF/convert OK. CI deploy after tests;
do not wait on Actions in this turn.

Prod contact/user field audit waits until the owner asks after deploy.
