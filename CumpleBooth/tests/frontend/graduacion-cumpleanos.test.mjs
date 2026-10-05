// Graduación y Cumpleaños de gala (05-10-2026): año de la generación, edad válida, saludo al festejado, orden de las capas,
// placa del logo y archivos de las temáticas. El recorrido en la tablet está en feria-integracion.test.mjs.
import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync, existsSync } from 'node:fs'
import { resolve, join } from 'node:path'
import { anioDeGraduacion, textosGraduacion, edadValida, textosCumpleanos, capasGraduacion, capasCumpleanos, ZONAS_GRAD, ZONAS_CUMPLE } from '../../src/feria/celebraciones.js'

const root = resolve(import.meta.dirname, '../..')

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
    translate: () => {}, scale: () => {},
    fillRect: (...a) => llamadas.push(['fillRect', ...a]),
    fillText: (t, x, y, max) => llamadas.push(['fillText', t, x, y, max]),
    strokeText: () => {},
    drawImage: (img, x, y, w, h) => llamadas.push(['drawImage', x, y, w, h]),
    measureText: (t) => {
      const px = Number(/(\d+(?:\.\d+)?)px/.exec(font)?.[1] || 10)
      return { width: String(t).length * px * 0.6, actualBoundingBoxAscent: px * 0.72 }
    },
    createLinearGradient: () => gradiente,
    letterSpacing: '0px',
  }
}
const textos = (ctx) => ctx.llamadas.filter((l) => l[0] === 'fillText').map((l) => l[1])

test('el año de la generación es el de la fecha del evento', () => {
  assert.equal(anioDeGraduacion('2026-12-15'), 2026, 'una licenciatura de diciembre es de ese año, no del siguiente')
  assert.equal(anioDeGraduacion('', new Date(2027, 5, 1)), 2027)
  assert.deepEqual(textosGraduacion({ nombre: 'sofía', fecha: '2026-12-15' }), { anio: '2026', etiqueta: 'GENERACIÓN', saludo: '¡Felicitaciones!', nombre: 'SOFÍA' })
})

test('la edad solo vale de 1 a 120, y el saludo es para el festejado', () => {
  assert.equal(edadValida('40'), '40')
  assert.equal(edadValida(' 040 '), '40')
  for (const malo of ['0', '121', '4a', '', null, '-3']) assert.equal(edadValida(malo), '', 'rechaza ' + malo)
  assert.deepEqual(textosCumpleanos({ numero: 50, festejado: 'Ana' }), { anio: '50', saludo: '¡Feliz cumpleaños, Ana!', nombre: '' })
  assert.deepEqual(textosCumpleanos({}), { anio: '', saludo: '¡Feliz cumpleaños!', nombre: '' })
})

test('Graduación: GENERACIÓN y el año detrás, saludo y logo del colegio delante, coronilla bajo el año', () => {
  const W = 1080, H = 1920
  const capas = capasGraduacion(textosGraduacion({ nombre: 'Sofía', fecha: '2026-12-15' }), { logo: { width: 400, height: 200 } })
  const antes = lienzoDeMentira(); capas.antes(antes, W, H)
  assert.deepEqual(textos(antes), ['GENERACIÓN', '2026'])
  const base = antes.llamadas.filter((l) => l[0] === 'fillText')[1][3] / H
  assert.ok(base < ZONAS_GRAD.cabeza - 0.02, `el pie del año (${base.toFixed(3)}) queda sobre la coronilla`)
  const despues = lienzoDeMentira(); capas.despues(despues, W, H)
  assert.deepEqual(textos(despues), ['¡Felicitaciones!', 'SOFÍA'])
  const logo = despues.llamadas.find((l) => l[0] === 'drawImage')
  assert.ok(logo && Math.abs(logo[1] + logo[3] / 2 - W / 2) < 1 && logo[2] / H > 0.88, 'la placa del logo va centrada abajo')
  const sinLogo = lienzoDeMentira(); capasGraduacion(textosGraduacion({})).despues(sinLogo, W, H)
  assert.ok(!sinLogo.llamadas.some((l) => l[0] === 'drawImage'), 'sin logo no hay placa')
})

test('Cumpleaños: la edad detrás y el saludo delante; sin edad, solo el saludo y sin encuadre de portada', () => {
  const W = 1080, H = 1920
  const capas = capasCumpleanos(textosCumpleanos({ numero: 40, festejado: 'Ana' }))
  const antes = lienzoDeMentira(); capas.antes(antes, W, H)
  assert.deepEqual(textos(antes), ['40'])
  assert.ok(antes.llamadas.find((l) => l[0] === 'fillText')[3] / H < ZONAS_CUMPLE.cabeza - 0.02)
  const despues = lienzoDeMentira(); capas.despues(despues, W, H)
  assert.deepEqual(textos(despues), ['¡Feliz cumpleaños, Ana!'])
  const sinEdad = capasCumpleanos(textosCumpleanos({}))
  assert.equal(sinEdad.antes, null)
  assert.equal(sinEdad.encuadre, null)
})

test('las temáticas Graduación (para todos) y Cumpleaños de gala (adultos): rótulo e imágenes de 1080x1920', () => {
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
  assert.equal(temas.graduacion.audiencia, undefined, 'Graduación sirve en Niños y en Adultos (colegios y universidades)')
  assert.equal(temas['adulto-cumpleanos'].audiencia, 'adulto')
  for (const [slug, diseno] of [['graduacion', 'graduacion'], ['adulto-cumpleanos', 'cumpleanos']]) {
    const t = temas[slug]
    assert.equal(t.franquicia, null)
    assert.deepEqual(t.personajes, [])
    assert.deepEqual(t.rotulo, { diseno })
    for (const archivo of ['fondo-escena.jpg', 'fondo-sala.jpg', 'fondo-banner.jpg', 'fondo-evento.jpg']) {
      const ruta = join(root, 'public/themes', slug, archivo)
      assert.ok(existsSync(ruta), 'falta ' + slug + '/' + archivo)
      assert.deepEqual(dimensiones(ruta), [1080, 1920])
    }
  }
})
