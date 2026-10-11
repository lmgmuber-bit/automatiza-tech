import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { rellenar, cabinaAdulto, textoCinta, partirEnLineas } from '../../src/cumpleAdulto.js'

// Datos ficticios: el repositorio es público y no lleva datos de fiestas reales.
const fiesta = { nombre: 'Marta Prueba', edad: 61 }

test('rellenar pone nombre y edad de la celebrada', () => {
  assert.equal(rellenar('{edad} y todavía doy guerra', fiesta), '61 y todavía doy guerra')
  assert.equal(rellenar('Cómplice oficial de {nombre}', fiesta), 'Cómplice oficial de Marta Prueba')
})

test('sin edad la frase cambia entera, sin hueco', () => {
  assert.equal(rellenar('{edad} y todavía doy guerra', { nombre: 'Marta' }), 'Y todavía doy guerra')
  assert.equal(rellenar('{edad} y todavía doy guerra', { nombre: 'Marta', edad: 0 }), 'Y todavía doy guerra')
})

test('sin nombre no queda un «de» colgando', () => {
  assert.equal(rellenar('Cómplice oficial de {nombre}', {}), 'Cómplice oficial de la fiesta')
  assert.equal(rellenar('Cómplice de {nombre}', { nombre: '   ' }), 'Cómplice de la fiesta')
})

test('cabinaAdulto llena las frases y descarta repetidas', () => {
  const c = cabinaAdulto({ frases: ['Cómplice de {nombre}', 'Cómplice de {nombre}', 'Culpable'], lema: '{edad} y todavía doy guerra' }, fiesta)
  assert.deepEqual(c.frases, ['Cómplice de Marta Prueba', 'Culpable'])
  assert.equal(c.lema, '61 y todavía doy guerra')
  assert.ok(c.juego.emojis.length > 0, 'siempre hay algo que atrapar')
})

test('cabinaAdulto sin bloque usa los textos por defecto', () => {
  const c = cabinaAdulto(null, {})
  assert.ok(c.frases.length >= 1)
  assert.equal(c.recuerdo, 'Cómplice oficial de la fiesta')
})

test('la cinta lleva el nombre en mayúsculas y la edad de la celebrada', () => {
  assert.equal(textoCinta(fiesta), 'MARTA PRUEBA · 61')
  assert.equal(textoCinta({ nombre: 'Marta' }), 'MARTA')
  assert.equal(textoCinta({}), '')
})

test('partirEnLineas respeta el ancho y avisa cuando no cabe', () => {
  const medir = (s) => s.length * 10
  assert.equal(partirEnLineas('Vine por la torta y me quedé', 150, medir), null, 'tres renglones no caben en dos')
  assert.deepEqual(partirEnLineas('Culpable de coquetería', 150, medir), ['Culpable de', 'coquetería'])
  assert.equal(partirEnLineas('Supercalifragilístico', 50, medir), null)
})

test('la temática genérica no trae el nombre ni datos de nadie', () => {
  const temas = JSON.parse(readFileSync(new URL('../../public/data/themes.json', import.meta.url), 'utf8')).themes
  const t = temas['adulto-coqueteria-pop']
  assert.ok(t, 'la temática existe')
  assert.equal(t.audiencia, 'adulto')
  assert.deepEqual(t.personajes, [], 'sin ruleta de personajes')
  const texto = JSON.stringify(t)
  assert.match(texto, /\{nombre\}/)
  assert.match(texto, /\{edad\}/)
  // El nombre y la edad entran solo por marcador: ningún texto los trae escritos.
  for (const f of t.cabinaAdulto.frases) assert.doesNotMatch(f, /\d/, `sin cifras fijas: ${f}`)
})
