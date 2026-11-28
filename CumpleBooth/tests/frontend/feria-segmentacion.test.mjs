import test from 'node:test'
import assert from 'node:assert/strict'
import { createSegmenter, softAlpha, subjectPlacement, previewDecision } from '../../src/feria/segmentation.js'

test('el recorte conserva la escala de cámara y se centra abajo, sin estirar', () => {
  const box = subjectPlacement(640, 480, 1080, 1920)
  assert.equal(box.width / 640, box.height / 480)
  assert.equal(box.x + box.width / 2, 540)
  assert.equal(box.y + box.height, 1920)
})
test('máscara suave: conserva personas, elimina fondo y tiene valores intermedios', () => {
  assert.equal(softAlpha(0), 0); assert.equal(softAlpha(1), 255)
  assert.ok(softAlpha(0.4) > 0 && softAlpha(0.6) < 255)
  const values = Array.from({ length: 101 }, (_, i) => softAlpha(i / 100))
  assert.deepEqual(values, [...values].sort((a,b) => a-b))
})
test('15 fps permite vista previa; menos pasa a recorte solo en la foto final', () => {
  assert.equal(previewDecision(30, 2000).enabled, true)
  assert.equal(previewDecision(29, 2000).enabled, false)
})
test('modelo que no responde cae al marco y termina el worker', async () => {
  let stopped = false
  const worker = { postMessage() {}, terminate() { stopped = true } }
  const client = createSegmenter('./', { makeWorker: () => worker, timeoutMs: 15 })
  assert.equal(await client.ready, false)
  assert.equal(client.metrics.preview, 'frame')
  assert.equal(client.metrics.fallback, 'timeout')
  assert.equal(stopped, true)
  assert.equal(await client.mask({}), null)
})
test('worker no disponible conserva la foto con el marco', async () => {
  const client = createSegmenter('./', { makeWorker: () => { throw Error('sin soporte') } })
  assert.equal(await client.ready, false)
  assert.equal(await client.mask({}), null)
})
test('cerrar la temática libera recursos y resuelve la carga pendiente', async () => {
  let stopped = 0
  const worker = { postMessage() {}, terminate() { stopped++ } }
  const client = createSegmenter('./', { makeWorker: () => worker })
  client.dispose()
  assert.equal(await client.ready, false)
  assert.equal(stopped, 1)
})
