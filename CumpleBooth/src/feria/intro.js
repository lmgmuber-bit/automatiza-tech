// Tiempos de la intro y la despedida de la temática en la feria. Regla (Luis, 28-09: en la feria la intro se saltaba y
// caía directo a la ruleta): la intro se ve SIEMPRE, como en una fiesta. El video se baja de antemano (videoListo.js);
// si aun así no arranca o falla, queda una tarjeta con el nombre un mínimo de tiempo antes de seguir. Lógica pura, sin
// DOM, para probarla con node:test (tests/frontend/feriaIntro.test.mjs).
export const INTRO_MINIMO_MS = 4000 // lo mínimo que se ve la intro, con video o con tarjeta
export const INTRO_ARRANQUE_MS = 8000 // cuánto se espera a que el video arranque antes de pasar a la tarjeta
export const INTRO_TOPE_MS = 45000 // tope para un video que avanza a tirones (el mismo de ListaInvitados en App.jsx)
export const INTRO_MARGEN_MS = 600

/** Cuánto falta para cumplir el mínimo desde que apareció la intro. */
export function restanteMinimo(desde, ahora, minimo = INTRO_MINIMO_MS) {
  return Math.max(0, minimo - (ahora - desde))
}

/** Con los metadatos leídos: cuánto esperar según la duración real del video (tope 30 s); null si no se conoce. */
export function esperaPorDuracion(duracionSeg) {
  const d = Number(duracionSeg)
  return Number.isFinite(d) && d > 0 ? Math.min(30000, Math.round(d * 1000 + INTRO_MARGEN_MS)) : null
}

/**
 * Qué hacer cuando vence el temporizador de seguridad. `video` es un objeto plano {ended, paused, currentTime,
 * duration} o null. Devuelve:
 *  - {accion:'esperar', ms, tiempo} si el video sigue avanzando y no se pasó del tope: se le da lo que le falta;
 *  - {accion:'tarjeta', ms} si el video no avanza y todavía no se cumple el mínimo: se muestra la tarjeta ese tiempo;
 *  - {accion:'terminar'} si el video no avanza y el mínimo ya se cumplió.
 */
export function alVencer({ video, ultimoTiempo, desde, ahora, minimo = INTRO_MINIMO_MS }) {
  const avanza = Boolean(video) && !video.ended && !video.paused && video.currentTime > ultimoTiempo + 0.05
  if (avanza && ahora - desde < INTRO_TOPE_MS && Number.isFinite(video.duration)) {
    const ms = Math.max(800, Math.round((video.duration - video.currentTime) * 1000 + INTRO_MARGEN_MS))
    return { accion: 'esperar', ms, tiempo: video.currentTime }
  }
  const falta = restanteMinimo(desde, ahora, minimo)
  return falta > 0 ? { accion: 'tarjeta', ms: falta } : { accion: 'terminar' }
}
