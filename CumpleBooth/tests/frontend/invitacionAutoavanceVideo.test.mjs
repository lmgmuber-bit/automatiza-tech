import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'
import vm from 'node:vm'

/**
 * CX-12 (AT-CUMPLECLICK-026): la lámina bajaba sola a los 5 s aunque el invitado estuviera
 * viendo el video de la invitación. Revisión B de Codex: a los 7 s el scroll pasaba de 986 a
 * 1915 con el video todavía corriendo.
 *
 * A diferencia de `recorridoInvitacion.test.mjs` (que mira el texto del archivo), aquí se
 * EJECUTAN los dos bloques reales de `invitation.js` —«Descubrir lo que sigue» y «Video de
 * invitación personalizado»— contra un DOM mínimo y un reloj falso, para fijar el
 * comportamiento y no una forma de escribirlo.
 */
const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const js = readFileSync(join(raiz, 'public/assets/invitation.js'), 'utf8')

const desde = js.indexOf('// ---------- Descubrir lo que sigue')
const hasta = js.indexOf('// ---------- Lista de reproducción de videos reales')
assert.ok(desde > 0 && hasta > desde, 'no se encontraron los bloques de la lámina en invitation.js')
const bloque = js.slice(desde, hasta)

function montar({ reduce = false, sonandoDesdeAntes = false, seccionVisible = true } = {}) {
  let ahora = 0
  let siguienteId = 1
  const timers = new Map()
  const setTimeout = (fn, ms) => { const id = siguienteId++; timers.set(id, { fn, en: ahora + ms }); return id }
  const clearTimeout = (id) => { timers.delete(id) }
  const avanzar = (ms) => {
    const fin = ahora + ms
    for (;;) {
      const prox = [...timers.entries()].filter(([, t]) => t.en <= fin).sort((a, b) => a[1].en - b[1].en)[0]
      if (!prox) break
      timers.delete(prox[0])
      ahora = prox[1].en
      prox[1].fn()
    }
    ahora = fin
  }

  const oyentes = (obj) => {
    const mapa = {}
    obj.addEventListener = (tipo, fn) => { (mapa[tipo] ||= []).push(fn) }
    obj.emitir = (tipo) => { (mapa[tipo] || []).forEach((fn) => fn({ type: tipo })) }
    return obj
  }

  const scrolls = []
  const siguiente = { scrollIntoView: (opts) => scrolls.push(opts) }
  const video = oyentes({ paused: !sonandoDesdeAntes, ended: false })
  const seccionArte = {
    nextElementSibling: siguiente,
    querySelectorAll: (sel) => (sel === 'video' ? [video] : []),
    getBoundingClientRect: () => (seccionVisible ? { top: 0, bottom: 800 } : { top: -3000, bottom: -2200 }),
  }
  video.closest = (sel) => (sel === 'section' ? seccionArte : null)
  const marco = { closest: () => seccionArte }

  const ventana = oyentes({ innerHeight: 800, matchMedia: () => ({ matches: reduce }) })
  const documento = {
    documentElement: { clientHeight: 800 },
    body: { appendChild() {} },
    createElement: () => ({ className: '', textContent: '', classList: { add() {} }, remove() {} }),
    querySelector: (sel) => {
      if (sel === '.inv-art-frame') return marco
      if (sel === '[data-inv-video-final]') return video
      return null
    },
  }
  let observador = null
  class IntersectionObserver {
    constructor(cb) { observador = cb }
    observe() {}
  }

  const contexto = vm.createContext({
    window: ventana, document: documento, IntersectionObserver, setTimeout, clearTimeout,
    reducedMotion: { matches: reduce }, Array,
  })
  vm.runInContext(bloque, contexto)

  return {
    scrolls,
    video,
    avanzar,
    laminaVisible: () => observador([{ isIntersecting: true, intersectionRatio: 0.9 }]),
    gesto: () => ventana.emitir('pointerdown'),
  }
}

test('control: sin video sonando, la lámina baja sola a los 5 s (comportamiento heredado intacto)', () => {
  const p = montar()
  p.laminaVisible()
  p.avanzar(4999)
  assert.equal(p.scrolls.length, 0)
  p.avanzar(1)
  assert.equal(p.scrolls.length, 1)
})

test('con el video reproduciéndose no hay avance automático, aunque pasen 30 s', () => {
  const p = montar()
  p.laminaVisible()
  p.gesto() // el toque sobre el video reinicia la cuenta…
  p.video.paused = false
  p.video.emitir('play') // …y el play la suelta
  p.avanzar(30000)
  assert.equal(p.scrolls.length, 0, 'la lámina bajó con el video corriendo')
  p.gesto() // otro toque (pausa, volumen) tampoco la rearma
  p.avanzar(30000)
  assert.equal(p.scrolls.length, 0, 'un gesto rearmó la cuenta con el video tomado')
})

test('al terminar el video avanza una sola vez, con desplazamiento suave', () => {
  const p = montar()
  p.laminaVisible()
  p.video.paused = false
  p.video.emitir('play')
  p.avanzar(24000)
  p.video.ended = true
  p.video.paused = true
  p.video.emitir('ended')
  p.avanzar(30000)
  assert.equal(p.scrolls.length, 1)
  assert.equal(p.scrolls[0].behavior, 'smooth')
})

test('si el video ya sonaba antes de registrar el listener, la cuenta no baja igual', () => {
  const p = montar({ sonandoDesdeAntes: true })
  p.laminaVisible()
  p.avanzar(10000)
  assert.equal(p.scrolls.length, 0)
})

test('con movimiento reducido el avance al terminar no es suave', () => {
  const p = montar({ reduce: true })
  p.video.ended = true
  p.video.emitir('ended')
  assert.equal(p.scrolls.length, 1)
  assert.equal(p.scrolls[0].behavior, 'auto')
})

test('si el invitado ya se fue de la sección, el fin del video no lo arrastra', () => {
  const p = montar({ seccionVisible: false })
  p.video.ended = true
  p.video.emitir('ended')
  assert.equal(p.scrolls.length, 0)
})
