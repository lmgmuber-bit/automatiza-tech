#!/bin/bash
# Corre las pruebas del módulo de colaboradores. Uso: bash tests/colaboradores/correr.sh [filtro]
# Las *-wp-test.php necesitan WAMP encendido.
cd "$(dirname "$0")/../.."
PHP=${PHP:-/c/wamp64/bin/php/php8.4.15/php.exe}
export AT_WP_LOAD=${AT_WP_LOAD:-C:/wamp64/www/automatiza-tech/wp-load.php}
res() { local n="$1"; shift; local o; o=$("$@" 2>&1); printf '%-28s %4s ok | %4s FALLA | %s\n' "$n" "$(echo "$o" | grep -c '^ok')" "$(echo "$o" | grep -c '^FALLA')" "$(echo "$o" | tail -1)"; echo "$o" | grep '^FALLA' | head -5; }
for f in tests/colaboradores/*-test.php; do
	case "$f" in *"$1"*) res "$(basename "$f")" "$PHP" "$f";; esac
done
