// Arrastre de fichas del rompecabezas: toque vs arrastre, casilla bajo el dedo e intercambio.
import test from 'node:test'
import assert from 'node:assert/strict'
import { celdaBajoPunto, esArrastre, intercambiar, UMBRAL_ARRASTRE_PX } from '../../src/puzzleArrastre.js'

const tablero = { left: 100, top: 200, width: 300, height: 300 }

test('un dedo que apenas se mueve es un toque, no un arrastre', () => {
  assert.equal(esArrastre(3, 4), false)
  assert.equal(esArrastre(UMBRAL_ARRASTRE_PX, 0), true)
  assert.equal(esArrastre(-8, 8), true)
})

test('la casilla bajo el dedo, en una grilla de 3x3', () => {
  assert.equal(celdaBajoPunto(tablero, 3, 3, 101, 201), 0)
  assert.equal(celdaBajoPunto(tablero, 3, 3, 250, 350), 4)
  assert.equal(celdaBajoPunto(tablero, 3, 3, 399, 499), 8)
  // Justo en el borde derecho cae en la última columna, no fuera.
  assert.equal(celdaBajoPunto(tablero, 3, 3, 399.9, 210), 2)
})

test('fuera del tablero no hay casilla', () => {
  assert.equal(celdaBajoPunto(tablero, 3, 3, 99, 250), -1)
  assert.equal(celdaBajoPunto(tablero, 3, 3, 250, 500), -1)
  assert.equal(celdaBajoPunto(null, 3, 3, 250, 250), -1)
})

test('grillas no cuadradas: 4 columnas por 2 filas', () => {
  const ancho = { left: 0, top: 0, width: 400, height: 200 }
  assert.equal(celdaBajoPunto(ancho, 4, 2, 350, 150), 7)
  assert.equal(celdaBajoPunto(ancho, 4, 2, 120, 20), 1)
})

test('soltar sobre otra casilla las intercambia; sobre la misma o fuera, nada cambia', () => {
  const orden = [2, 0, 1, 3]
  assert.deepEqual(intercambiar(orden, 0, 2), [1, 0, 2, 3])
  assert.equal(intercambiar(orden, 1, 1), orden)
  assert.equal(intercambiar(orden, 1, -1), orden)
  assert.equal(intercambiar(orden, 1, 9), orden)
  assert.deepEqual(orden, [2, 0, 1, 3], 'no muta el original')
})
