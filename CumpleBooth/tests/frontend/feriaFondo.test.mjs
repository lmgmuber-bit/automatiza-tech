import test from 'node:test'
import assert from 'node:assert/strict'
import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { FONDO_GENERICO, PENDONES_GENERICOS, fondoEstilo, pendonesDe, rutaFondo } from '../../src/feria/fondo.js'

const root = resolve(import.meta.dirname, '../..')

// Luis (29-09): un evento de Noche de Brujas mostraba el fondo de Fiestas Patrias. Cada temática trae su fondo y el
// evento usa el de su temática principal; sin fondo propio queda el genérico, nunca una pantalla sin fondo.

test('gana la primera candidata válida: la temática elegida antes que el evento', () => {
  assert.equal(rutaFondo('themes/brujitas/fondo-evento.jpg', 'themes/hielo/fondo-evento.jpg'), 'themes/brujitas/fondo-evento.jpg')
  assert.equal(rutaFondo('', 'themes/hielo/fondo-evento.jpg'), 'themes/hielo/fondo-evento.jpg')
  assert.equal(rutaFondo(undefined, null, ''), FONDO_GENERICO)
  assert.equal(rutaFondo(), FONDO_GENERICO)
})

test('una ruta que no es el fondo de una temática se descarta: el valor termina dentro de un url() de CSS', () => {
  for (const mala of ['https://otro.sitio/fondo.jpg', '../config/secreto.jpg', 'themes/hielo/fondo-sala.jpg', 'themes/../fondo-evento.jpg',
    'themes/hielo/fondo-evento.jpg")', 'themes/HIELO/fondo-evento.jpg', 'themes/hielo/x/fondo-evento.jpg', 42, {}]) {
    assert.equal(rutaFondo(mala), FONDO_GENERICO, String(mala))
  }
})

test('el estilo lleva la dirección absoluta, también bajo /app/', () => {
  assert.deepEqual(fondoEstilo('/app/', 'https://cumpleclick.com/app/feria.html?f=x', 'themes/brujitas/fondo-evento.jpg'),
    { '--feria-fondo': 'url("https://cumpleclick.com/app/themes/brujitas/fondo-evento.jpg")' })
  assert.deepEqual(fondoEstilo('/', 'http://localhost:5230/?p=x', ''), { '--feria-fondo': 'url("http://localhost:5230/feria/fondo.jpg")' })
})

test('el genérico existe y las temáticas de los eventos de fin de año traen fondo propio en 9:16', () => {
  assert.ok(existsSync(resolve(root, 'public', FONDO_GENERICO)))
  for (const slug of ['brujitas', 'navidad', 'fiestas-patrias']) {
    const archivo = resolve(root, 'public/themes', slug, 'fondo-evento.jpg')
    assert.ok(existsSync(archivo), slug + ' sin fondo-evento.jpg')
    const b = readFileSync(archivo)
    // JPEG: buscar el marcador SOF0/SOF2 y leer alto y ancho.
    let i = 2, ancho = 0, alto = 0
    while (i < b.length) {
      if (b[i] !== 0xff) { i++; continue }
      const m = b[i + 1]
      if (m === 0xc0 || m === 0xc2) { alto = b.readUInt16BE(i + 5); ancho = b.readUInt16BE(i + 7); break }
      i += 2 + b.readUInt16BE(i + 2)
    }
    assert.equal(ancho + 'x' + alto, '1080x1920', slug)
  }
})

test('los banderines de la franja toman los colores de la temática', () => {
  assert.deepEqual(pendonesDe(['#F26A1B', '#5B2A86', '#FFFFFF', '#FFC94A']), ['#F26A1B', '#5B2A86', '#FFFFFF'])
  assert.deepEqual(pendonesDe(['#D52B1E', '#FFFFFF', '#0039A6', '#F2C14E']), PENDONES_GENERICOS)
  for (const malo of [undefined, null, [], ['#fff', 'rojo', '#12345'], ['#F26A1B', '#5B2A86'], 'texto']) assert.deepEqual(pendonesDe(malo), PENDONES_GENERICOS)
})

test('el selector baja solo hasta la autorización y el botón al elegir temática', () => {
  const selector = readFileSync(resolve(root, 'src/feria/selector.jsx'), 'utf8')
  assert.match(selector, /scrollIntoView\(\{ behavior: reduced\.current \? 'auto' : 'smooth', block: 'end' \}\)/)
  assert.match(selector, /ref=\{continuar\}/)
  assert.match(readFileSync(resolve(root, 'src/feria/feria.css'), 'utf8'), /\.feria-primary\{[^}]*scroll-margin-bottom/)
})
