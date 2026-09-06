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

**Aceptación de Términos + firma (2026-09-05, rama
`feat/cumpleclick-aceptacion-terminos`, LOCAL only).** A party can only be set
`activa` when `cc_plan_acceptances` has an `accepted` (client signed via
`aceptar-plan.php?t=`) or `waived` (explicit demo/internal exemption) row. Code:
`public/lib.acceptance.php`, `public/admin/aceptaciones.php`, legal drafts in
`public/legal/*.md` (versioned, hash fail-closed, lawyer review pending).
Blueprint + FTP order: `Docs/BLUEPRINTS/CUMPLECLICK-ACEPTACION-TERMINOS-Y-FIRMA.md`
(root repo). Migration `013` numbered to avoid clashing with 008–012 living in
other worktrees.

**PROD status (corrected 2026-08-03).** PROD *is* deployed, since 2026-07-27, at
`https://automatizatech.cl/cumpleclick/`. What is **not** deployed is the
2026-08-02 closure — no merge, no FTP. So PROD runs the 2026-07-27 build, and
nothing from that closure may be described as live. The previous line here said
"PROD has not been deployed", which was wrong and is kept noted so the error is
not reintroduced. Never claim something is in PROD without evidence.

**Juego 3D "Tu Cumple en 3D" (2026-09-06, ramas `feat/cumpleclick-sala-ayudantes`, LOCAL only).**
The 3D birthday game lives in a separate repo (`C:\wamp64\www\tucumple-repo\`, Higgsfield-hosted for
demos only: `*.higgsfield.app` returns 401 to anonymous visitors, so at parties it is served from WAMP on the
LAN). CumpleBooth is its backend: `public/sala.php` + `public/lib.sala.php` + migration
`014_salas_ayudantes` (phase 3: guests send animo/copos/ayuda from their phones via QR; phase 4:
`op=fotos` lists the party's kiosk photos for the game, gated by the gallery PIN, and `crear` returns the
server's LAN IPs for the QR). The game uploads its own 1080x1920 memories through `upload.php` like any
booth photo and reads the party through `api.php?p=`. Contract: `tucumple-repo/app/design/sala-api.md`.
Tests: `tests/backend/salas.php` (73 checks). Tickets: `Docs/ORCHESTRATION/AT-CUMPLECLICK-014/015.yaml`.
Local test party `prueba-3d` (theme hielo, gallery PIN 1234) exists only in the dev DB.
