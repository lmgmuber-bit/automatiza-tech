// Voces del animador (AT-CUMPLECLICK-026): el guion que se graba y el que muestra el kiosco salen de la misma
// fuente, la Mímica nunca se lee, la respuesta la da la otra voz y la presentación nombra a cada equipo.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { JUEGOS, EQUIPOS_SUGERIDOS } from '../../src/animador/mazos.js'
import {
  EQUIPOS_VOZ, TEXTO_COMENZAR, claveCarta, claveComenzar, claveEquipo, claveIntro, claveRespuesta, guionCompleto,
  otraVoz, presentacion, textoLeible, vozDelJuego,
} from '../../src/animador/vocesGuion.js'

const datos = { nombre: 'Rosa', edad: 70 }
const guion = guionCompleto(JUEGOS, datos)
const porClave = Object.fromEntries(guion.map((x) => [x.clave, x]))

test('cada clave del guion es única y tiene texto y voz', () => {
  assert.equal(new Set(guion.map((x) => x.clave)).size, guion.length)
  for (const x of guion) {
    assert.ok(x.texto.trim().length > 0, x.clave)
    assert.ok(['fem', 'masc'].includes(x.voz), x.clave)
  }
})

test('cada juego tiene intro con su nombre y nada queda sin rellenar', () => {
  for (const j of JUEGOS) assert.ok(porClave[claveIntro(j)], j.id)
  assert.match(porClave[claveIntro(JUEGOS[0])].texto, /Bienvenidos al juego/)
  for (const x of guion) assert.doesNotMatch(x.texto, /\{(nombre|edad)\}/, x.clave)
  assert.ok(guion.some((x) => x.texto.includes('Rosa')), 'el nombre de quien cumple entra al audio')
})

test('la Mímica (tarjeta privada) nunca se graba en voz alta', () => {
  const mimica = JUEGOS.find((j) => j.privado)
  assert.ok(mimica, 'hay un juego privado')
  for (const c of mimica.cartas) assert.equal(porClave[claveCarta(c)], undefined, c.id)
})

test('las respuestas las dice la otra voz y los retos no tienen respuesta grabada', () => {
  for (const j of JUEGOS) {
    const voz = vozDelJuego(JUEGOS, j)
    for (const c of j.cartas) {
      const r = porClave[claveRespuesta(c)]
      if (c.respuesta && c.tipo !== 'reto' && !j.privado) {
        assert.ok(r, c.id)
        assert.equal(r.voz, otraVoz(voz), c.id)
        assert.match(r.texto, /^La respuesta es: /)
      } else assert.equal(r, undefined, c.id)
    }
  }
})

test('las dos voces se reparten parejo (ninguna con más del 60 %)', () => {
  const fem = guion.filter((x) => x.voz === 'fem').length
  assert.ok(fem / guion.length < 0.6 && fem / guion.length > 0.4, `fem ${fem} de ${guion.length}`)
})

test('presentación: intro, un saludo por equipo con su voz y «presionemos comenzar»', () => {
  const juego = JUEGOS[0]
  const pasos = presentacion(JUEGOS, juego, EQUIPOS_SUGERIDOS.slice(0, 2), EQUIPOS_SUGERIDOS, datos)
  assert.equal(pasos[0].clave, claveIntro(juego))
  assert.match(pasos[1].texto, /Caballeros sospechosos/)
  assert.equal(pasos[1].clave, claveEquipo(0, 0))
  assert.equal(porClave[pasos[1].clave].voz, 'masc')
  assert.match(pasos[2].texto, /Cómplices peligrosas/)
  assert.equal(porClave[pasos[2].clave].voz, 'fem')
  assert.equal(pasos.at(-1).texto, TEXTO_COMENZAR)
  assert.equal(pasos.at(-1).clave, claveComenzar(vozDelJuego(JUEGOS, juego)))
  // El texto en pantalla es el mismo que suena.
  for (const p of pasos) assert.equal(porClave[p.clave].texto, p.texto)
})

test('un equipo con nombre propio aparece en pantalla, sin audio', () => {
  const pasos = presentacion(JUEGOS, JUEGOS[1], ['Las Tías'], EQUIPOS_SUGERIDOS, datos)
  assert.equal(pasos[1].clave, null)
  assert.match(pasos[1].texto, /Las Tías/)
})

test('los equipos con voz son los sugeridos, en el mismo orden', () => {
  assert.equal(EQUIPOS_VOZ.length, EQUIPOS_SUGERIDOS.length)
  EQUIPOS_SUGERIDOS.forEach((nombre, n) => {
    for (const t of EQUIPOS_VOZ[n].textos) assert.ok(t.toLowerCase().includes(nombre.toLowerCase()), `${nombre}: ${t}`)
  })
})

test('los juegos con respuesta tienen reloj, así el tiempo puede revelarla', () => {
  for (const j of JUEGOS) {
    const conRespuesta = j.cartas.some((c) => c.respuesta && c.tipo !== 'reto')
    if (conRespuesta) assert.ok(j.segundos > 0, j.id)
  }
})

test('el kiosco: presentación con Omitir, voz por tarjeta, Repetir y reloj que revela', () => {
  const src = readFileSync(new URL('../../src/animador/Animador.jsx', import.meta.url), 'utf8')
  assert.match(src, /setPantalla\('presentacion'\)/)
  assert.match(src, /⏭ Omitir/)
  assert.match(src, /▶ Comenzar/)
  assert.match(src, /voz\.decir\(claveCarta\(carta\)\)/)
  assert.match(src, /🔊 Repetir/)
  assert.match(src, /onFin=\{alTerminarReloj\}/)
  assert.match(src, /voz\.callar\(\)/)
  const app = readFileSync(new URL('../../src/App.jsx', import.meta.url), 'utf8')
  assert.match(app, /voces=\{CONFIG\.vocesAnimador\}/)
})

test('cómo se lee una tarjeta: «A + B» es «A y B» y el paréntesis pasa a coma (la voz se lo saltaba)', () => {
  assert.equal(textoLeible('Tacón + coartada'), 'Tacón y coartada')
  assert.equal(textoLeible('Todos a cantar «Me fui» (Reymar Perdomo).'), 'Todos a cantar "Me fui", Reymar Perdomo.')
  assert.equal(textoLeible('Salto Ángel (Kerepakupai Merú), 979 m'), 'Salto Ángel, Kerepakupai Merú, 979 m')
  assert.equal(textoLeible('El Ávila (Waraira Repano)'), 'El Ávila, Waraira Repano')
  for (const x of guion) assert.doesNotMatch(x.texto, /[()]|,,|\s\+\s/, x.clave)
})

// --- Revisión B de Codex (CX-30, CX-31, CX-32) ---
import { crearReproductor } from '../../src/animador/voz.js'

/** Audio falso: `play` responde según `modo` (ok = suena y termina al llamar `terminar`, falla = rechaza). */
function audioFalso(modo = () => 'ok') {
  const a = { src: '', pausado: false, onended: null, onerror: null, reproducidos: [] }
  a.play = () => {
    a.reproducidos.push(a.src); a.pausado = false
    return modo(a.src) === 'falla' ? Promise.reject(new Error('autoplay')) : Promise.resolve()
  }
  a.pause = () => { a.pausado = true }
  a.terminar = () => a.onended && a.onended()
  return a
}
const tic = () => new Promise((r) => setTimeout(r, 0))

test('CX-31: callar resuelve la reproducción pendiente con false (no queda colgada)', async () => {
  const a = audioFalso(); const rep = crearReproductor(() => a)
  const p = rep.decir(['a.mp3', 'b.mp3']); await tic()
  rep.callar()
  assert.equal(await p, false)
  assert.equal(a.pausado, true)
  assert.deepEqual(a.reproducidos, ['a.mp3'])
})

test('CX-31: una voz nueva corta la anterior; la vieja resuelve false y la nueva true', async () => {
  const a = audioFalso(); const rep = crearReproductor(() => a)
  const vieja = rep.decir(['a.mp3']); await tic()
  const nueva = rep.decir(['b.mp3']); await tic()
  a.terminar()
  assert.equal(await vieja, false)
  assert.equal(await nueva, true)
})

test('CX-32: si la reproducción falla o se bloquea, resuelve false (la presentación da tiempo de lectura)', async () => {
  const rep = crearReproductor(() => audioFalso(() => 'falla'))
  assert.equal(await rep.decir(['a.mp3', 'b.mp3']), false)
  const conError = audioFalso(); const rep2 = crearReproductor(() => conError)
  const p = rep2.decir(['x.mp3']); await tic(); conError.onerror()
  assert.equal(await p, false)
})

test('una secuencia que suena entera resuelve true y en orden', async () => {
  const a = audioFalso(); const rep = crearReproductor(() => a)
  const p = rep.decir(['a.mp3', 'b.mp3'])
  await tic(); a.terminar(); await tic(); a.terminar()
  assert.equal(await p, true)
  assert.deepEqual(a.reproducidos, ['a.mp3', 'b.mp3'])
})

test('CX-30: el reloj se monta de nuevo por tarjeta (key), así el cero de la anterior no cierra la nueva', () => {
  const src = readFileSync(new URL('../../src/animador/Animador.jsx', import.meta.url), 'utf8')
  assert.match(src, /<Cronometro key=\{`\$\{sesion\.ronda\}\|\$\{sesion\.carta\}`\}/)
  // La presentación espera el tiempo de lectura cuando la voz no sonó (false), no solo cuando no hay clip.
  assert.match(src, /if \(!sono && vivo\) await esperar/)
})
