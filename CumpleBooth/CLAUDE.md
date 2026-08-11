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

**Invitación: portada/música/narración de Alice + multi-tema (2026-08-11,
local, sin deploy).** Ver `docs/INVITACION-MUSICA-Y-NARRACION-ALICE.md`
(canónico para esto). Portada "sobre que se abre" + música de fondo + pie con
logo/link/favicon: hechos por Claude, genéricos por tema. Multi-tema
(`$playlistOrdersByTheme`, hielo con 7 videos y nombre dinámico): hecho por
Codex, ver `docs/CODEX-HANDOFF-INVITACION-HIELO-2026-08-11.md`. Falta SOLO la
narración de audio real (ElevenLabs, voice_id `Xb7hH8MSUJpSbSDYk0k2`) para
ambos temas — código ya resiliente sin ella. Gotcha de entorno local:
`event_profile_enabled` debe ir en `true` (default `false`) o la ficha del
cumpleañero nunca aparece; usar `config/cumpleclick.local.php` (gitignored)
en vez de env vars para no reiniciar el servidor de pruebas.
