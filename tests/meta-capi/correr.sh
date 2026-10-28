#!/bin/bash
# Corre las pruebas del evento Lead por CAPI. Uso: bash tests/meta-capi/correr.sh [filtro]
# Las *-wp-test.php necesitan WAMP encendido (AT_WP_LOAD).
cd "$(dirname "$0")/../.."
PHP=${PHP:-/c/wamp64/bin/php/php8.3.36/php.exe}
export AT_WP_LOAD=${AT_WP_LOAD:-C:/wamp64/www/automatiza-tech/wp-load.php}
fallo=0
res() { local n="$1"; shift; local o; o=$("$@" 2>&1); local f; f=$(echo "$o" | grep -c '^FALLA'); printf '%-26s %4s ok | %4s FALLA | %s\n' "$n" "$(echo "$o" | grep -c '^ok')" "$f" "$(echo "$o" | tail -1)"; echo "$o" | grep '^FALLA' | head -5; [ "$f" = 0 ] && echo "$o" | tail -1 | grep -q 'TODO OK' || fallo=1; }
for f in tests/meta-capi/*-test.php; do case "$f" in *"$1"*) res "$(basename "$f")" "$PHP" "$f";; esac; done
for f in tests/meta-capi/*-test.mjs; do case "$f" in *"$1"*) res "$(basename "$f")" node "$f";; esac; done
exit $fallo
