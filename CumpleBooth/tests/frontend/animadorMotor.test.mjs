import test from 'node:test'
import assert from 'node:assert/strict'
import {
  cartasDelNivel, juegosDelNivel, nuevaSesion, abrirJuego, siguiente, pasar, anotar, deshacer,
  marcador, ganadores, cambiarNivel, reiniciarMazo, nuevaRonda, yaAnoto, necesitaPreparacion,
  confirmarPreparacion, alternativaDisponible, serializarSesion, restaurarSesion,
} from '../../src/animador/motor.js'
import { JUEGOS } from '../../src/animador/mazos.js'
import { rellenar } from '../../src/cumpleAdulto.js'

// Generador fijo: las pruebas no dependen del azar.
const fijo = () => { let s = 7; return () => ((s = (s * 9301 + 49297) % 233280) / 233280) }
const juego = { id: 'j', cartas: [
  { id: 'a', tono: 'coqueto' }, { id: 'b', tono: 'picante' }, { id: 'c', tono: 'sinverguenza' }, { id: 'd', tono: 'coqueto' },
] }

test('el nivel es un techo: nunca aparece una tarjeta más atrevida', () => {
  assert.deepEqual(cartasDelNivel(juego, 'coqueto').map((c) => c.id), ['a', 'd'])
  assert.deepEqual(cartasDelNivel(juego, 'picante').map((c) => c.id), ['a', 'b', 'd'])
  assert.equal(cartasDelNivel(juego, 'sinverguenza').length, 4)
})

test('un juego sin tarjetas para el nivel se oculta', () => {
  const soloPicante = { id: 'p', cartas: [{ id: 'x', tono: 'picante' }] }
  assert.deepEqual(juegosDelNivel([juego, soloPicante], 'coqueto').map((j) => j.id), ['j'])
})

test('el mazo no repite hasta agotarse y no se reinicia solo', () => {
  let s = abrirJuego(nuevaSesion({ nivel: 'sinverguenza', equipos: ['A', 'B'] }), juego, fijo())
  const vistas = [s.carta]
  for (let i = 0; i < 3; i++) { s = siguiente(s); vistas.push(s.carta) }
  assert.equal(new Set(vistas).size, 4)
  s = siguiente(s)
  assert.equal(s.agotado, true)
  assert.equal(s.carta, null)
  s = reiniciarMazo(s, juego, fijo())
  assert.equal(s.agotado, false)
  assert.ok(s.carta)
})

test('«Paso esta» avanza sin dar puntos', () => {
  let s = abrirJuego(nuevaSesion({ equipos: ['A', 'B'] }), juego, fijo())
  s = pasar(s)
  assert.deepEqual(marcador(s), [0, 0])
})

test('un doble toque no suma dos veces y deshacer quita el último', () => {
  let s = abrirJuego(nuevaSesion({ equipos: ['A', 'B'] }), juego, fijo())
  s = anotar(s, 0, 3)
  s = anotar(s, 0, 3)
  assert.deepEqual(marcador(s), [3, 0])
  s = anotar(s, 1, 1)
  s = deshacer(s)
  assert.deepEqual(marcador(s), [3, 0])
  s = siguiente(s)
  s = anotar(s, 0, 2)
  assert.deepEqual(marcador(s), [5, 0])
  assert.deepEqual(ganadores(s), ['A'])
})

test('los puntos se acotan a 0..3 y a equipos que existen', () => {
  let s = nuevaSesion({ equipos: ['A', 'B'] })
  s = anotar(s, 0, 9)
  s = anotar(nuevaRonda(s), 5, 1)
  assert.deepEqual(marcador(s), [3, 0])
})

test('bajar de nivel retira la tarjeta en pantalla si queda sobre el techo', () => {
  let s = abrirJuego(nuevaSesion({ nivel: 'sinverguenza', equipos: ['A', 'B'] }), juego, fijo())
  while (s.carta !== 'c') s = siguiente(s.agotado ? reiniciarMazo(s, juego, fijo()) : s)
  s = cambiarNivel(s, 'coqueto', juego)
  assert.notEqual(s.carta, 'c')
  assert.ok(!s.mazo.includes('b') && !s.mazo.includes('c'))
})

test('empate: el premio se comparte; sin puntos no hay ganador', () => {
  let s = nuevaSesion({ equipos: ['A', 'B'] })
  assert.deepEqual(ganadores(s), [])
  s = anotar(s, 0, 2)
  s = anotar(s, 1, 2)
  assert.deepEqual(ganadores(s), ['A', 'B'])
})

test('los mazos son genéricos, válidos y con ids únicos', () => {
  const ids = JUEGOS.flatMap((j) => j.cartas.map((c) => c.id))
  assert.equal(new Set(ids).size, ids.length)
  assert.ok(JUEGOS.length >= 14)
  for (const j of JUEGOS) {
    assert.ok(j.nombre && j.instrucciones && j.premio, j.id)
    for (const c of j.cartas) assert.ok(['coqueto', 'picante', 'sinverguenza'].includes(c.tono), c.id)
    assert.ok(j.sinCartas || j.cartas.length > 0, j.id)
  }
  const texto = JSON.stringify(JUEGOS)
  assert.doesNotMatch(texto, /\d{2} y todavía/, 'la edad entra por {edad}, no escrita')
})

test('con nivel Coqueto quedan juegos para jugar (el arranque por defecto no queda vacío)', () => {
  assert.ok(juegosDelNivel(JUEGOS, 'coqueto').length >= 6)
})

// ---- Correcciones de la revisión B de Codex (AT-CUMPLECLICK-026) ----

const tribunal = JUEGOS.find((j) => j.id === 'tribunal')
const piropos = JUEGOS.find((j) => j.id === 'piropos')
const beso = JUEGOS.find((j) => j.id === 'beso-al-blanco')

test('CX-06: abrir un juego sin cartas empieza una ronda nueva y no hereda lo anotado', () => {
  let s = abrirJuego(nuevaSesion({ nivel: 'picante', equipos: ['A', 'B'] }), tribunal, fijo())
  s = anotar(s, 0, 1)
  assert.equal(yaAnoto(s, 0), true)
  s = abrirJuego(s, beso, fijo())
  assert.equal(yaAnoto(s, 0), false, 'el primer lanzamiento de A no queda bloqueado')
  s = anotar(s, 0, 3)
  assert.deepEqual(marcador(s), [4, 0])
  // Doble toque en el mismo lanzamiento sigue sin sumar dos veces.
  s = anotar(s, 0, 3)
  assert.deepEqual(marcador(s), [4, 0])
  // Siguiente lanzamiento, y volver a entrar (p. ej. desde el cierre): siempre ronda nueva.
  s = nuevaRonda(s)
  assert.equal(yaAnoto(s, 0), false)
  s = anotar(s, 0, 2)
  const antes = s.ronda
  s = abrirJuego(s, beso, fijo())
  assert.equal(s.ronda, antes + 1)
  assert.equal(yaAnoto(s, 0), false)
  assert.equal(s.ronda - s.rondaInicio + 1, 1, 'el contador de lanzamientos parte en 1')
})

test('CX-06: un juego que abre agotado también empieza ronda nueva', () => {
  let s = abrirJuego(nuevaSesion({ nivel: 'sinverguenza', equipos: ['A', 'B'] }), juego, fijo())
  while (!s.agotado) s = siguiente(s)
  s = anotar(s, 1, 1)
  s = abrirJuego(s, juego, fijo())
  assert.equal(s.agotado, true)
  assert.equal(yaAnoto(s, 1), false)
})

test('CX-08: salir y volver a un juego no repite tarjetas hasta agotarlo', () => {
  const rng = fijo()
  let s = abrirJuego(nuevaSesion({ nivel: 'picante', equipos: ['A', 'B'] }), piropos, rng)
  const vistas = [s.carta]
  for (let i = 1; i < piropos.cartas.length; i++) {
    s = abrirJuego(s, beso, rng) // otro juego entremedio
    s = abrirJuego(s, piropos, rng) // vuelve
    vistas.push(s.carta)
  }
  assert.equal(new Set(vistas).size, piropos.cartas.length, 'las ocho tarjetas, sin repetir')
  s = abrirJuego(s, piropos, rng)
  assert.equal(s.agotado, true, 'agotado: no se rebaraja solo')
  assert.equal(s.carta, null)
  s = reiniciarMazo(s, piropos, rng)
  assert.equal(s.agotado, false, '«Barajar de nuevo» explícito')
  assert.ok(s.carta)
})

test('CX-08: barajar de nuevo un juego no toca lo usado en los otros', () => {
  let s = abrirJuego(nuevaSesion({ nivel: 'sinverguenza', equipos: ['A', 'B'] }), piropos, fijo())
  s = abrirJuego(s, juego, fijo())
  const usadasPiropos = [...s.usadas.piropos]
  while (!s.agotado) s = siguiente(s)
  s = reiniciarMazo(s, juego, fijo())
  assert.deepEqual(s.usadas.piropos, usadasPiropos)
  assert.deepEqual(s.usadas.j, [s.carta])
})

test('CX-08: las tarjetas usadas sobreviven a guardar y recargar', () => {
  let s = abrirJuego(nuevaSesion({ nivel: 'picante', equipos: ['A', 'B'] }), piropos, fijo())
  s = siguiente(s)
  const vistas = [...s.usadas.piropos]
  const recargada = restaurarSesion(JSON.parse(JSON.stringify(serializarSesion(s))))
  assert.ok(recargada)
  let r = abrirJuego(recargada, piropos, fijo())
  const despues = [r.carta]
  while (!r.agotado) { r = siguiente(r); if (r.carta) despues.push(r.carta) }
  assert.equal(despues.filter((id) => vistas.includes(id)).length, 0, 'no vuelve ninguna ya vista')
  assert.equal(despues.length + vistas.length, piropos.cartas.length)
})

test('CX-07: Tribunal exige confirmar la preparación (mínimo 6) y ofrece La coartada perfecta', () => {
  assert.ok(tribunal.preparacion, 'Tribunal tiene preparación')
  assert.ok(tribunal.preparacion.minimo >= 6)
  assert.match(tribunal.preparacion.confirmar, /\{nombre\}.*mínimo 6/)
  assert.equal(tribunal.preparacion.alternativa, 'coartada')
  let s = nuevaSesion({ nivel: 'picante', equipos: ['A', 'B'] })
  assert.equal(necesitaPreparacion(s, tribunal), true)
  assert.equal(necesitaPreparacion(s, piropos), false, 'los demás juegos no piden nada')
  assert.equal(alternativaDisponible(JUEGOS, tribunal, 'picante')?.id, 'coartada')
  // La coartada no tiene tarjetas coquetas: con Coqueto no se ofrece (el nivel sigue siendo techo).
  assert.equal(alternativaDisponible(JUEGOS, tribunal, 'coqueto'), null)
  s = confirmarPreparacion(s, tribunal)
  assert.equal(necesitaPreparacion(s, tribunal), false)
  assert.equal(confirmarPreparacion(s, tribunal), s, 'confirmar dos veces no cambia nada')
  // Lo guardado es solo el sí del animador: ids de juegos, nada de respuestas.
  const guardado = serializarSesion(s)
  assert.deepEqual(guardado.preparados, ['tribunal'])
  // `categorias` (12-oct): solo el nombre de la categoría elegida por juego, nunca respuestas.
  assert.deepEqual(Object.keys(guardado).sort(), ['categorias', 'equipos', 'nivel', 'preparados', 'puntos', 'ronda', 'usadas', 'v'])
  assert.equal(necesitaPreparacion(restaurarSesion(JSON.parse(JSON.stringify(guardado))), tribunal), false)
})

test('CX-18: un estado guardado incompleto o con otra forma se descarta', () => {
  const buena = serializarSesion(anotar(abrirJuego(nuevaSesion({ equipos: ['Uno', 'Dos'] }), tribunal, fijo()), 1, 1))
  assert.ok(restaurarSesion(JSON.parse(JSON.stringify(buena))), 'la forma completa sí se restaura')
  const malas = [
    null, undefined, 'texto', 42, [], {},
    { equipos: ['Uno', 'Dos'] }, // el caso reportado: pasaba la guarda y rompía en marcador()
    { ...buena, v: undefined }, { ...buena, v: 1 },
    { ...buena, equipos: 'Uno,Dos' }, { ...buena, equipos: ['Solo'] }, { ...buena, equipos: ['A', ''] },
    { ...buena, equipos: ['A', 'B', 'C', 'D', 'E'] }, { ...buena, equipos: ['A', 7] },
    { ...buena, nivel: 'extremo' }, { ...buena, nivel: undefined },
    { ...buena, ronda: -1 }, { ...buena, ronda: 1.5 }, { ...buena, ronda: '3' }, { ...buena, ronda: null },
    { ...buena, puntos: 'x' }, { ...buena, puntos: [null] },
    { ...buena, puntos: [{ ronda: 1, equipo: 5, valor: 1, juego: 'tribunal' }] },
    { ...buena, puntos: [{ ronda: 1, equipo: 0, valor: 9, juego: 'tribunal' }] },
    { ...buena, puntos: [{ ronda: 1, equipo: 0, valor: '1', juego: 'tribunal' }] },
    { ...buena, puntos: [{ ronda: 99, equipo: 0, valor: 1, juego: 'tribunal' }] },
    { ...buena, usadas: [] }, { ...buena, usadas: { piropos: 'pi1' } }, { ...buena, usadas: null },
    { ...buena, preparados: 'tribunal' }, { ...buena, preparados: [1] },
  ]
  for (const m of malas) assert.equal(restaurarSesion(m), null, JSON.stringify(m))
  // Lo que sí se restaura queda listo para el menú y para el marcador.
  const r = restaurarSesion(JSON.parse(JSON.stringify(buena)))
  assert.deepEqual(marcador(r), [0, 1])
  assert.equal(r.juego, null)
  assert.deepEqual(r.mazo, [])
})

test('CX-23: el catálogo no supone el género de quien cumple y funciona sin nombre', () => {
  const texto = JSON.stringify(JUEGOS)
  for (const fijoFemenino of ['la lleva', 'la más buscada', 'celebrada', 'arreglada', 'Me hago la difícil']) {
    assert.ok(!texto.includes(fijoFemenino), fijoFemenino)
  }
  const textos = JUEGOS.flatMap((j) => [
    j.nombre, j.instrucciones, j.premio, j.nota || '', j.preparacion?.confirmar || '', j.preparacion?.explicacion || '',
    ...j.cartas.map((c) => c.texto),
  ])
  for (const plantilla of textos) {
    for (const datos of [{ nombre: '', edad: null }, { nombre: 'Alex', edad: 50 }]) {
      const listo = rellenar(plantilla, datos)
      assert.doesNotMatch(listo, /[{}]/, plantilla)
      assert.doesNotMatch(listo, /\s(a|de|con|para|que)\s*[?.,:;)]/, `queda colgando: «${listo}»`)
    }
  }
})

test('CX-05: la tarjeta privada es un solo botón estable que se tapa al soltar, cancelar o perder foco', async () => {
  // Sin DOM en node: se cuida la estructura del componente. El recorrido real (soltar fuera,
  // Espacio, cambio de tarjeta) se prueba en el navegador.
  const { readFile } = await import('node:fs/promises')
  const fuente = await readFile(new URL('../../src/animador/Animador.jsx', import.meta.url), 'utf8')
  const inicio = fuente.indexOf('function CartaPrivada')
  assert.ok(inicio > 0, 'existe CartaPrivada')
  const cuerpo = fuente.slice(inicio, fuente.indexOf('\nfunction ', inicio + 10))
  for (const clave of ['setPointerCapture', 'releasePointerCapture', 'onPointerCancel', 'onLostPointerCapture',
    'onBlur', 'onKeyDown', 'onKeyUp', "'pointerup'", "'pointercancel'", "'blur'", 'visibilitychange']) {
    assert.ok(cuerpo.includes(clave), clave)
  }
  assert.equal((cuerpo.match(/<button\b/g) || []).length, 1, 'un solo botón, que no se reemplaza por otro elemento')
  assert.doesNotMatch(cuerpo, /<div\b/)
  // La llave incluye la tarjeta y el nivel: cambiar cualquiera de los dos la tapa.
  assert.match(fuente, /<CartaPrivada llave=\{`\$\{carta\.id\}\|\$\{sesion\.nivel\}`\}/)
})
