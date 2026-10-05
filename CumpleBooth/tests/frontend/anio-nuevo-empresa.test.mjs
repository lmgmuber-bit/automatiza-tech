// Año Nuevo y Muro de prensa (Empresa), 04-10-2026: el año que se celebra, el orden de las capas, el tono del logo y la grilla
// del muro sin navegador, y los archivos de las dos temáticas. El recorrido en la tablet está en feria-integracion.test.mjs.
import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync, existsSync } from 'node:fs'
import { resolve, join } from 'node:path'
import { anioQueSeCelebra, textosAnioNuevo, capasAnioNuevo, ZONAS_ANIO } from '../../src/feria/anioNuevo.js'
import { tonoDeLogo, grillaMuro, caber, capasMuroLogos } from '../../src/feria/muroLogos.js'

const root = resolve(import.meta.dirname, '../..')

// Contexto 2D de mentira: anota lo que se dibuja (texto, imágenes) para revisar el orden y las posiciones.
function lienzoDeMentira() {
  const llamadas = []
  let font = '10px serif'
  const gradiente = { addColorStop: () => {} }
  return {
    llamadas,
    get font() { return font },
    set font(v) { font = v },
    save: () => llamadas.push(['save']),
    restore: () => llamadas.push(['restore']),
    translate: (...a) => llamadas.push(['translate', ...a]),
    scale: (...a) => llamadas.push(['scale', ...a]),
    fillRect: (...a) => llamadas.push(['fillRect', ...a]),
    fillText: (t, x, y, max) => llamadas.push(['fillText', t, x, y, max]),
    strokeText: (t, x, y, max) => llamadas.push(['strokeText', t, x, y, max]),
    drawImage: (img, x, y, w, h) => llamadas.push(['drawImage', x, y, w, h]),
    measureText: (t) => {
      const px = Number(/(\d+(?:\.\d+)?)px/.exec(font)?.[1] || 10)
      return { width: String(t).length * px * 0.6, actualBoundingBoxAscent: px * 0.72 }
    },
    createLinearGradient: () => gradiente,
    createRadialGradient: () => gradiente,
    letterSpacing: '0px',
  }
}
const textos = (ctx) => ctx.llamadas.filter((l) => l[0] === 'fillText').map((l) => l[1])

test('el año que se celebra: noviembre y diciembre celebran el que viene', () => {
  assert.equal(anioQueSeCelebra('2026-12-31'), 2027)
  assert.equal(anioQueSeCelebra('2026-11-28'), 2027, 'las fiestas de fin de año de las empresas parten en noviembre')
  assert.equal(anioQueSeCelebra('2027-01-01'), 2027)
  assert.equal(anioQueSeCelebra('2026-10-04'), 2026)
  assert.equal(anioQueSeCelebra('', new Date(2026, 11, 20)), 2027, 'sin fecha, se mira la de hoy')
})

test('los textos de Año Nuevo: el año, el saludo y el nombre en mayúsculas', () => {
  assert.deepEqual(textosAnioNuevo({ nombre: ' josé ', fecha: '2026-12-31' }), { anio: '2027', saludo: '¡Feliz Año Nuevo!', nombre: 'JOSÉ' })
  assert.equal(textosAnioNuevo({ fecha: '2026-12-31' }).nombre, '', 'sin nombre no se escribe nada debajo')
})

test('Año Nuevo: el año va detrás de la persona, el saludo delante, y la coronilla queda bajo los números', () => {
  const W = 1080, H = 1920
  const capas = capasAnioNuevo(textosAnioNuevo({ nombre: 'Camila', fecha: '2026-12-31' }))
  const antes = lienzoDeMentira(); capas.antes(antes, W, H)
  assert.deepEqual(textos(antes), ['2027'])
  const despues = lienzoDeMentira(); capas.despues(despues, W, H)
  assert.deepEqual(textos(despues), ['¡Feliz Año Nuevo!', 'CAMILA'])
  const base = antes.llamadas.find((l) => l[0] === 'fillText')[3] / H
  assert.ok(base < capas.encuadre.cabeza - 0.03, `el pie del año (${base.toFixed(3)}) queda sobre la coronilla (${capas.encuadre.cabeza})`)
  assert.equal(capas.encuadre.cabeza, ZONAS_ANIO.cabeza)
  for (const ctx of [antes, despues]) assert.equal(ctx.llamadas.reduce((n, l) => n + (l[0] === 'save') - (l[0] === 'restore'), 0), 0)
})

// Píxeles RGBA de mentira: `cuantos` de un color, más `transparentes` vacíos.
const pixeles = (color, cuantos, transparentes = 0) => {
  const out = []
  for (let i = 0; i < cuantos; i++) out.push(...color, 255)
  for (let i = 0; i < transparentes; i++) out.push(0, 0, 0, 0)
  return Uint8ClampedArray.from(out)
}

test('el tono del logo decide el color del muro', () => {
  assert.equal(tonoDeLogo(pixeles([255, 255, 255], 300, 700)), 'oscuro', 'logo blanco sobre transparente: muro oscuro')
  assert.equal(tonoDeLogo(pixeles([30, 58, 138], 300, 700)), 'claro', 'logo azul oscuro: muro claro')
  assert.equal(tonoDeLogo(pixeles([255, 215, 0], 300, 700)), 'oscuro', 'logo amarillo: sobre blanco no se lee')
  const jpg = Uint8ClampedArray.from([...pixeles([255, 255, 255], 800), ...pixeles([200, 30, 30], 200)])
  assert.equal(tonoDeLogo(jpg), 'claro', 'JPG con fondo blanco y dibujo rojo: se mira solo el dibujo')
  assert.equal(tonoDeLogo(pixeles([255, 255, 255], 1000)), 'claro', 'JPG todo blanco: da igual, muro claro')
})

test('la grilla del muro cubre todo el lienzo, con una fila corrida medio paso', () => {
  const W = 1080, H = 1920
  const centros = grillaMuro(W, H)
  assert.ok(centros.some((c) => c.x <= 0) && centros.some((c) => c.x >= W), 'pasa de las orillas: no quedan huecos')
  assert.ok(Math.min(...centros.map((c) => c.y)) < H * 0.1 && Math.max(...centros.map((c) => c.y)) > H * 0.9)
  const filas = [...new Set(centros.map((c) => Math.round(c.y)))]
  const primera = centros.filter((c) => Math.round(c.y) === filas[0]).map((c) => c.x)
  const segunda = centros.filter((c) => Math.round(c.y) === filas[1]).map((c) => c.x)
  assert.ok(Math.abs(Math.abs(primera[0] - segunda[0]) - W * 0.34 / 2) < 1, 'corrida medio paso')
})

test('caber mete la imagen en la caja sin deformarla', () => {
  assert.deepEqual(caber(600, 200, 240, 120), { w: 240, h: 80 })
  assert.deepEqual(caber(100, 400, 240, 120), { w: 30, h: 120 })
})

test('el muro: con logo lo repite en cada celda; sin logo, repite en letras el nombre de quien organiza', () => {
  const W = 1080, H = 1920
  const conLogo = lienzoDeMentira()
  const capas = capasMuroLogos({ logo: { width: 600, height: 200 }, tono: 'claro' })
  capas.antes(conLogo, W, H)
  assert.equal(conLogo.llamadas.filter((l) => l[0] === 'drawImage').length, grillaMuro(W, H).length)
  assert.equal(capas.encuadre, null, 'sin encuadre de portada: el logo se ve alrededor de la cabeza')
  assert.equal(capas.despues, null)
  const sinLogo = lienzoDeMentira()
  capasMuroLogos({ texto: 'Empresa Demo' }).antes(sinLogo, W, H)
  const letras = textos(sinLogo)
  assert.equal(letras.length, grillaMuro(W, H).length)
  assert.ok(letras.every((t) => t === 'EMPRESA DEMO'))
  const oscuro = lienzoDeMentira()
  capasMuroLogos({ logo: { width: 600, height: 200 }, tono: 'oscuro' }).antes(oscuro, W, H)
  assert.deepEqual(oscuro.llamadas.find((l) => l[0] === 'fillRect').slice(1), [0, 0, W, H], 'muro oscuro: se pinta entero antes de los logos')
})

test('las temáticas Año Nuevo y Muro de prensa: adultas, con su rótulo y sus cuatro imágenes de 1080x1920', () => {
  const temas = JSON.parse(readFileSync(join(root, 'public/data/themes.json'), 'utf8')).themes
  const dimensiones = (archivo) => {
    const b = readFileSync(archivo)
    for (let i = 2; i < b.length;) {
      const marca = b[i + 1], largo = b.readUInt16BE(i + 2)
      if (marca >= 0xc0 && marca <= 0xc3) return [b.readUInt16BE(i + 7), b.readUInt16BE(i + 5)]
      i += 2 + largo
    }
    return null
  }
  for (const [slug, diseno] of [['adulto-anio-nuevo', 'anioNuevo'], ['adulto-empresa', 'muroLogos']]) {
    const t = temas[slug]
    assert.ok(t, slug + ' registrada')
    assert.equal(t.audiencia, 'adulto')
    assert.equal(t.franquicia, null)
    assert.deepEqual(t.personajes, [])
    assert.equal(t.modoFoto, 'fondo')
    assert.deepEqual(t.rotulo, { diseno })
    for (const archivo of ['fondo-escena.jpg', 'fondo-sala.jpg', 'fondo-banner.jpg', 'fondo-evento.jpg']) {
      const ruta = join(root, 'public/themes', slug, archivo)
      assert.ok(existsSync(ruta), 'falta ' + slug + '/' + archivo)
      assert.deepEqual(dimensiones(ruta), [1080, 1920], slug + '/' + archivo + ' mide 1080x1920')
    }
  }
})
