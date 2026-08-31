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
