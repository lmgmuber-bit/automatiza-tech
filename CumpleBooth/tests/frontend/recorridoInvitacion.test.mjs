import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

/**
 * El recorrido de la invitación tiene que avanzar solo.
 *
 * Dos huecos que reportó Luis el 2026-08-31 abriendo las demos de baby shower
 * en el celular:
 *
 * 1. Abría el sobre y quedaba parado en la portada. El avance automático
 *    existía desde el 2026-08-12, pero exigía que terminara la narración de
 *    Alice en el hero, y las invitaciones de baby shower no tienen esa
 *    narración: `introNarrationEnded` se quedaba en false para siempre y la
 *    función salía por la primera línea. No fallaba nada —ni error, ni test
 *    rojo—, simplemente no pasaba nada nunca.
 *
 * 2. Terminaban los videos y ahí se acababa el recorrido: el botón "Ver
 *    invitación" aparecía al EMPEZAR el último clip, compitiendo con lo que se
 *    estaba contando, y al terminar no había nada que llevara al invitado a la
 *    tarjeta.
 *
 * Son tests sobre el archivo porque `invitation.js` es una IIFE que toca el DOM
 * entero de la página; montar eso en Node cuesta más de lo que protege. Lo que
 * se fija acá es que las dos condiciones no vuelvan a desaparecer.
 */
const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const js = readFileSync(join(raiz, 'public/assets/invitation.js'), 'utf8')

test('sin narración de intro el avance automático igual se habilita', () => {
  assert.match(
    js,
    /if \(narrationIntro instanceof HTMLAudioElement\) \{[\s\S]*?\} else \{[\s\S]*?introNarrationEnded = true;/,
    'volvió a quedar sin la rama else: en baby shower el avance no se dispara nunca',
  )
})

test('los dos caminos de entrada programan la bajada a los videos', () => {
  // Con intro temático y sin él. Si uno de los dos se queda sin la llamada, la
  // mitad de las invitaciones vuelve a quedarse quieta en la portada.
  const conIntro = /startInvitationAudioAfterThemeIntro = \(\) => \{[\s\S]{0,200}?programarBajadaAutomatica\(\);/
  const sinIntro = /const startMusic = \(\) => \{[\s\S]{0,300}?programarBajadaAutomatica\(\);/
  assert.match(js, conIntro, 'falta la bajada automática tras el intro temático')
  assert.match(js, sinIntro, 'falta la bajada automática cuando no hay intro temático')
})

test('la bajada automática es idempotente y respeta el hero automático', () => {
  assert.match(js, /if \(bajadaProgramada \|\| autoAdvanced \|\| reducedMotion\.matches\) return;/)
})

test('"Ver invitación" aparece cuando los videos TERMINAN, no al empezar el último', () => {
  assert.ok(
    !/hint\.classList\.toggle\('is-visible', i === clips\.length - 1\)/.test(js),
    'el botón volvió a aparecer al entrar al último clip',
  )
  // En el cierre de la lista: se muestra y se acerca al invitado.
  assert.match(js, /hint\.classList\.add\('is-visible'\);/)
  assert.match(js, /hint\.scrollIntoView\(\{ block: 'nearest', behavior: 'smooth' \}\)/)
})

test('el cierre de la lista no salta solo a la tarjeta: el último paso lo da el invitado', () => {
  // `block: 'nearest'` acerca el botón, no navega. Un scrollIntoView del
  // destino (#inv-detalles) se saltaría el click que Luis pidió conservar.
  assert.ok(
    !/inv-detalles'\)\.scrollIntoView/.test(js),
    'la lista está saltando sola a la tarjeta y se salta el click del invitado',
  )
})

test('la barra de progreso se llena aunque no exista el botón', () => {
  // Antes el cierre era `else if (progressBar)`: sin barra, la rama del final
  // no corría entera. Ahora son pasos independientes.
  assert.ok(
    !/\} else if \(progressBar\) \{\s*progressBar\.style\.transform = 'scaleX\(1\)';/.test(js),
    'el cierre de la lista volvió a colgar de que exista la barra',
  )
})

/**
 * La lista de videos tiene DOS disparadores independientes.
 *
 * En la landing, IntersectionObserver entregó la intersección once segundos
 * tarde en producción. Probando esta sección apareció además un navegador
 * donde no llega ni la intersección NI el evento de scroll. O sea: ninguno de
 * los dos mecanismos alcanza solo, y desde el escritorio no hay manera de
 * saber cuál falla en el celular de un invitado.
 *
 * Por eso van los dos, más una primera medición en rAF para la sección que ya
 * entra en pantalla sin que haya scroll de por medio. El primero que dispare
 * gana; `started` impide arrancar dos veces. Si alguien "limpia" uno de los
 * dos caminos, este test se cae: el síntoma del que falta es un recuadro negro
 * y la invitación terminada ahí, sin error en consola.
 */
test('la lista de videos arranca por medición Y por observador, no por uno solo', () => {
  assert.match(js, /const listaALaVista = \(\) => \{/, 'falta la medición contra el viewport')
  assert.match(js, /window\.addEventListener\('scroll', revisarLista, \{ passive: true \}\)/, 'falta el disparador por scroll')
  assert.match(js, /window\.requestAnimationFrame\(revisarLista\)/, 'falta la primera revisión sin scroll')
  assert.match(js, /observador = new IntersectionObserver/, 'se quitó el observador, que es el respaldo del scroll')
  assert.match(js, /revisarLista\(true\)/, 'el observador ya no fuerza el arranque')
})

test('deja de escuchar en cuanto la lista arrancó', () => {
  // Un listener de scroll que queda vivo toda la sesión por nada es justo lo
  // que hace pesada una página en un celular modesto.
  assert.match(js, /const dejarDeRevisar = \(\) => \{[\s\S]*?removeEventListener\('scroll', revisarLista\)/)
})
