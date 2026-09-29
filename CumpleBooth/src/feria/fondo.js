// Fondo de pantalla del selector y del kiosco de un evento (Luis, 29-09: en un evento de Noche de Brujas "tiene fondo de
// la temática de fiestas patrias. Esto debe tener su propio fondo"). Cada temática puede traer el suyo en
// themes/<slug>/fondo-evento.jpg; el evento usa el de su primera temática con fondo propio (lo decide el servidor) y,
// sin ninguno, queda el genérico de siempre. Lógica pura, sin DOM, para probarla con node:test.
export const FONDO_GENERICO = 'feria/fondo.jpg'
const VALIDO = /^themes\/[a-z0-9][a-z0-9-]*\/fondo-evento\.jpg$/

/** La primera ruta válida entre las candidatas, o el fondo genérico. Nunca devuelve una ruta que no sea de esa forma:
 *  el valor termina dentro de un url("…") de CSS y no se confía ni en lo que manda el servidor. */
export function rutaFondo(...candidatas) {
  return candidatas.find((ruta) => typeof ruta === 'string' && VALIDO.test(ruta)) || FONDO_GENERICO
}

export const PENDONES_GENERICOS = ['#D52B1E', '#FFFFFF', '#0039A6']

/** Colores de los banderines de la franja de recuerdo: los tres primeros del confeti de la temática (29-09: en un
 *  evento de Noche de Brujas salían rojo, blanco y azul). Sin tres colores válidos, los de siempre. */
export function pendonesDe(confetti) {
  const validos = (Array.isArray(confetti) ? confetti : []).filter((c) => typeof c === 'string' && /^#[0-9a-f]{6}$/i.test(c))
  return validos.length >= 3 ? validos.slice(0, 3) : PENDONES_GENERICOS
}

/** Estilo con la variable --feria-fondo. La dirección va absoluta: una url() relativa dentro de una variable CSS se
 *  resuelve contra la hoja que la usa (assets/), no contra la página. */
export function fondoEstilo(base, here, ...candidatas) {
  return { '--feria-fondo': `url("${new URL(base + rutaFondo(...candidatas), here).href}")` }
}
