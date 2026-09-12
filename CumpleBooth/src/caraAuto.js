/**
 * Asómate: el ajuste (zoom, dx, dy) que deja una cara detectada DENTRO del hueco.
 *
 * Antes la foto se muestreaba siempre en el centro del cuadro y a un tamaño fijo: la cara
 * salía bien solo si el niño estaba exactamente a la distancia y en el lugar que la guía
 * pedía. Con seis personajes probados en la tablet, solo Spidey quedó bien (Luis,
 * 2026-09-10). Con la cara detectada, se calcula el ajuste que la centra en el hueco y la
 * escala a `factor` veces el alto del hueco, y los mandos parten de ahí: el operador solo
 * afina.
 *
 * Es matemática pura sobre la misma fórmula del compositor (`rectFotoEnLienzo`): la caja de
 * la cara, dibujada con este ajuste, cae exactamente en el hueco.
 */
import { FOTO_POR_HUECO } from './asomateGuia.js'

const recortar = (v, min, max) => Math.min(max, Math.max(min, v))

/**
 * @param cara      {x, y, w, h} en píxeles de la foto (esquina superior izquierda y tamaño)
 * @param foto      {naturalWidth, naturalHeight}
 * @param personaje {rx, ry} del hueco (píxeles del PNG; solo importa la proporción)
 * @param opciones  factor: alto de la cara respecto del alto del hueco (1,1 = un poco más
 *                  grande, para que la frente y el mentón queden bajo el borde del traje);
 *                  zoomMin/zoomMax: el recorrido del mando de tamaño. dx y dy NO se topan:
 *                  una cara en la esquina del cuadro necesita correrse varios huecos para
 *                  quedar centrada, y topándola quedaba fuera; los mandos se estiran para
 *                  incluir el valor.
 */
export function ajusteParaCara(cara, foto, personaje, opciones = {}) {
  if (!cara || !(cara.w > 0) || !(cara.h > 0) || !foto || !personaje) return null
  const { factor = 1.1, zoomMin = 0.45, zoomMax = 3.5, desplazMax = Infinity } = opciones
  const W = foto.naturalWidth
  const H = foto.naturalHeight
  // La foto entera mide FOTO_POR_HUECO radios del hueco (= FOTO_POR_HUECO/2 huecos) con
  // zoom 1. Para que la cara mida `factor` huecos: zoom = factor·(2/FOTO_POR_HUECO)·(H/h).
  const zoom = recortar((2 * factor * H) / (FOTO_POR_HUECO * cara.h), zoomMin, zoomMax)
  // Con ese zoom, un píxel de la foto mide (FOTO_POR_HUECO·zoom/H) radios verticales del
  // hueco en el lienzo. dx va en radios horizontales, así que se corrige por ry/rx.
  const porPixel = (FOTO_POR_HUECO * zoom) / H
  const centroX = cara.x + cara.w / 2
  const centroY = cara.y + cara.h / 2
  const dx = recortar((W / 2 - centroX) * porPixel * (personaje.ry / personaje.rx), -desplazMax, desplazMax)
  const dy = recortar((H / 2 - centroY) * porPixel, -desplazMax, desplazMax)
  return { zoom, dx, dy }
}
