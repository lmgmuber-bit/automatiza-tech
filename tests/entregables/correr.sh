#!/bin/bash
# Corre las pruebas del módulo entregables. Uso: bash tests/entregables/correr.sh [filtro]
cd "$(dirname "$0")/../.."
PHP=${PHP:-/c/wamp64/bin/php/php8.4.15/php.exe}
SCR=C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad
export AT_WP_LOAD=${AT_WP_LOAD:-$SCR/wp-local-plan/wp-load.php}
res() { local n="$1"; shift; local o; o=$("$@" 2>&1); printf '%-34s %4s ok | %4s FALLA | %s\n' "$n" "$(echo "$o" | grep -c '^ok')" "$(echo "$o" | grep -c '^FALLA')" "$(echo "$o" | tail -1)"; }
for f in tests/entregables/*-test.php; do
	case "$f" in *"$1"*) res "$(basename "$f")" "$PHP" "$f";; esac
done
