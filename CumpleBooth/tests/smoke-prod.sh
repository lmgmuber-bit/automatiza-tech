#!/usr/bin/env bash
# Smoke de producción: cada URL con el código HTTP que DEBE devolver.
#
# Se corre después de cada despliegue, desde cualquier máquina con red. Solo hace GET y un
# POST que tiene que ser rechazado: no modifica nada.
#
# Uso:  bash tests/smoke-prod.sh [fiesta-1] [fiesta-2] [token-de-aportes-vigente]
# El token es opcional: si no se pasa, se saltan las dos comprobaciones que lo necesitan.
# No se guarda ninguno acá: el enlace de aportes se emite de a uno y cambia.

APP="${CC_APP_URL:-https://cumpleclick.com/app}"
FIESTA_A="${1:-isidora-reino-de-hielo}"
FIESTA_B="${2:-luciano-spidey}"
TOKEN_VIGENTE="${3:-}"

ok=0; mal=0
probar() {  # probar <esperado> <descripción> <url> [args curl extra]
  local esperado="$1" desc="$2" url="$3"; shift 3
  local codigo
  codigo=$(curl -s -o /dev/null -w '%{http_code}' "$@" "$url")
  if [ "$codigo" = "$esperado" ]; then
    ok=$((ok+1)); printf '  ok   %-52s %s\n' "$desc" "$codigo"
  else
    mal=$((mal+1)); printf '  MAL  %-52s %s (esperado %s)\n' "$desc" "$codigo" "$esperado"
  fi
}

echo "== Lo que ve un invitado"
probar 200 "Kiosco $FIESTA_A"                "$APP/?p=$FIESTA_A"
probar 200 "Kiosco $FIESTA_B"                "$APP/?p=$FIESTA_B"
probar 200 "Juego 3D $FIESTA_A"              "$APP/juego/?p=$FIESTA_A"
probar 200 "Juego 3D $FIESTA_B"              "$APP/juego/?p=$FIESTA_B"
probar 200 "Galeria $FIESTA_A (pide PIN)"    "$APP/galeria.php?p=$FIESTA_A"
probar 200 "Galeria $FIESTA_B (pide PIN)"    "$APP/galeria.php?p=$FIESTA_B"
probar 400 "Subir fotos sin token"           "$APP/subir.php"
if [ -n "$TOKEN_VIGENTE" ]; then
  probar 200 "Subir fotos con el token vigente" "$APP/subir.php?t=$TOKEN_VIGENTE"
fi
probar 410 "Subir fotos con un token inventado" "$APP/subir.php?t=$(printf '0%.0s' {1..32})"

echo
echo "== Puertas del admin: sin sesion no pasa nada"
for p in index mensajes comprobante aceptaciones album leads invitations; do
  probar 200 "admin/$p.php muestra el ingreso" "$APP/admin/$p.php"
done
probar 302 "admin/marca.php redirige al ingreso" "$APP/admin/marca.php"
probar 401 "admin/carteles-api.php sin sesion"   "$APP/admin/carteles-api.php?p=$FIESTA_A"
# Sin sesion la API corta antes de mirar el CSRF: 401, no 403. Es el orden correcto.
probar 401 "carteles-api POST sin sesion"        "$APP/admin/carteles-api.php?p=$FIESTA_A" -X POST -d "accion=album-token&csrf=falso"

echo
echo "== Comprobante: la firma manda"
probar 400 "sin firma"                       "$APP/comprobante.php?p=$FIESTA_A"
probar 403 "firma inventada"                 "$APP/comprobante.php?p=$FIESTA_A&f=0000000000000000"
# La firma se valida ANTES de mirar si la fiesta existe, para no filtrar qué fiestas hay.
probar 403 "fiesta inexistente con firma mala" "$APP/comprobante.php?p=no-existe-esta&f=x"

echo
echo "== Archivos que usan los carteles, los correos y los PDF"
probar 200 "carteles.html"                   "$APP/carteles.html"
probar 200 "isotipo CumpleClick"             "$APP/brand/cumpleclick-mark.svg"
probar 200 "logo AutomatizaTech"             "$APP/brand/logo-automatizatech.png"
probar 200 "logo CumpleClick del PDF"        "$APP/brand/pdf-cumpleclick.jpg"
probar 200 "logo AutomatizaTech del PDF"     "$APP/brand/pdf-automatizatech.jpg"
for t in hielo spidey; do
  probar 200 "banner de $t"                  "$APP/themes/$t/fondo-banner.jpg"
  probar 200 "cabecera de correo de $t"      "$APP/themes/$t/correo-cabecera.jpg"
done

echo
echo "== Textos legales enlazados en los correos"
probar 200 "Terminos y condiciones"          "$APP/legal/terminos.html"
probar 200 "Politica de privacidad"          "$APP/legal/privacidad.html"
probar 200 "Consentimiento de imagen"        "$APP/legal/imagen-menores.html"

printf '\n%d correctas, %d fuera de lo esperado\n' "$ok" "$mal"
[ "$mal" -eq 0 ]
