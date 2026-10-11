/**
 * Motor de los juegos del animador (AT-CUMPLECLICK-026), sin DOM.
 *
 * Un animador, una tablet. El animador elige el nivel máximo, arma los equipos y conduce:
 * siguiente tarjeta, paso esta, revelar, cronómetro y puntos. Reglas que el motor garantiza:
 *  - el nivel es un TECHO: Coqueto muestra solo coqueto; Picante, coqueto y picante;
 *    Sinvergüenza, los tres. Se filtra antes de barajar y nunca se sube solo;
 *  - el mazo no repite hasta agotarse; agotado, hay que reiniciarlo a propósito;
 *  - «Paso esta» descarta la tarjeta sin castigo;
 *  - un punto se anota una sola vez por ronda y equipo aunque se toque dos veces,
 *    y deshacer quita el último punto anotado (queda visible en el historial).
 * Todo es estado plano y serializable: se puede guardar en sessionStorage tal cual.
 */

export const NIVELES = Object.freeze(['coqueto', 'picante', 'sinverguenza'])

export function rangoNivel(nivel) {
  const i = NIVELES.indexOf(nivel)
  return i < 0 ? 0 : i
}

/** Las tarjetas que caben bajo el techo del nivel. */
export function cartasDelNivel(juego, nivel) {
  const techo = rangoNivel(nivel)
  return (juego?.cartas || []).filter((c) => rangoNivel(c.tono) <= techo)
}

/** Los juegos que tienen al menos una tarjeta para el nivel (los demás se ocultan). */
export function juegosDelNivel(juegos, nivel) {
  return juegos.filter((j) => j.sinCartas || cartasDelNivel(j, nivel).length > 0)
}

/** Fisher-Yates con un generador inyectable (en las pruebas, uno fijo). */
export function barajar(lista, rng = Math.random) {
  const out = [...lista]
  for (let i = out.length - 1; i > 0; i--) {
    const j = Math.floor(rng() * (i + 1))
    ;[out[i], out[j]] = [out[j], out[i]]
  }
  return out
}

export function nuevaSesion({ nivel = 'coqueto', equipos = [] } = {}) {
  const nombres = equipos.map((e) => String(e || '').trim()).filter(Boolean).slice(0, 4)
  return {
    nivel: NIVELES.includes(nivel) ? nivel : 'coqueto',
    equipos: nombres.length >= 2 ? nombres : ['Equipo 1', 'Equipo 2'],
    puntos: [], // { ronda, equipo, valor, juego }
    ronda: 0,
    juego: null,
    mazo: [], // ids pendientes
    carta: null, // id de la tarjeta en pantalla
    revelada: false,
    agotado: false,
  }
}

/** Entra a un juego: mazo nuevo con las tarjetas del nivel, barajadas, y saca la primera. */
export function abrirJuego(sesion, juego, rng = Math.random) {
  const ids = barajar(cartasDelNivel(juego, sesion.nivel).map((c) => c.id), rng)
  return siguiente({ ...sesion, juego: juego.id, mazo: ids, carta: null, agotado: false }, rng)
}

/** Siguiente tarjeta (o la primera). Una ronda nueva por tarjeta, para el control de puntos. */
export function siguiente(sesion) {
  if (!sesion.mazo.length) {
    return { ...sesion, carta: null, revelada: false, agotado: true }
  }
  const [carta, ...resto] = sesion.mazo
  return { ...sesion, carta, mazo: resto, revelada: false, ronda: sesion.ronda + 1, agotado: false }
}

/** Ronda nueva sin tarjeta (juegos físicos como Beso al blanco: cada lanzamiento es una ronda). */
export const nuevaRonda = (sesion) => ({ ...sesion, ronda: sesion.ronda + 1 })

/** «Paso esta»: lo mismo que siguiente, sin puntos para nadie. */
export const pasar = (sesion) => siguiente(sesion)

export const revelar = (sesion) => ({ ...sesion, revelada: true })

/** Volver a barajar un mazo agotado: solo a pedido del animador. */
export function reiniciarMazo(sesion, juego, rng = Math.random) {
  return abrirJuego(sesion, juego, rng)
}

/** Bajar (o subir) el nivel: si la tarjeta en pantalla queda sobre el techo, se descarta. */
export function cambiarNivel(sesion, nivel, juego) {
  const s = { ...sesion, nivel: NIVELES.includes(nivel) ? nivel : sesion.nivel }
  if (!juego) return s
  const permitidas = new Set(cartasDelNivel(juego, s.nivel).map((c) => c.id))
  const mazo = s.mazo.filter((id) => permitidas.has(id))
  const actualFuera = s.carta && !permitidas.has(s.carta)
  return actualFuera ? siguiente({ ...s, mazo }) : { ...s, mazo }
}

/** Anota puntos a un equipo en la ronda actual. Repetir el mismo toque no suma dos veces. */
export function anotar(sesion, equipo, valor) {
  const v = Math.max(0, Math.min(3, Math.round(Number(valor) || 0)))
  if (!v || equipo < 0 || equipo >= sesion.equipos.length) return sesion
  const ya = sesion.puntos.some((p) => p.ronda === sesion.ronda && p.equipo === equipo)
  if (ya) return sesion
  return { ...sesion, puntos: [...sesion.puntos, { ronda: sesion.ronda, equipo, valor: v, juego: sesion.juego }] }
}

/** Deshace el último punto anotado. */
export function deshacer(sesion) {
  return sesion.puntos.length ? { ...sesion, puntos: sesion.puntos.slice(0, -1) } : sesion
}

export function marcador(sesion) {
  const totales = sesion.equipos.map(() => 0)
  for (const p of sesion.puntos) totales[p.equipo] += p.valor
  return totales
}

/** Ganadores (puede haber empate: el premio se comparte). */
export function ganadores(sesion) {
  const t = marcador(sesion)
  const max = Math.max(...t)
  return max > 0 ? sesion.equipos.filter((_, i) => t[i] === max) : []
}

export function yaAnoto(sesion, equipo) {
  return sesion.puntos.some((p) => p.ronda === sesion.ronda && p.equipo === equipo)
}
