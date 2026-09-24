import assert from 'node:assert/strict'
import test from 'node:test'
import { selectSpinnerWinnerIndex } from '../../src/spinnerWinner.js'

const carreras = [
  { name: 'Sally' },
  { name: 'Rayo McQueen' },
  { name: 'Mate' },
]

test('QA local de Carreras puede forzar a Rayo McQueen', () => {
  assert.equal(selectSpinnerWinnerIndex(carreras, {
    themeSlug: 'carreras',
    search: '?p=demo-carreras&qaWinner=rayo-mcqueen',
    hostname: 'localhost',
    random: () => 0,
  }), 1)
})

test('el reintento nunca repite el personaje recién rechazado', () => {
  // Se barre el rango de random: ningún valor puede devolver el excluido.
  for (const r of [0, 0.2, 0.4, 0.6, 0.8, 0.999]) {
    for (let excluido = 0; excluido < carreras.length; excluido++) {
      const idx = selectSpinnerWinnerIndex(carreras, {
        random: () => r,
        excludeIndex: excluido,
      })
      assert.notEqual(idx, excluido)
      assert.ok(idx >= 0 && idx < carreras.length)
    }
  }
})

test('con un solo personaje el reintento devuelve ese mismo (no hay otro)', () => {
  assert.equal(selectSpinnerWinnerIndex([{ name: 'Solo' }], {
    random: () => 0.5,
    excludeIndex: 0,
  }), 0)
})

test('el atajo no se habilita fuera de localhost ni para otra tem?tica', () => {
  assert.equal(selectSpinnerWinnerIndex(carreras, {
    themeSlug: 'carreras',
    search: '?qaWinner=rayo-mcqueen',
    hostname: 'automatizatech.cl',
    random: () => 0,
  }), 0)
  assert.equal(selectSpinnerWinnerIndex(carreras, {
    themeSlug: 'hielo',
    search: '?qaWinner=rayo-mcqueen',
    hostname: 'localhost',
    random: () => 0,
  }), 0)
})
