/**
 * Cumpleaños de adulto (AT-CUMPLECLICK-026): los textos de la cabina, resueltos sin DOM.
 *
 * La temática no lleva el nombre de nadie: trae plantillas con `{nombre}` y `{edad}` y el
 * kiosco las llena con los datos de la fiesta. Si la fiesta no tiene edad, la frase cambia
 * entera en vez de dejar un hueco («Y todavía doy guerra», nunca « y todavía doy guerra»),
 * igual que el kiosco ya hace con un baby shower sin nombre.
 *
 * Todo lo que sale de acá se dibuja en un canvas o se pone como texto en el DOM: nunca HTML.
 */

export const CABINA_ADULTO_POR_DEFECTO = Object.freeze({
  titulo: 'gente con ganas de pasarlo bien',
  lema: '{edad} y todavía doy guerra',
  recuerdo: 'Cómplice oficial de {nombre}',
  frases: Object.freeze(['Culpable de coquetería', 'Con permiso: hoy doy guerra']),
  juego: Object.freeze({ label: '¡Atrapa la coquetería!', emojis: Object.freeze(['💋']) }),
})

const limpiar = (v) => String(v ?? '').replace(/\s+/g, ' ').trim()

function mayusculaInicial(texto) {
  return texto ? texto.charAt(0).toLocaleUpperCase('es-CL') + texto.slice(1) : texto
}

/** Reemplaza `{nombre}` y `{edad}`. Sin el dato, quita el marcador y lo que lo amarra. */
export function rellenar(plantilla, { nombre = '', edad = null } = {}) {
  let texto = limpiar(plantilla)
  const n = limpiar(nombre)
  const e = Number.isInteger(edad) && edad > 0 ? String(edad) : ''

  texto = e ? texto.replaceAll('{edad}', e) : texto.replace(/\{edad\}\s*/g, '')
  if (n) {
    texto = texto.replaceAll('{nombre}', n)
  } else {
    // «Cómplice oficial de {nombre}» → «Cómplice oficial de la fiesta».
    texto = texto.replace(/\s+de\s+\{nombre\}/g, ' de la fiesta').replace(/\{nombre\}\s*/g, '')
  }
  return mayusculaInicial(limpiar(texto))
}

/** El bloque de la temática con sus textos ya llenos; cae a los textos por defecto. */
export function cabinaAdulto(bloque, datos) {
  const b = bloque && typeof bloque === 'object' ? bloque : {}
  const frases = (Array.isArray(b.frases) && b.frases.length ? b.frases : CABINA_ADULTO_POR_DEFECTO.frases)
    .map((f) => rellenar(f, datos))
    .filter(Boolean)
  const juego = b.juego && Array.isArray(b.juego.emojis) && b.juego.emojis.length
    ? { label: limpiar(b.juego.label) || CABINA_ADULTO_POR_DEFECTO.juego.label, emojis: [...b.juego.emojis] }
    : { ...CABINA_ADULTO_POR_DEFECTO.juego, emojis: [...CABINA_ADULTO_POR_DEFECTO.juego.emojis] }
  return {
    titulo: limpiar(b.titulo) || CABINA_ADULTO_POR_DEFECTO.titulo,
    lema: rellenar(b.lema || CABINA_ADULTO_POR_DEFECTO.lema, datos),
    recuerdo: rellenar(b.recuerdo || CABINA_ADULTO_POR_DEFECTO.recuerdo, datos),
    frases: [...new Set(frases)],
    cinta: b.cinta || null,
    panel: b.panel || null,
    juego,
  }
}

/**
 * La línea de la cinta dorada: «MARTA PRUEBA» o, con edad, «MARTA PRUEBA · 61» (la edad es de la
 * celebrada, nunca del invitado de la foto). Sin nombre ni edad, la cinta queda vacía.
 */
export function textoCinta({ nombre = '', edad = null } = {}) {
  const partes = [limpiar(nombre).toLocaleUpperCase('es-CL')]
  if (Number.isInteger(edad) && edad > 0) partes.push(String(edad))
  return partes.filter(Boolean).join(' · ')
}

/**
 * Parte una frase en a lo más `maxLineas` renglones que quepan en `ancho`, según `medir`
 * (en el kiosco, `ctx.measureText`). Devuelve null si no cabe: quien llama achica la letra.
 */
export function partirEnLineas(texto, ancho, medir, maxLineas = 2) {
  const palabras = limpiar(texto).split(' ').filter(Boolean)
  if (!palabras.length) return []
  const lineas = []
  let actual = ''
  for (const palabra of palabras) {
    const prueba = actual ? `${actual} ${palabra}` : palabra
    if (medir(prueba) <= ancho) {
      actual = prueba
      continue
    }
    if (!actual) return null // una sola palabra más ancha que el panel
    lineas.push(actual)
    actual = palabra
  }
  if (actual) lineas.push(actual)
  return lineas.length <= maxLineas ? lineas : null
}
