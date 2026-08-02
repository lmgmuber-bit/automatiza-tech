# OpenCode context — CumpleClick

Use `AGENTS.md` as project rules. Backend ownership includes `public/*.php`,
`public/admin/`, `database/`, `scripts/`, `config/` and backend tests. Preserve
the public API payload and PHP 8.0 compatibility; use native PDO prepares and
fail closed when config/private storage is invalid. Coordinate before editing
frontend-owned `src/` files. No secrets or production mutations.
