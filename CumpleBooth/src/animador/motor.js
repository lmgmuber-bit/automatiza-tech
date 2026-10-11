/**
 * Motor de los juegos del animador (AT-CUMPLECLICK-026), sin DOM.
 *
 * Un animador, una tablet. El animador elige el nivel máximo, arma los equipos y conduce:
 * siguiente tarjeta, paso esta, revelar, cronómetro y puntos. Reglas que el motor garantiza:
 *  - el nivel es un TECHO: Coqueto muestra solo coqueto; Picante, coqueto y picante;
 *    Sinvergüenza, los tres. Se filtra antes de barajar y nunca se sube solo;
 *  - el mazo no repite hasta agotarse, tampoco al salir del juego y volver: cada juego
 *    recuerda sus tarjetas ya usadas en la partida. Agotado, hay que reiniciarlo a propósito;
 *  - «Paso esta» descarta la tarjeta sin castigo;
 *  - un punto se anota una sola vez por ronda y equipo aunque se toque dos veces,
 *    y deshacer quita el último punto anotado (queda visible en el historial);
 *  - abrir un juego siempre empieza una ronda nueva, aunque no tenga tarjetas (Beso al blanco):
 *    lo anotado en el juego anterior no bloquea el primer punto del siguiente;
 *  - un juego con `preparacion` (Tribunal) no se abre hasta que el animador confirme que la
 *    tiene lista; la confirmación es un sí/no, nunca las respuestas.
 * Todo es estado plano y serializable. Lo que se guarda en sessionStorage pasa por
 * `serializarSesion` y vuelve por `restaurarSesion`, que descarta cualquier forma incompleta.
 */

export const NIVELES = Object.freeze(['coqueto', 'picante', 'sinverguenza'])

/** Versión de lo que se guarda en sessionStorage. Si cambia la forma, se sube y lo viejo se descarta. */
export const VERSION_GUARDADO = 2

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
    rondaInicio: 0, // primera ronda del juego abierto (para contar lanzamientos)
    juego: null,
    mazo: [], // ids pendientes del juego abierto
    carta: null, // id de la tarjeta en pantalla
    revelada: false,
    agotado: false,
    usadas: {}, // { [juegoId]: ids ya mostrados en esta partida }
    preparados: [], // ids de juegos cuya preparación confirmó el animador
  }
}

const usadasDe = (sesion, juegoId) => (sesion.usadas && sesion.usadas[juegoId]) || []

/**
 * Entra a un juego. El mazo son las tarjetas del nivel que este juego todavía no mostró en la
 * partida, barajadas; si ya no queda ninguna, el juego abre agotado y no se rebaraja solo.
 * Siempre empieza una ronda nueva, con o sin tarjeta.
 */
export function abrirJuego(sesion, juego, rng = Math.random) {
  const usadas = new Set(usadasDe(sesion, juego.id))
  const ids = barajar(cartasDelNivel(juego, sesion.nivel).map((c) => c.id).filter((id) => !usadas.has(id)), rng)
  const base = { ...sesion, juego: juego.id, mazo: ids, carta: null, revelada: false, agotado: false, rondaInicio: sesion.ronda + 1 }
  if (juego.sinCartas) return { ...base, ronda: sesion.ronda + 1 }
  const s = siguiente(base)
  // Agotado al entrar: igual es una ronda nueva, para que los botones de puntos no hereden la anterior.
  return s.agotado ? { ...s, ronda: sesion.ronda + 1 } : s
}

/** Siguiente tarjeta (o la primera). Una ronda nueva por tarjeta, para el control de puntos. */
export function siguiente(sesion) {
  if (!sesion.mazo.length) {
    return { ...sesion, carta: null, revelada: false, agotado: true }
  }
  const [carta, ...resto] = sesion.mazo
  const usadas = sesion.juego
    ? { ...(sesion.usadas || {}), [sesion.juego]: [...usadasDe(sesion, sesion.juego), carta] }
    : sesion.usadas || {}
  return { ...sesion, carta, mazo: resto, usadas, revelada: false, ronda: sesion.ronda + 1, agotado: false }
}

/** Ronda nueva sin tarjeta (juegos físicos como Beso al blanco: cada lanzamiento es una ronda). */
export const nuevaRonda = (sesion) => ({ ...sesion, ronda: sesion.ronda + 1 })

/** «Paso esta»: lo mismo que siguiente, sin puntos para nadie. */
export const pasar = (sesion) => siguiente(sesion)

export const revelar = (sesion) => ({ ...sesion, revelada: true })

/** Volver a barajar un mazo agotado: solo a pedido del animador. Olvida las usadas de ese juego. */
export function reiniciarMazo(sesion, juego, rng = Math.random) {
  const usadas = { ...(sesion.usadas || {}) }
  delete usadas[juego.id]
  return abrirJuego({ ...sesion, usadas }, juego, rng)
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

/** ¿Este juego pide una preparación que el animador todavía no confirmó? */
export function necesitaPreparacion(sesion, juego) {
  return Boolean(juego?.preparacion) && !(sesion.preparados || []).includes(juego.id)
}

/** El animador confirma que tiene lista la preparación del juego (solo el sí, nunca el contenido). */
export function confirmarPreparacion(sesion, juego) {
  const preparados = sesion.preparados || []
  return preparados.includes(juego.id) ? sesion : { ...sesion, preparados: [...preparados, juego.id] }
}

/** El juego que reemplaza a uno sin preparar, solo si cabe bajo el techo del nivel actual. */
export function alternativaDisponible(juegos, juego, nivel) {
  const id = juego?.preparacion?.alternativa
  if (!id) return null
  return juegosDelNivel(juegos, nivel).find((j) => j.id === id) || null
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

/**
 * Lo que se guarda para sobrevivir a un recargón: nivel, equipos, puntos, ronda, ids de tarjetas
 * ya usadas por juego (son del catálogo genérico) y qué juegos se confirmaron preparados.
 * Nunca lo que se dijo en la ronda ni respuestas.
 */
export function serializarSesion(sesion) {
  const { nivel, equipos, puntos, ronda, usadas = {}, preparados = [] } = sesion
  return { v: VERSION_GUARDADO, nivel, equipos, puntos, ronda, usadas, preparados }
}

const entero = (n, min, max = Number.MAX_SAFE_INTEGER) => Number.isInteger(n) && n >= min && n <= max
const listaDeTextos = (l) => Array.isArray(l) && l.every((x) => typeof x === 'string' && x.length > 0 && x.length <= 64)
const esObjetoPlano = (o) => o !== null && typeof o === 'object' && !Array.isArray(o)

/**
 * Valida lo leído de sessionStorage y devuelve una sesión lista para el menú de juegos, o null
 * si no calza en algo (versión, nivel, equipos, puntos, ronda, usadas, preparados). Con null,
 * quien llama descarta la clave y vuelve a Preparar: nada a medias.
 */
export function restaurarSesion(datos) {
  if (!esObjetoPlano(datos) || datos.v !== VERSION_GUARDADO) return null
  const { nivel, equipos, puntos, ronda, usadas, preparados } = datos
  if (!NIVELES.includes(nivel)) return null
  if (!Array.isArray(equipos) || equipos.length < 2 || equipos.length > 4) return null
  if (!equipos.every((e) => typeof e === 'string' && e.trim() && e.length <= 64)) return null
  if (!entero(ronda, 0)) return null
  if (!Array.isArray(puntos)) return null
  for (const p of puntos) {
    if (!esObjetoPlano(p)) return null
    if (!entero(p.ronda, 0, ronda) || !entero(p.equipo, 0, equipos.length - 1) || !entero(p.valor, 1, 3)) return null
    if (p.juego !== null && typeof p.juego !== 'string') return null
  }
  if (!esObjetoPlano(usadas) || !Object.values(usadas).every(listaDeTextos)) return null
  if (!listaDeTextos(preparados)) return null
  return {
    ...nuevaSesion({ nivel, equipos }),
    equipos: [...equipos],
    puntos: puntos.map(({ ronda: r, equipo, valor, juego }) => ({ ronda: r, equipo, valor, juego })),
    ronda,
    rondaInicio: ronda,
    usadas: Object.fromEntries(Object.entries(usadas).map(([k, l]) => [k, [...l]])),
    preparados: [...preparados],
  }
}
