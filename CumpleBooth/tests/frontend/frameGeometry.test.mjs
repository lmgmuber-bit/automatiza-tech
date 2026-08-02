import assert from 'node:assert/strict'
import test from 'node:test'
import {
  DEFAULT_FRAME_BOX,
  getSquarePhotoGeometry,
  getSquareFrameGeometry,
  getTrackCharacterGeometry,
  normalizeFrameBox,
} from '../../src/frameGeometry.js'

test('normaliza un frame válido recibido por la API', () => {
  assert.deepEqual(normalizeFrameBox({ x: 0.1, y: 0.2, w: 0.4, h: 0.3 }), {
    x: 0.1,
    y: 0.2,
    w: 0.4,
    h: 0.3,
  })
})

test('usa el fallback si el frame sale del canvas', () => {
  assert.deepEqual(normalizeFrameBox({ x: 0.8, y: 0.2, w: 0.4, h: 0.3 }), DEFAULT_FRAME_BOX)
})

test('calcula un marco cuadrado centrado dentro del frame normalizado', () => {
  const geometry = getSquareFrameGeometry({ x: 0.25, y: 0.25, w: 0.5, h: 0.25 }, 1080, 1920)
  assert.equal(geometry.cx, 540)
  assert.equal(geometry.cy, 720)
  assert.equal(geometry.side, 480)
  assert.equal(geometry.left, 300)
  assert.equal(geometry.top, 480)
})

test('inserta la foto sin cubrir el borde decorativo del fondo', () => {
  const geometry = getSquarePhotoGeometry({ x: 0.333, y: 0.285, w: 0.343, h: 0.279 }, 1080, 1920)
  assert.ok(geometry.photoLeft > geometry.left)
  assert.ok(geometry.photoTop > geometry.top)
  assert.equal(geometry.photoLeft + geometry.photoSide / 2, geometry.cx)
  assert.equal(geometry.photoTop + geometry.photoSide / 2, geometry.cy)
})

test('centra el personaje sobre la pista y limita su tamaño', () => {
  const placement = getTrackCharacterGeometry(1200, 700, 1080, 1920)
  assert.ok(placement)
  assert.equal(placement.left + placement.width / 2, 540)
  assert.equal(placement.bottom, 1920 * 0.88)
  assert.ok(placement.top >= 1920 * 0.58)
  assert.ok(placement.width <= 1080 * 0.62 + Number.EPSILON * 1000)
})

test('lower=true baja la pista del personaje (Frozen/K-Pop)', () => {
  const normal = getTrackCharacterGeometry(1200, 700, 1080, 1920)
  const lower = getTrackCharacterGeometry(1200, 700, 1080, 1920, true)
  assert.ok(lower)
  assert.equal(lower.bottom, normal.bottom + 1920 * 0.05)
  assert.equal(lower.top, normal.top + 1920 * 0.05)
})
