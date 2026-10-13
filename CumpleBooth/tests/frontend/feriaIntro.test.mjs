import test from 'node:test'
import assert from 'node:assert/strict'
import { INTRO_ARRANQUE_MS, INTRO_MINIMO_MS, INTRO_TOPE_MS, alVencer, esperaPorDuracion, restanteMinimo } from '../../src/feria/intro.js'

// Luis (28-09): en la feria la intro se saltaba y el niño caía directo a la ruleta. Estas reglas garantizan que la intro
// se vea un mínimo aunque el video no arranque o falle, y que un video que sí avanza no se corte a media frase.

test('la intro nunca dura menos que el mínimo, aunque el video falle o termine antes', () => {
  assert.equal(restanteMinimo(1000, 1000), INTRO_MINIMO_MS)
  assert.equal(restanteMinimo(1000, 3500), INTRO_MINIMO_MS - 2500)
  assert.equal(restanteMinimo(1000, 9000), 0)
})

test('un video que no arranca pasa a la tarjeta y espera lo que falta del mínimo; no salta a la ruleta', () => {
  const desde = 10000
  const parado = { ended: false, paused: true, currentTime: 0, duration: NaN }
  assert.deepEqual(alVencer({ video: parado, ultimoTiempo: -1, desde, ahora: desde + 2000 }), { accion: 'tarjeta', ms: INTRO_MINIMO_MS - 2000 })
  // Sin video (falló al montarse) y ya pasado el arranque: el mínimo se cumplió, se sigue.
  assert.deepEqual(alVencer({ video: null, ultimoTiempo: -1, desde, ahora: desde + INTRO_ARRANQUE_MS }), { accion: 'terminar' })
})

test('un video que sigue avanzando recibe lo que le falta, con tope', () => {
  const desde = 0
  const paso = alVencer({ video: { ended: false, paused: false, currentTime: 3.2, duration: 9.5 }, ultimoTiempo: 1.0, desde, ahora: 7000 })
  assert.equal(paso.accion, 'esperar')
  assert.equal(paso.tiempo, 3.2)
  assert.equal(paso.ms, 6900)
  // Por debajo de 0,8 s siempre se espera al menos eso (el final del video puede llegar con retraso).
  assert.equal(alVencer({ video: { ended: false, paused: false, currentTime: 9.4, duration: 9.5 }, ultimoTiempo: 5, desde, ahora: 9000 }).ms, 800)
  // Pasado el tope se termina aunque avance.
  assert.deepEqual(alVencer({ video: { ended: false, paused: false, currentTime: 40, duration: 60 }, ultimoTiempo: 30, desde, ahora: INTRO_TOPE_MS + 1 }), { accion: 'terminar' })
  // Misma marca de tiempo que la última vez (se trabó) y mínimo cumplido: terminar.
  assert.deepEqual(alVencer({ video: { ended: false, paused: false, currentTime: 1.0, duration: 9.5 }, ultimoTiempo: 1.0, desde, ahora: 9000 }), { accion: 'terminar' })
})

test('la espera por duración se ajusta al video, con margen y tope de 30 s', () => {
  assert.equal(esperaPorDuracion(5.04), 5640)
  assert.equal(esperaPorDuracion(60), 30000)
  assert.equal(esperaPorDuracion(NaN), null)
  assert.equal(esperaPorDuracion(0), null)
})
