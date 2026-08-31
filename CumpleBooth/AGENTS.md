# CumpleClick agent context

## Shared memory (read first — found missing 2026-07-25)

This file used to be a closed loop: CLAUDE.md says "follow AGENTS.md", OPENCODE.md
says "use AGENTS.md as project rules", `.github/copilot-instructions.md` says
"read ../AGENTS.md" — and none of them ever pointed outside `CumpleBooth/`. Any
agent invoked with this folder as its working directory never saw the shared
vault or the orchestration board, and worked blind to what other agents had
already done. Fixed by adding this section; keep it if you edit this file.

Before changing anything, read:

1. `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\10-Projects\CumpleClick.md` —
   what the product is, architecture, current real state, hard rules, lessons
   learned. This is the shared source of truth across Claude/Codex/OpenCode/Copilot.
2. `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\Multi-Agent-Workflow.md`
   — how agents coordinate (branches, locks, session closing format).
3. `..\Docs\ORCHESTRATION\current-handoff.yaml` and `..\Docs\ORCHESTRATION\AT-CUMPLECLICK-*.yaml`
   (paths relative to this folder, i.e. inside `automatiza-tech\Docs\ORCHESTRATION\`)
   — the task board. **It has been found stale before** (a ticket described work
   already finished days earlier); if it contradicts what you find in the
   filesystem, trust the filesystem and report the mismatch, don't silently redo
   finished work.
4. The knowledge graph: this folder has its own scoped one at
   `graphify-out\graph.json` (660 nodes, CumpleBooth only, dated 2026-07-18 —
   run `graphify` commands from here and it's picked up automatically). There is
   also a repo-wide one at `..\graphify-out\graph.json` (4,331 nodes, all of
   `automatiza-tech`, dated 2026-07-16) if you need context outside this folder.
   Query with `graphify query "<question>"` instead of grepping raw files for
   orientation. Both predate most of the recent work (see the vault note in #1
   for the real current state) — treat either as a map, not as current-state
   truth, and rebuild (`graphify extract . --no-cluster`) if relying on it heavily.

When you close a session, update #1 and the relevant ticket in #3, and add a
closing entry to the vault's `50-Daily-Logs/`. This is what lets the next agent
continue instead of starting blind.

---

- Read `docs/CUMPLECLICK-HANDOFF-CODEX.md`, `docs/ARQUITECTURA.md` and
  `docs/FASE1.md` before changes.
- Álbum Recuerdo (branch `feat/album-recuerdo`, 2026-08-04): design and
  decisions in `docs/ALBUM-RECUERDO-PROPUESTA.md`, manual validation steps in
  `docs/ALBUM-RECUERDO-PRUEBAS.md`. Vocabulary is deliberately generic
  (`event_album`, `event_media`, `contributor_*`) so weddings/baby showers can
  reuse it; do not tie it back to birthdays. Booth photos are referenced, never
  copied.
- Public identity/path: CumpleClick by AutomatizaTech, `/cumpleclick/`;
  technical project name remains CumpleBooth.
- PHP 8.0+ (baseline 8.2), MySQL/InnoDB/utf8mb4 and an independent database.
- `public/` and `src/` are source; `dist/` is generated once after integration.
- Never version or deploy real config, passwords, HMAC keys, backups or photos.
- Keep mutable storage outside DocumentRoot and URLs independent of HTTP_HOST.
- Frames come from API/DB; do not restore localStorage calibration.
- If touching an image prompt, describe only physical traits: never name a
  character or franchise. Ask Luis before spending a generation if uncertain.
- Run frontend/backend tests, build, dist parity and Chrome QA proportionally.
- Do not deploy, merge or upload to PROD without Luis's approval.

## Deploy: git y FTP son dos acciones, no una (added 2026-08-31)

Read `docs/DEPLOY.md`, first section, BEFORE shipping anything.

Merging to `main` does NOT publish. The site is served from `public_html` on
Hostinger, filled over FTP; git keeps history, FTP publishes. Something being on
`main` does not mean it is on cumpleclick.com, nor the other way round.

The loop: branch off `main` -> commit with tests green -> **upload over FTP and
verify the CONTENT, not the HTTP status** -> PR to `main` -> occasionally a
`prod-sync-YYYY-MM-DD` snapshot branch.

Deploy and verify BEFORE merging. If production breaks, the branch is still
separate and fixing it does not dirty `main`.

Until 2026-08-31 production ran ahead of git for months. That is closed now
(PR #12, `prod-sync-2026-08-31`); do not reopen it by uploading without
committing.

Note: GitHub Actions —secret scanning included— is not running (billing). Four
red checks on a PR may mean the jobs never started, not that the code is wrong;
open the log. The repo is PUBLIC, so scan diffs for credentials by CONTENT, not
by filename, until CI is back.
