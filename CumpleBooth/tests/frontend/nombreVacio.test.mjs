import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

/**
 * El nombre del protagonista puede venir VACÍO.
 *
 * En un baby shower "aún no saben" no hay nombre —es el caso para el que se
 * hizo esa temática— y el kiosco lo pegaba igual al final de frases fijas. El
 * invitado de la demo Safari veía:
 *
 *     ¡Bienvenidos al baby shower de !          (pantalla de entrada)
 *     Gracias por venir al baby shower de       (recuerdito impreso)
 *
 * Es el MISMO defecto que ya había aparecido en el título de la invitación, en
 * el pie de las fotos, en la portada del álbum y en el tablero de predicciones.
 * Cinco veces la misma forma: texto fijo + un nombre que puede no existir.
 *
 * Por eso el test no persigue cada frase una por una, sino la forma: en
 * `src/App.jsx` no debe quedar ni un `CONFIG.nombre` suelto. Todo pasa por
 * `nombreEvento()`, que devuelve '' limpio, y por las funciones de frase, que
 * cambian la oración entera cuando no hay nombre en vez de dejar un "de"
 * colgando.
 */
const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const app = readFileSync(join(raiz, 'src/App.jsx'), 'utf8')

test('ninguna pantalla del kiosco lee CONFIG.nombre directamente', () => {
  const sueltos = app.split('\n')
    .map((linea, i) => ({ n: i + 1, linea }))
    .filter(({ linea }) => /CONFIG\??\.nombre/.test(linea))
    .filter(({ linea }) => !linea.includes('const nombreEvento'))
  assert.deepEqual(
    sueltos.map((s) => s.n), [],
    'CONFIG.nombre sin pasar por nombreEvento() en:\n' + sueltos.map((s) => `  L${s.n}: ${s.linea.trim()}`).join('\n'),
  )
})

test('sin nombre, las frases del evento cambian enteras y no dejan "de" colgando', () => {
  // "al baby shower" / "a la fiesta": español correcto, sin hueco.
  assert.match(app, /return nombre \? `al baby shower de \$\{nombre\}` : 'al baby shower'/)
  assert.match(app, /return nombre \? `a la fiesta de \$\{nombre\}` : 'a la fiesta'/)
  assert.match(app, /return nombre \? `el baby shower de \$\{nombre\}` : 'el baby shower'/)
  assert.match(app, /return nombre \? `la fiesta de \$\{nombre\}` : 'la fiesta'/)
})

test('la bienvenida no imprime "baby shower de" cuando no hay a quién nombrar', () => {
  assert.ok(
    !/baby shower de<br \/>\{CONFIG\.nombre\}/.test(app),
    'la pantalla de entrada volvió a pegar el nombre al final de la frase',
  )
  assert.match(app, /¡Bienvenidos al<br \/>baby shower!/)
})
