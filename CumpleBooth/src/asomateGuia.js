/**
 * Asómate: la correspondencia entre el óvalo de la FOTO y el hueco del PERSONAJE.
 *
 * Hay dos óvalos que tienen que ser el mismo: el hueco recortado en el PNG del personaje
 * (donde se ve la cara) y la porción de la selfie que cae dentro de ese hueco. Esta es la
 * única fuente de esa relación: la usan el compositor (para dibujar la foto en el lienzo)
 * y la cámara (para dibujar la guía sobre el video), así que no pueden desacordarse.
 *
 * Sin la guía, el niño no sabía dónde poner la cara: la foto se muestreaba en el centro
 * del cuadro a un tamaño fijo, y la cara salía chica o corrida (2026-09-10, fotos reales
 * de Luis: la cara a la izquierda del hueco en Ghost-Spider y a la derecha en Elsa).
 */

/** Cuántas alturas del hueco mide la foto entera con el ajuste en cero. La cara ocupa como
 * un tercio del alto de una selfie; con cinco, la cara llena el hueco sin que el niño
 * tenga que pegarse a la cámara. */
export const FOTO_POR_HUECO = 5

/**
 * Dónde se dibuja la foto dentro del lienzo, para un sitio ya escalado
 * ({hx, hy, rx, ry}: centro y radios del hueco en píxeles del lienzo).
 * `ajuste` trae {zoom, dx, dy}; dx y dy van en unidades del radio del hueco.
 */
export function rectFotoEnLienzo(sitio, foto, ajuste = {}) {
  const alto = sitio.ry * FOTO_POR_HUECO * (ajuste.zoom || 1)
  const ancho = (alto * foto.naturalWidth) / foto.naturalHeight
  return {
    x: sitio.hx - ancho / 2 + sitio.rx * (ajuste.dx || 0),
    y: sitio.hy - alto / 2 + sitio.ry * (ajuste.dy || 0),
    ancho,
    alto,
  }
}

/**
 * El óvalo de la foto que va a caer dentro del hueco, en píxeles de la PROPIA foto y con el
 * ajuste en cero: centrado, un quinto del alto, con la forma del hueco del personaje.
 */
export function huecoEnFoto(personaje, foto) {
  const ry = foto.naturalHeight / FOTO_POR_HUECO
  return {
    cx: foto.naturalWidth / 2,
    cy: foto.naturalHeight / 2,
    rx: ry * (personaje.rx / personaje.ry),
    ry,
  }
}

/**
 * Ese mismo óvalo sobre el <video> de la cámara, en píxeles CSS de la caja que lo muestra.
 * El video va con object-fit: cover, así que se escala por el lado que más falta y queda
 * centrado; el óvalo sigue esa misma escala y ese mismo centro.
 */
export function guiaEnPantalla(personaje, video, caja) {
  const escala = Math.max(caja.ancho / video.videoWidth, caja.alto / video.videoHeight)
  const enFoto = huecoEnFoto(personaje, {
    naturalWidth: video.videoWidth,
    naturalHeight: video.videoHeight,
  })
  return {
    cx: caja.ancho / 2,
    cy: caja.alto / 2,
    rx: enFoto.rx * escala,
    ry: enFoto.ry * escala,
  }
}
