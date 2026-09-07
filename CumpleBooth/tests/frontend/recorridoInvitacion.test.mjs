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

/**
 * La portada de un baby shower NO baja sola: el invitado decide.
 *
 * Historia completa de este punto, porque cambió tres veces en un día:
 * 1. No pasaba nada nunca (el avance esperaba una narración que no existe).
 * 2. Se puso un scroll automático a los 1,2s — y Luis lo devolvió: se sentía
 *    apurado; la portada tiene el nombre, la fecha y los contadores.
 * 3. Forma final (pedido de Luis 2026-08-31): un BOTÓN "Toca para seguir" que
 *    aparece a los 3 segundos y cuyo clic lleva a los videos; deslizar a mano
 *    vale desde el primer instante. Las invitaciones CON narración de Alice
 *    conservan su avance automático aprobado de 2026-08-12.
 */
test('sin narración no hay bajada automática: el que baja es el invitado', () => {
  assert.ok(!/programarBajadaAutomatica/.test(js), 'volvió el scroll automático de portada')
  assert.ok(
    !/\} else \{[\s\S]{0,900}?introNarrationEnded = true;/.test(js),
    'la rama sin narración volvió a habilitar el avance automático',
  )
})

test('el botón de la historia aparece a los 3 segundos y se arma en los dos caminos de entrada', () => {
  assert.match(js, /const ESPERA_BOTON_HISTORIA = 3000;/)
  const conIntro = /startInvitationAudioAfterThemeIntro = \(\) => \{[\s\S]{0,200}?armarBotonHistoria\(\);/
  const sinIntro = /const startMusic = \(\) => \{[\s\S]{0,300}?armarBotonHistoria\(\);/
  assert.match(js, conIntro, 'falta armar el botón tras el intro temático')
  assert.match(js, sinIntro, 'falta armar el botón cuando no hay intro temático')
  assert.match(js, /botonHistoria\.addEventListener\('click', irALaHistoria\)/)
})

test('el botón existe en el HTML como <button> y parte oculto', () => {
  const pagina = readFileSync(join(raiz, 'public/invitacion.php'), 'utf8')
  assert.match(pagina, /<button type="button" class="inv-scroll-hint inv-scroll-hint--waiting" data-inv-scroll-only data-inv-historia/)
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
