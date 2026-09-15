/**
 * Arrastre de fichas en el rompecabezas (2026-09-15).
 *
 * En las fiestas del 13-sep los niños intentaban ARRASTRAR cada pieza en vez de tocar dos
 * (Luis). Estas dos funciones son la parte que se puede probar sin navegador: distinguir un
 * toque de un arrastre, y saber sobre qué casilla del tablero se soltó el dedo.
 */

/** Cuántos píxeles hay que mover el dedo para que cuente como arrastre y no como toque. */
export const UMBRAL_ARRASTRE_PX = 10

export function esArrastre(dx, dy, umbral = UMBRAL_ARRASTRE_PX) {
  return Math.hypot(dx, dy) >= umbral
}

/**
 * Casilla (0..cols*filas-1) del tablero que hay bajo el punto (x, y) de la pantalla, o -1 si
 * el punto cae fuera del tablero. `rect` es getBoundingClientRect() del tablero.
 */
export function celdaBajoPunto(rect, cols, filas, x, y) {
  if (!rect || rect.width <= 0 || rect.height <= 0 || cols < 1 || filas < 1) return -1
  const fx = (x - rect.left) / rect.width
  const fy = (y - rect.top) / rect.height
  if (fx < 0 || fx >= 1 || fy < 0 || fy >= 1) return -1
  const col = Math.min(cols - 1, Math.floor(fx * cols))
  const fila = Math.min(filas - 1, Math.floor(fy * filas))
  return fila * cols + col
}

/** El orden nuevo al soltar la pieza de `desde` sobre `hasta`; el mismo si no hay cambio. */
export function intercambiar(orden, desde, hasta) {
  if (desde === hasta || desde < 0 || hasta < 0 || desde >= orden.length || hasta >= orden.length) return orden
  const next = [...orden]
  ;[next[desde], next[hasta]] = [next[hasta], next[desde]]
  return next
}
