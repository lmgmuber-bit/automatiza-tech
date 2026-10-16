/**
 * Guion de las voces del animador (AT-CUMPLECLICK-026): qué dice cada voz y con qué clave queda el audio.
 *
 * Una sola fuente para dos usos: el generador de audios (`scripts/voces-animador.mjs`, fuera del kiosco)
 * y el kiosco, que muestra en el centro de la pantalla el mismo texto que suena. Si cambia una tarjeta en
 * mazos.js, cambia acá y el generador sabe qué volver a grabar.
 *
 * Dos voces que se turnan por juego (`fem` y `masc`); los equipos sugeridos tienen voz fija. Las claves no
 * dependen de la fiesta; los audios sí (llevan el nombre y la edad), por eso viven solo en el servidor:
 * themes/<tema>/voces-fiesta-<slug>/indice.json + un mp3 por clave.
 */
import { rellenar } from '../cumpleAdulto.js'

export const VOCES = Object.freeze(['fem', 'masc'])

/** La otra voz: la que da las respuestas (una pregunta y la otra responde). */
export const otraVoz = (voz) => (voz === 'fem' ? 'masc' : 'fem')

/** La voz de un juego: se alternan en el orden del catálogo. */
export function vozDelJuego(juegos, juego) {
  const i = juegos.findIndex((j) => j.id === juego.id)
  return VOCES[(i < 0 ? 0 : i) % 2]
}

const ARRANQUES = ['¡Epa, mi gente!', '¡Atención, atención!', '¡Ahora sí, mi gente!', '¡Agárrense, que esto se pone bueno!']

const minuscula = (s) => (s ? s.charAt(0).toLowerCase() + s.slice(1) : s)

/** «Bienvenidos al juego…, consiste en…». El cierre «presionemos comenzar» va aparte, después de los equipos. */
export function textoIntro(juegos, juego, datos) {
  const i = Math.max(0, juegos.findIndex((j) => j.id === juego.id))
  const resumen = juego.guia?.resumen || ''
  const consiste = resumen ? ` Consiste en esto: ${minuscula(rellenar(resumen, datos))}` : ''
  const nombre = rellenar(juego.nombre, datos)
  return `${ARRANQUES[i % ARRANQUES.length]} Bienvenidos al juego ${nombre}${/[?!.]$/.test(nombre) ? '' : '.'}${consiste}`
}

/** Presentación de cada equipo sugerido; dos variantes para que no suene igual en todos los juegos. */
export const EQUIPOS_VOZ = Object.freeze([
  { voz: 'masc', textos: ['¡Por acá, el equipo de los Caballeros sospechosos!', '¡Y de este lado… los Caballeros sospechosos!'] },
  { voz: 'fem', textos: ['¡Y por acá está el equipo de las Cómplices peligrosas!', '¡Y aquí, mucho cuidado… las Cómplices peligrosas!'] },
  { voz: 'masc', textos: ['¡Por acá, los que todavía dan guerra!', '¡Y de este lado, los que todavía dan guerra!'] },
  { voz: 'fem', textos: ['¡Y aquí, el equipo Culpables y encantados!', '¡Y por acá, Culpables y encantados!'] },
])

export const TEXTO_COMENZAR = '¡Presionemos comenzar!'
export const TEXTO_TIEMPO = '¡Se acabó el tiempo!'

/**
 * Lo que dice la voz al cambiar de tarjeta, antes de leer la nueva (pedido de Luis, 16-oct): «Paso esta» = esa no
 * gustó; «Siguiente» = seguimos. Tres variantes de cada una que se turnan, para no repetir siempre la misma.
 */
export const TRANSICIONES = Object.freeze({
  pasar: [
    '¡Uy, esa no nos gustó! Vamos a la siguiente pregunta.',
    '¡Paso! Esa la dejamos pa\' otro día. ¡Siguiente!',
    'Mejor no… ¡vamos con otra!',
  ],
  siguiente: [
    '¡Muy bien! Vamos a la siguiente pregunta.',
    '¡Eso! Seguimos con la que viene.',
    '¡Bueno, bueno! Vamos con la siguiente.',
  ],
})

/**
 * El texto de una tarjeta tal como se lee. Sin comillas angulares (algunas voces las leen como pausa rara); «A + B»
 * se dice «A y B» (se leía «A más B»); y el paréntesis pasa a coma, porque la voz se lo saltaba («Me fui (Reymar
 * Perdomo)» sonaba «Me fui»). Lo que se ve en pantalla no cambia: sale de mazos.js.
 */
export const textoLeible = (texto) => String(texto || '')
  .replace(/[«»]/g, '"')
  .replace(/\s\+\s/g, ' y ')
  .replace(/\s*\(([^)]+)\)/g, ', $1,')
  .replace(/,\s*,/g, ',')
  .replace(/,\s*([.?!:;])/g, '$1')
  .replace(/,\s*$/, '')

export const claveIntro = (juego) => `intro:${juego.id}`
export const claveEquipo = (n, variante) => `equipo:${n}:${variante}`
export const claveComenzar = (voz) => `comenzar:${voz}`
export const claveTiempo = (voz) => `tiempo:${voz}`
export const claveCarta = (carta) => `carta:${carta.id}`
export const claveRespuesta = (carta) => `resp:${carta.id}`
export const claveTransicion = (tipo, voz, n) => `${tipo}:${voz}:${n % TRANSICIONES[tipo].length}`

/**
 * La ruleta del baile: lo que dice la voz al girar, al detenerse (sin pareja o en pareja) y al girar de nuevo. El
 * nombre de quien baila no se dice (cambia en cada fiesta): sale gigante en pantalla. La canción la dice la tarjeta.
 */
export const RULETA = Object.freeze({
  gira: ['¡La ruleta está girando… ¿a quién le tocará bailar?', '¡Atención, mi gente! ¿Quién será la estrella de la pista?'],
  solo: ['¡Te toca bailar sin pareja! Pura actitud… ¡pa\' la pista!'],
  pareja: ['¡Te toca bailar en pareja! Agarra a tu compañía… ¡y pa\' la pista!'],
  otra: ['¿No quiere? ¡Tranquilos, giramos otra vez!'],
})
export const claveRuleta = (tipo, n = 0) => `ruleta:${tipo}:${n % RULETA[tipo].length}`

/** Si no hay lista de invitados, la ruleta gira con frases y la sala decide quién es. */
export const RULETA_SIN_LISTA = Object.freeze([
  '👠 La más elegante', '🕶️ El de lentes', '⏰ Quien llegó último', '😂 La que más se ríe', '😐 El más serio',
  '❤️ Quien tenga algo rojo', '🎂 Quien está de cumpleaños', '🕺 El que más baila', '📱 Quien más mira el celular',
  '💄 La de los labios más rojos',
])
export const RULETA_EMOJIS = Object.freeze(['💃', '🕺', '🎉', '🔥', '✨', '🎶', '🥳', '🎤', '🌶️', '🪇'])

/**
 * Entre quiénes gira la ruleta: todos los invitados con nombre, o solo los hombres o solo las mujeres cuando la
 * canción es jocosa para uno de ellos (`para`). Si la lista no tiene a nadie de ese género, gira entre todos.
 */
export function candidatosRuleta(invitados, para = '') {
  const todos = (invitados || []).filter((i) => i && typeof i.name === 'string' && i.name.trim())
  if (!para) return todos
  const mismos = todos.filter((i) => i.g === para)
  return mismos.length ? mismos : todos
}

/** ¿La tarjeta se lee en voz alta? Las privadas (Mímica) nunca: solo las ve quien actúa. */
export const seLee = (juego, carta) => Boolean(carta) && !juego.privado

/** ¿La tarjeta tiene una respuesta que el reloj puede revelar? Los retos no. */
export const tieneRespuesta = (carta) => Boolean(carta?.respuesta) && carta.tipo !== 'reto'

/**
 * Todos los audios de una fiesta: [{ clave, voz, texto }]. `equipos`: los nombres sugeridos (índice =
 * EQUIPOS_VOZ). `datos`: { nombre, edad } de quien cumple.
 */
export function guionCompleto(juegos, datos) {
  const out = []
  for (const j of juegos) {
    const voz = vozDelJuego(juegos, j)
    out.push({ clave: claveIntro(j), voz, texto: textoIntro(juegos, j, datos) })
    if (j.ruleta) {
      for (const tipo of Object.keys(RULETA)) RULETA[tipo].forEach((texto, n) => out.push({ clave: claveRuleta(tipo, n), voz, texto }))
    }
    for (const c of j.cartas) {
      if (!seLee(j, c)) continue
      out.push({ clave: claveCarta(c), voz, texto: textoLeible(rellenar(c.texto, datos)) })
      if (tieneRespuesta(c)) out.push({ clave: claveRespuesta(c), voz: otraVoz(voz), texto: `La respuesta es: ${textoLeible(rellenar(c.respuesta, datos))}` })
    }
  }
  EQUIPOS_VOZ.forEach((e, n) => e.textos.forEach((t, v) => out.push({ clave: claveEquipo(n, v), voz: e.voz, texto: t })))
  for (const voz of VOCES) {
    out.push({ clave: claveComenzar(voz), voz, texto: TEXTO_COMENZAR })
    out.push({ clave: claveTiempo(voz), voz, texto: TEXTO_TIEMPO })
    for (const tipo of Object.keys(TRANSICIONES)) {
      TRANSICIONES[tipo].forEach((texto, n) => out.push({ clave: claveTransicion(tipo, voz, n), voz, texto }))
    }
  }
  return out
}

/**
 * La secuencia de la presentación de un juego: intro, un saludo por equipo y «presionemos comenzar».
 * Un equipo con nombre propio (no sugerido) aparece en pantalla sin audio.
 */
export function presentacion(juegos, juego, equipos, sugeridos, datos) {
  const voz = vozDelJuego(juegos, juego)
  const variante = Math.max(0, juegos.findIndex((j) => j.id === juego.id)) % 2
  const pasos = [{ clave: claveIntro(juego), texto: textoIntro(juegos, juego, datos) }]
  equipos.forEach((nombre) => {
    const n = sugeridos.findIndex((s) => s.trim().toLowerCase() === String(nombre).trim().toLowerCase())
    if (n >= 0 && EQUIPOS_VOZ[n]) pasos.push({ clave: claveEquipo(n, variante), texto: EQUIPOS_VOZ[n].textos[variante] })
    else pasos.push({ clave: null, texto: `¡Y por acá, el equipo ${nombre}!` })
  })
  pasos.push({ clave: claveComenzar(voz), texto: TEXTO_COMENZAR })
  return pasos
}
