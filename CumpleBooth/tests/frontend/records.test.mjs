import test from 'node:test'
import assert from 'node:assert/strict'

/* records.js habla con localStorage, que en Node no existe. Se le pone un
   doble mínimo ANTES de importar el módulo: así se prueba la lógica real
   (quién gana, qué se descarta, cómo se formatea) sin navegador. */
const memoria = new Map()
globalThis.localStorage = {
  getItem: (k) => (memoria.has(k) ? memoria.get(k) : null),
  setItem: (k, v) => memoria.set(k, String(v)),
  removeItem: (k) => memoria.delete(k),
  clear: () => memoria.clear(),
}

const { configurarRecords, guardarRecord, leerRecord, textoRecord, formatoSegundos } =
  await import('../../src/records.js')

test('sin fiesta configurada no se guarda nada (no ensucia otra fiesta)', () => {
  memoria.clear()
  configurarRecords(null)
  assert.equal(guardarRecord('copos', 10, 'Sofía'), false)
  assert.equal(leerRecord('copos'), null)
})

test('el primer puntaje siempre es récord y queda con su dueño', () => {
  memoria.clear()
  configurarRecords('booth_demo')
  assert.equal(guardarRecord('copos', 10, 'Sofía'), true)
  assert.deepEqual(leerRecord('copos'), { valor: 10, invitado: 'Sofía' })
  assert.equal(textoRecord('copos'), '10 · Sofía')
})

test('en juegos de puntaje solo gana el número más alto; el empate NO es récord', () => {
  memoria.clear()
  configurarRecords('booth_demo')
  guardarRecord('copos', 10, 'Sofía')
  assert.equal(guardarRecord('copos', 9, 'Mateo'), false, 'menos no destrona')
  assert.equal(guardarRecord('copos', 10, 'Mateo'), false, 'empatar no destrona')
  assert.equal(leerRecord('copos').invitado, 'Sofía')
  assert.equal(guardarRecord('copos', 11, 'Mateo'), true, 'superar sí destrona')
  assert.equal(leerRecord('copos').invitado, 'Mateo')
})

test('en los rompecabezas gana el tiempo MÁS BAJO', () => {
  memoria.clear()
  configurarRecords('booth_demo')
  assert.equal(guardarRecord('fichas', 30000, 'Sofía', 'menor'), true)
  assert.equal(guardarRecord('fichas', 45000, 'Mateo', 'menor'), false, 'más lento no es récord')
  assert.equal(guardarRecord('fichas', 12400, 'Mateo', 'menor'), true, 'más rápido sí')
  assert.equal(textoRecord('fichas', formatoSegundos), '12.4s · Mateo')
})

test('un cero de puntaje no ensucia el marcador (saltarse el juego no es marca)', () => {
  memoria.clear()
  configurarRecords('booth_demo')
  assert.equal(guardarRecord('ritmo', 0, 'Sofía'), false)
  assert.equal(leerRecord('ritmo'), null)
  assert.equal(textoRecord('ritmo'), '')
})

test('cada juego lleva su propio récord, no se pisan entre sí', () => {
  memoria.clear()
  configurarRecords('booth_demo')
  guardarRecord('copos', 10, 'Sofía')
  guardarRecord('ritmo', 400, 'Mateo')
  assert.equal(leerRecord('copos').invitado, 'Sofía')
  assert.equal(leerRecord('ritmo').invitado, 'Mateo')
})

test('cada fiesta arranca con el marcador limpio', () => {
  memoria.clear()
  configurarRecords('booth_demo')
  guardarRecord('copos', 10, 'Sofía')
  configurarRecords('booth_otra-fiesta')
  assert.equal(leerRecord('copos'), null, 'la fiesta nueva no hereda récords')
  // Y la anterior conserva el suyo al volver.
  configurarRecords('booth_demo')
  assert.equal(leerRecord('copos').valor, 10)
})

test('valores inválidos no rompen ni se guardan', () => {
  memoria.clear()
  configurarRecords('booth_demo')
  assert.equal(guardarRecord('copos', NaN, 'Sofía'), false)
  assert.equal(guardarRecord('copos', Infinity, 'Sofía'), false)
  assert.equal(guardarRecord('copos', 'diez', 'Sofía'), false)
  assert.equal(leerRecord('copos'), null)
})

test('un localStorage corrupto no tumba el kiosco', () => {
  memoria.clear()
  configurarRecords('booth_demo')
  memoria.set('booth_demo_records', '{{{ no es json')
  assert.equal(leerRecord('copos'), null)
  assert.equal(guardarRecord('copos', 5, 'Sofía'), true, 'se puede volver a escribir encima')
})
