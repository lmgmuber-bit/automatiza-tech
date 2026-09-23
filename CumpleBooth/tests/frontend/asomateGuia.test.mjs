import assert from 'node:assert/strict'
import test from 'node:test'
import {
  FOTO_POR_HUECO,
  guiaEnPantalla,
  huecoEnFoto,
  rectFotoEnLienzo,
} from '../../src/asomateGuia.js'

// Spidey tal como está en themes.json: el hueco mide 187x202 dentro de un PNG de 711x1762.
const SPIDEY = { rx: 187, ry: 202 }
// Un sitio ya escalado en el lienzo (k = 1.6), como los arma componerAsomate.
const SITIO = { hx: 540, hy: 610, rx: 187 * 1.6, ry: 202 * 1.6 }
const SELFIE = { naturalWidth: 1280, naturalHeight: 720 }
const SELFIE_VERTICAL = { naturalWidth: 720, naturalHeight: 1280 }

const cerca = (a, b, msg) => assert.ok(Math.abs(a - b) < 1e-6, `${msg}: ${a} vs ${b}`)

/** Lleva un punto de la foto (píxeles de la foto) al lienzo a través del rectángulo donde se dibujó. */
function alLienzo(rect, foto, px, py) {
  return {
    x: rect.x + (px / foto.naturalWidth) * rect.ancho,
    y: rect.y + (py / foto.naturalHeight) * rect.alto,
  }
}

test('la foto entera mide cinco alturas del hueco con el ajuste en cero', () => {
  const rect = rectFotoEnLienzo(SITIO, SELFIE, {})
  cerca(rect.alto, SITIO.ry * FOTO_POR_HUECO, 'alto')
  cerca(rect.ancho, rect.alto * (1280 / 720), 'ancho conserva la proporción de la selfie')
  cerca(rect.x + rect.ancho / 2, SITIO.hx, 'centrada en el hueco (x)')
  cerca(rect.y + rect.alto / 2, SITIO.hy, 'centrada en el hueco (y)')
})

test('el óvalo guía de la foto cae EXACTAMENTE sobre el hueco del personaje', () => {
  for (const foto of [SELFIE, SELFIE_VERTICAL]) {
    const guia = huecoEnFoto(SPIDEY, foto)
    const rect = rectFotoEnLienzo(SITIO, foto, {})
    const centro = alLienzo(rect, foto, guia.cx, guia.cy)
    const borde = alLienzo(rect, foto, guia.cx + guia.rx, guia.cy + guia.ry)
    cerca(centro.x, SITIO.hx, 'centro x')
    cerca(centro.y, SITIO.hy, 'centro y')
    cerca(borde.x - centro.x, SITIO.rx, 'radio x')
    cerca(borde.y - centro.y, SITIO.ry, 'radio y')
  }
})

test('el óvalo guía conserva la forma del hueco de cada personaje', () => {
  const olaf = { rx: 168, ry: 233 }
  const g = huecoEnFoto(olaf, SELFIE)
  cerca(g.rx / g.ry, olaf.rx / olaf.ry, 'proporción')
  cerca(g.ry, 720 / FOTO_POR_HUECO, 'alto = un quinto de la foto')
})

test('dx corre la foto a la derecha y dy hacia abajo, en unidades del hueco', () => {
  const base = rectFotoEnLienzo(SITIO, SELFIE, {})
  const movida = rectFotoEnLienzo(SITIO, SELFIE, { dx: 0.5, dy: -0.25 })
  cerca(movida.x - base.x, SITIO.rx * 0.5, 'dx')
  cerca(movida.y - base.y, -SITIO.ry * 0.25, 'dy')
  cerca(movida.ancho, base.ancho, 'mover no cambia el tamaño')
})

test('zoom agranda la foto alrededor del centro del hueco', () => {
  const rect = rectFotoEnLienzo(SITIO, SELFIE, { zoom: 2 })
  cerca(rect.alto, SITIO.ry * FOTO_POR_HUECO * 2, 'alto')
  cerca(rect.x + rect.ancho / 2, SITIO.hx, 'sigue centrada (x)')
  cerca(rect.y + rect.alto / 2, SITIO.hy, 'sigue centrada (y)')
})

test('la guía en pantalla sigue al video con object-fit: cover', () => {
  // Tablet vertical (9:16) con cámara apaisada: el video llena el alto y se recorta a los lados.
  const vertical = guiaEnPantalla(SPIDEY, { videoWidth: 1280, videoHeight: 720 }, { ancho: 540, alto: 960 })
  cerca(vertical.cx, 270, 'centro x')
  cerca(vertical.cy, 480, 'centro y')
  cerca(vertical.ry, 960 / FOTO_POR_HUECO, 'alto: un quinto de la pantalla')
  cerca(vertical.rx, vertical.ry * (187 / 202), 'forma del hueco')

  // Misma tablet con una cámara que entrega el cuadro vertical: el resultado es el mismo.
  const camVertical = guiaEnPantalla(SPIDEY, { videoWidth: 720, videoHeight: 1280 }, { ancho: 540, alto: 960 })
  cerca(camVertical.ry, 960 / FOTO_POR_HUECO, 'alto con cámara vertical')

  // Tablet apaisada: el video llena el ancho y sobra alto... salvo que sea 16:9 exacto.
  const apaisada = guiaEnPantalla(SPIDEY, { videoWidth: 1280, videoHeight: 720 }, { ancho: 960, alto: 540 })
  cerca(apaisada.ry, 540 / FOTO_POR_HUECO, 'alto apaisada')

  // Pantalla más ancha que el video: cover escala por el ancho y el cuadro sobresale por arriba y abajo.
  const ancha = guiaEnPantalla(SPIDEY, { videoWidth: 1280, videoHeight: 720 }, { ancho: 1280, alto: 480 })
  cerca(ancha.ry, (720 / FOTO_POR_HUECO) * 1, 'escala 1 (1280/1280 > 480/720)')
  cerca(ancha.cy, 240, 'centrada en la caja aunque el cuadro sobresalga')
})
