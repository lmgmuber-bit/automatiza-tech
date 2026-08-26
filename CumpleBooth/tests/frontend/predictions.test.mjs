import test from 'node:test'
import assert from 'node:assert/strict'
import { createPredictionSubmissionToken, predictionLabels, predictionSummary, validPrediction } from '../../src/predictions.js'

const complete = {
  guest_name: 'Camila',
  parecido: 'ambos',
  peso: 'entre',
  fecha: 'justo',
}

test('valida una predicción completa y rechaza opciones inventadas', () => {
  assert.equal(validPrediction(complete), true)
  assert.equal(validPrediction({ ...complete, parecido: 'otra' }), false)
  assert.equal(validPrediction({ ...complete, guest_name: '   ' }), false)
})

test('presenta etiquetas humanas estables para pantalla y canvas', () => {
  assert.deepEqual(predictionLabels(complete), {
    parecido: 'A ambos',
    peso: 'Entre 3 y 3,5 kg',
    fecha: 'Justo en la fecha',
  })
  assert.equal(predictionSummary(complete), 'A ambos · Entre 3 y 3,5 kg · Justo en la fecha')
})

test('genera una clave opaca nueva para hacer idempotente cada recorrido', () => {
  const first = createPredictionSubmissionToken()
  const second = createPredictionSubmissionToken()
  assert.match(first, /^[a-f0-9]{32}$/)
  assert.match(second, /^[a-f0-9]{32}$/)
  assert.notEqual(first, second)
})
