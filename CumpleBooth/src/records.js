/* ============================================================
   Récords por juego — el marcador de la fiesta.

   Cada juego guarda su mejor marca CON EL NOMBRE de quien la hizo, y la
   muestra en el HUD mientras el siguiente invitado juega. Ese es todo el
   punto: que el niño vea "🏆 240 · Sofía" arriba y quiera ganarle.

   Decisiones que importan:

   - VIVE EN localStorage, POR FIESTA. Misma llave que la lista de invitados
     (`STORAGE_KEY`), así una fiesta nueva arranca con el marcador limpio y no
     hereda los récords de la fiesta del sábado pasado. No va al servidor: el
     kiosco tiene que funcionar sin internet en el salón.

   - DOS SENTIDOS. En los juegos de puntaje gana el número MÁS ALTO; en los
     rompecabezas gana el tiempo MÁS BAJO. Por eso `modo`, y por eso el récord
     de tiempo se guarda en milisegundos y se muestra en segundos.

   - EMPATE NO ES RÉCORD. Solo se guarda si se supera de verdad, para que el
     cartel de "¡NUEVO RÉCORD!" signifique algo.
   ============================================================ */

let claveBase = null

/** La llama buildRuntime() cuando ya sabe de qué fiesta se trata. */
export function configurarRecords(storageKey) {
  claveBase = storageKey ? `${storageKey}_records` : null
}

function leerTodo() {
  if (!claveBase) return {}
  try {
    const crudo = localStorage.getItem(claveBase)
    const obj = crudo ? JSON.parse(crudo) : null
    return obj && typeof obj === 'object' ? obj : {}
  } catch {
    return {}
  }
}

/**
 * Récord vigente de un juego.
 * @returns {{valor:number, invitado:string}|null}
 */
export function leerRecord(juego) {
  const r = leerTodo()[juego]
  if (!r || typeof r.valor !== 'number' || !Number.isFinite(r.valor)) return null
  return { valor: r.valor, invitado: String(r.invitado || '') }
}

/**
 * Guarda si supera al vigente. Devuelve true solo cuando hubo récord nuevo,
 * que es lo que dispara el cartel en pantalla.
 * @param {'mayor'|'menor'} modo  'mayor' = puntaje, 'menor' = tiempo
 */
export function guardarRecord(juego, valor, invitado, modo = 'mayor') {
  if (!claveBase) return false
  if (typeof valor !== 'number' || !Number.isFinite(valor)) return false
  // Un cero en un juego de puntaje no es una marca: no ensucia el marcador
  // con "🏆 0 · Sofía" cuando alguien se saltó el juego sin jugar.
  if (modo === 'mayor' && valor <= 0) return false
  const actual = leerRecord(juego)
  const supera = !actual || (modo === 'menor' ? valor < actual.valor : valor > actual.valor)
  if (!supera) return false
  try {
    const todo = leerTodo()
    todo[juego] = { valor, invitado: String(invitado || '').slice(0, 24), at: Date.now() }
    localStorage.setItem(claveBase, JSON.stringify(todo))
  } catch {
    return false
  }
  return true
}

/** Texto corto para el HUD: "240 · Sofía". Vacío si todavía no hay récord. */
export function textoRecord(juego, formato = (v) => String(v)) {
  const r = leerRecord(juego)
  if (!r) return ''
  return r.invitado ? `${formato(r.valor)} · ${r.invitado}` : formato(r.valor)
}

/** Milisegundos → "12.4s", para los récords de tiempo. */
export function formatoSegundos(ms) {
  return `${(ms / 1000).toFixed(1)}s`
}
