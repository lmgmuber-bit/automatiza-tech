# CumpleClick shared memory

Follow `AGENTS.md`. The canonical entry point is
`docs/HANDOFF-OTRA-PC-2026-08-02.md` — read it first; if an older handoff
contradicts it, that closure wins. Then `docs/CUMPLECLICK-HANDOFF-CODEX.md`;
current architecture/deploy are in `docs/ARQUITECTURA.md` and `docs/DEPLOY.md`.

> The canonical handoff is **not in the local working tree** on this machine
> (the local branch is one commit behind `origin`). Read it with:
> `git show origin/codex/cumpleclick-site-frontend-fixes:CumpleBooth/docs/HANDOFF-OTRA-PC-2026-08-02.md`

Ticket `AT-CUMPLECLICK-001` introduced the independent DB, secure PHP backend,
persistent frames, local Baloo 2, AT branding, private tokenized photos and safe
rollback/retention.

**PROD status (corrected 2026-08-03).** PROD *is* deployed, since 2026-07-27, at
`https://automatizatech.cl/cumpleclick/`. What is **not** deployed is the
2026-08-02 closure — no merge, no FTP. So PROD runs the 2026-07-27 build, and
nothing from that closure may be described as live. The previous line here said
"PROD has not been deployed", which was wrong and is kept noted so the error is
not reintroduced. Never claim something is in PROD without evidence.
