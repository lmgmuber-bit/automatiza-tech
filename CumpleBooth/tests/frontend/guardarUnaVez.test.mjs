// Guardar una sola vez en las vistas previas adultas (revisión B2, CX-24).
import test from 'node:test'
import assert from 'node:assert/strict'
import { crearGuardadoUnaVez } from '../../src/guardarUnaVez.js'

// Reloj falso: los temporizadores se guardan y se disparan a mano.
function reloj() {
  const pendientes = new Map()
  let id = 0
  return {
    programar: (fn, ms) => { id += 1; pendientes.set(id, { fn, ms }); return id },
    cancelar: (t) => { pendientes.delete(t) },
    correr() { for (const [t, { fn }] of [...pendientes]) { pendientes.delete(t); fn() } },
    get cuantos() { return pendientes.size },
  }
}

test('el primer guardado corre la acción y, tras la espera, el paso siguiente', () => {
  const r = reloj()
  const g = crearGuardadoUnaVez(r.programar, r.cancelar)
  const log = []
  assert.equal(g.guardar(() => log.push('descarga'), () => log.push('qr'), 1600), true)
  assert.deepEqual(log, ['descarga'])
  assert.equal(g.usado, true)
  r.correr()
  assert.deepEqual(log, ['descarga', 'qr'])
})

test('tocar Guardar tres veces descarga una vez y pasa al QR una vez (CX-11)', () => {
  const r = reloj()
  const g = crearGuardadoUnaVez(r.programar, r.cancelar)
  const log = []
  for (let i = 0; i < 3; i++) g.guardar(() => log.push('descarga'), () => log.push('qr'), 1600)
  assert.equal(r.cuantos, 1)
  r.correr()
  assert.deepEqual(log, ['descarga', 'qr'])
})

test('si la vista se desmonta antes de la espera, no pasa al QR (CX-24)', () => {
  const r = reloj()
  const g = crearGuardadoUnaVez(r.programar, r.cancelar)
  const log = []
  g.guardar(() => log.push('descarga'), () => log.push('qr'), 1600)
  g.soltar()
  assert.equal(r.cuantos, 0)
  r.correr()
  assert.deepEqual(log, ['descarga'])
})

test('desmontada, ni siquiera un temporizador que se escapara llama al paso siguiente', () => {
  // Cancelar que no cancela: la guarda interna igual lo frena.
  const r = reloj()
  const g = crearGuardadoUnaVez(r.programar, () => {})
  const log = []
  g.guardar(() => {}, () => log.push('qr'), 1600)
  g.soltar()
  r.correr()
  assert.deepEqual(log, [])
})

test('soltar sin haber guardado no rompe nada y armar deja guardar de nuevo', () => {
  const r = reloj()
  const g = crearGuardadoUnaVez(r.programar, r.cancelar)
  g.soltar()
  assert.equal(g.guardar(() => {}, () => {}, 300), false)
  g.armar()
  assert.equal(g.guardar(() => {}, () => {}, 300), true)
})
