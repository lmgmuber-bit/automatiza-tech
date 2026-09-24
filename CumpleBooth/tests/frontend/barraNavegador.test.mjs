import test from 'node:test'
import assert from 'node:assert/strict'

/**
 * El color de la barra del navegador.
 *
 * `index.html` y `album.html` traían `<meta name="theme-color" content="#e8000d">`
 * escrito a mano. Ese valor no es "el rojo de CumpleClick": es el `accent` de
 * CARRERAS, la primera temática que existió. Así que el kiosco de un baby
 * shower verde entero abría con una franja roja arriba en Chrome Android, y lo
 * mismo cualquier temática que no fuera Carreras.
 *
 * No lo detectaba ningún test porque el HTML era válido y la página se veía
 * bien: el color equivocado está fuera del viewport, en el cromo del navegador.
 * Lo reportó Luis con una captura del celular (2026-08-31).
 *
 * Se prueba con un `document` de mentira en vez de a punta de regex sobre el
 * archivo: lo que importa es que la función DEJE el meta con el color del tema,
 * no cómo esté escrita.
 */
function documentoFalso(metaInicial) {
  const raiz = { style: { props: {}, setProperty(k, v) { this.props[k] = v } } }
  let meta = metaInicial === undefined ? null : { atributos: { name: 'theme-color', content: metaInicial },
    setAttribute(k, v) { this.atributos[k] = v },
    getAttribute(k) { return this.atributos[k] } }
  const head = { hijos: [], appendChild(n) { this.hijos.push(n); return n } }
  return {
    documentElement: raiz,
    head,
    querySelector: (sel) => (sel === 'meta[name="theme-color"]' ? meta : null),
    createElement: () => {
      meta = { atributos: {}, setAttribute(k, v) { this.atributos[k] = v }, getAttribute(k) { return this.atributos[k] } }
      return meta
    },
    get meta() { return meta },
  }
}

const SAFARI = { accent: '#7A9455', ink: '#33402A', dark1: '#3E5233' }
const CARRERAS = { accent: '#e8000d', ink: '#2b1a12', dark1: '#b30009' }

async function conDocumento(doc, fn) {
  const previo = globalThis.document
  globalThis.document = doc
  try {
    // Import fresco por llamada: el módulo no guarda estado, pero así el test
    // no depende del orden en que corran los demás.
    const mod = await import('../../src/themeVars.js?' + Math.random())
    return fn(mod)
  } finally {
    if (previo === undefined) delete globalThis.document
    else globalThis.document = previo
  }
}

test('la barra del navegador toma el color de la temática, no el rojo de Carreras', async () => {
  const doc = documentoFalso('#e8000d')
  await conDocumento(doc, ({ applyThemeColors }) => applyThemeColors(SAFARI))
  assert.equal(doc.meta.getAttribute('content'), '#7A9455')
})

test('Carreras conserva exactamente el color que ya tenía', async () => {
  const doc = documentoFalso('#e8000d')
  await conDocumento(doc, ({ applyThemeColors }) => applyThemeColors(CARRERAS))
  assert.equal(doc.meta.getAttribute('content'), '#e8000d')
})

test('si la página no trae el meta, se crea', async () => {
  const doc = documentoFalso(undefined)
  await conDocumento(doc, ({ applyThemeColors }) => applyThemeColors(SAFARI))
  assert.equal(doc.meta.getAttribute('name'), 'theme-color')
  assert.equal(doc.meta.getAttribute('content'), '#7A9455')
  assert.equal(doc.head.hijos.length, 1)
})

test('un tema sin accent no deja la barra en blanco: se queda como estaba', async () => {
  const doc = documentoFalso('#e8000d')
  await conDocumento(doc, ({ applyThemeColors }) => applyThemeColors({ ink: '#111' }))
  assert.equal(doc.meta.getAttribute('content'), '#e8000d')
})
