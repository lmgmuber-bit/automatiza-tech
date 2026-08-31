import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

/**
 * Los papás tienen DOS pantallas detrás de un solo token, y las dos tienen que
 * ser alcanzables.
 *
 * El 2026-08-27 la lista de regalos existía, funcionaba y no la enlazaba nada:
 * el admin entregaba sólo la URL del tablero de predicciones y `regalos-papas.php`
 * quedaba accesible únicamente adivinando la dirección. Nada fallaba —ni un
 * error, ni un test rojo— y aun así la lista de regalos era inservible.
 *
 * Estos tests son sobre alcanzabilidad, no sobre estética. Un enlace roto acá
 * no rompe ninguna página: simplemente deja una pantalla huérfana, que es
 * exactamente la clase de defecto que nadie nota hasta que un cliente la pide.
 */
const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const leer = (ruta) => readFileSync(join(raiz, ruta), 'utf8')

test('el admin entrega las DOS URLs de los papás, no sólo la de predicciones', () => {
  const admin = leer('public/admin/invitations.php')
  assert.ok(
    admin.includes('cb_prediction_board_url('),
    'el admin debe mostrar la URL del tablero de predicciones',
  )
  assert.ok(
    admin.includes('cb_gift_board_url('),
    'el admin debe mostrar también la URL de la lista de regalos',
  )
})

test('cada pantalla de los papás enlaza a la otra', () => {
  assert.ok(
    leer('public/predicciones.php').includes('cb_gift_board_url('),
    'el tablero de predicciones debe enlazar la lista de regalos',
  )
  assert.ok(
    leer('public/regalos-papas.php').includes('cb_prediction_board_url('),
    'la lista de regalos debe enlazar el tablero de predicciones',
  )
})

test('las dos URLs se construyen desde la configuración, nunca desde HTTP_HOST', () => {
  const gifts = leer('public/lib.gifts.php')
  const predicciones = leer('public/lib.predictions.php')
  for (const [nombre, fuente] of [['lib.gifts.php', gifts], ['lib.predictions.php', predicciones]]) {
    assert.ok(
      fuente.includes('cb_public_base_url()'),
      `${nombre} debe armar la URL con cb_public_base_url()`,
    )
    // Busca el ACCESO, no la palabra: `HTTP_HOST` aparece en un comentario que
    // explica justamente por qué no se usa, y una búsqueda de texto plano se
    // tragaría ese comentario y fallaría sola.
    assert.ok(
      !/\$_SERVER\s*\[\s*['"]HTTP_HOST/.test(fuente),
      `${nombre} no debe leer $_SERVER['HTTP_HOST']: el atacante lo controla`,
    )
  }
})

test('el mismo token abre las dos pantallas, así revocar cierra ambas', () => {
  const gifts = leer('public/lib.gifts.php')
  const regalos = leer('public/regalos-papas.php')
  assert.match(
    gifts,
    /function cb_gift_board_url\(string \$token\)/,
    'la URL de regalos recibe el token, no un identificador propio',
  )
  assert.ok(
    regalos.includes("cb_invitation_resolve_role_token($token, 'parents')"),
    'la pantalla de regalos valida el mismo propósito de token que la de predicciones',
  )
})

test('el invitado nunca ve quién reservó: sólo la pantalla de los papás lo muestra', () => {
  assert.ok(
    !leer('public/invitacion.php').includes('claimed_name'),
    'la invitación pública no debe tocar claimed_name',
  )
  assert.ok(
    !leer('public/gift-api.php').includes('claimed_name'),
    'la API pública de regalos no debe exponer claimed_name',
  )
  assert.ok(
    leer('public/regalos-papas.php').includes('claimed_name'),
    'la pantalla de los papás sí muestra el nombre de quien reservó',
  )
})
