import test from 'node:test'
import assert from 'node:assert/strict'
import {
  cartasDelNivel, juegosDelNivel, nuevaSesion, abrirJuego, siguiente, pasar, anotar, deshacer,
  marcador, ganadores, cambiarNivel, reiniciarMazo, nuevaRonda,
} from '../../src/animador/motor.js'
import { JUEGOS } from '../../src/animador/mazos.js'

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
