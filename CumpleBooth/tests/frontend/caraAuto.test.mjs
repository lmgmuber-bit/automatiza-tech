import assert from 'node:assert/strict'
import test from 'node:test'
import { ajusteParaCara } from '../../src/caraAuto.js'
import { rectFotoEnLienzo } from '../../src/asomateGuia.js'

const SELFIE = { naturalWidth: 1280, naturalHeight: 720 }
const SELFIE_VERTICAL = { naturalWidth: 720, naturalHeight: 1280 }
const SPIDEY = { rx: 187, ry: 202 }
const OLAF = { rx: 168, ry: 233 } // hueco alto y angosto
// Un sitio ya escalado en el lienzo (k = 1.6), como los arma componerAsomate.
const sitioDe = (p, k = 1.6) => ({ hx: 540, hy: 610, rx: p.rx * k, ry: p.ry * k })

const cerca = (a, b, msg, tol = 1e-6) => assert.ok(Math.abs(a - b) < tol, `${msg}: ${a} vs ${b}`)

/** Dónde cae en el lienzo la caja de la cara, dibujando la foto con el ajuste calculado. */
function caraEnLienzo(cara, foto, personaje, ajuste) {
  const sitio = sitioDe(personaje)
  const r = rectFotoEnLienzo(sitio, foto, ajuste)
  const ex = r.ancho / foto.naturalWidth
  const ey = r.alto / foto.naturalHeight
  return {
    cx: r.x + (cara.x + cara.w / 2) * ex,
    cy: r.y + (cara.y + cara.h / 2) * ey,
    alto: cara.h * ey,
    sitio,
  }
}

test('una cara centrada que ya llena el hueco no necesita ajuste', () => {
  const cara = { x: 640 - 130, y: 360 - 144, w: 260, h: 288 } // 40% del alto, centrada
  const a = ajusteParaCara(cara, SELFIE, SPIDEY, { factor: 1 })
  cerca(a.zoom, 1, 'zoom')
  cerca(a.dx, 0, 'dx')
  cerca(a.dy, 0, 'dy')
})

test('la cara queda centrada en el hueco y con la altura pedida, esté donde esté', () => {
  const casos = [
    { foto: SELFIE, personaje: SPIDEY, cara: { x: 100, y: 80, w: 150, h: 170 } }, // chica, arriba a la izquierda
    { foto: SELFIE, personaje: OLAF, cara: { x: 900, y: 400, w: 220, h: 250 } }, // abajo a la derecha, hueco angosto
    { foto: SELFIE_VERTICAL, personaje: SPIDEY, cara: { x: 400, y: 900, w: 200, h: 230 } }, // cámara vertical
  ]
  for (const { foto, personaje, cara } of casos) {
    const a = ajusteParaCara(cara, foto, personaje, { factor: 1.1 })
    const en = caraEnLienzo(cara, foto, personaje, a)
    cerca(en.cx, en.sitio.hx, 'centro x', 1e-6)
    cerca(en.cy, en.sitio.hy, 'centro y', 1e-6)
    cerca(en.alto, 2 * en.sitio.ry * 1.1, 'alto = 1,1 veces el hueco', 1e-6)
  }
})

test('el factor manda cuánto del hueco llena la cara', () => {
  const cara = { x: 500, y: 200, w: 200, h: 200 }
  const justa = caraEnLienzo(cara, SELFIE, SPIDEY, ajusteParaCara(cara, SELFIE, SPIDEY, { factor: 1 }))
  const grande = caraEnLienzo(cara, SELFIE, SPIDEY, ajusteParaCara(cara, SELFIE, SPIDEY, { factor: 1.3 }))
  cerca(justa.alto, 2 * justa.sitio.ry, 'factor 1: la cara mide lo que el hueco')
  cerca(grande.alto / justa.alto, 1.3, 'factor 1,3: 30% más grande')
})

test('el tamaño se topa; el corrimiento no, para que una cara en la esquina igual quede centrada', () => {
  const diminuta = ajusteParaCara({ x: 600, y: 340, w: 30, h: 36 }, SELFIE, SPIDEY, { factor: 1.1, zoomMax: 3.5 })
  cerca(diminuta.zoom, 3.5, 'zoom topado')
  const cara = { x: 0, y: 0, w: 120, h: 140 }
  const esquina = ajusteParaCara(cara, SELFIE, SPIDEY, { factor: 1.1 })
  assert.ok(esquina.dx > 2 && esquina.dy > 0, `una cara arriba a la izquierda se corre mucho a la derecha y algo abajo: ${esquina.dx}, ${esquina.dy}`)
  const en = caraEnLienzo(cara, SELFIE, SPIDEY, esquina)
  cerca(en.cx, en.sitio.hx, 'centrada igual (x)')
  cerca(en.cy, en.sitio.hy, 'centrada igual (y)')
})

test('sin cara no hay ajuste', () => {
  assert.equal(ajusteParaCara(null, SELFIE, SPIDEY), null)
  assert.equal(ajusteParaCara({ x: 0, y: 0, w: 0, h: 0 }, SELFIE, SPIDEY), null)
})
