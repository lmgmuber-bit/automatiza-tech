import test from 'node:test'
import assert from 'node:assert/strict'
import { buildPages } from '../../src/album/pages.js'

const base = {
  album: { title: 'Los recuerdos de Isidora', subtitle: 'Reino de Hielo', coverId: null },
  event: { name: 'Isidora', date: '2026-08-15' },
  theme: { name: 'Reino de Hielo', assets: { banner: 'themes/hielo/fondo-banner.jpg' } },
  media: [],
}

const foto = (id, extra = {}) => ({
  id, kind: 'image', source: 'guest', url: `u${id}`, thumb: `t${id}`,
  width: 1200, height: 800, author: null, message: null, ...extra,
})

const layouts = (pages) => pages.map((page) => page.layout)

test('un álbum vacío igual tiene portada y cierre', () => {
  const pages = buildPages(base)
  assert.deepEqual(layouts(pages), ['cover', 'closing'])
  assert.equal(pages[0].title, 'Los recuerdos de Isidora')
  // Sin fotos, la portada cae al banner de la temática en vez de quedar vacía.
  assert.equal(pages[0].image, null)
  assert.equal(pages[0].fallback, 'themes/hielo/fondo-banner.jpg')
})

test('la portada elegida por el organizador no se repite adentro', () => {
  const pages = buildPages({
    ...base,
    album: { ...base.album, coverId: 2 },
    media: [foto(1), foto(2), foto(3)],
  })
  assert.equal(pages[0].image.id, 2)
  const dentro = pages.slice(1).flatMap((page) => page.items ?? []).map((item) => item.id)
  assert.deepEqual(dentro, [1, 3])
})

test('sin portada elegida se usa la primera foto', () => {
  const pages = buildPages({ ...base, media: [foto(7), foto(8)] })
  assert.equal(pages[0].image.id, 7)
})

// En estas pruebas la portada se fija en la id 1 para que el interior quede
// sin ambigüedad; sin coverId el armador usa igual la primera foto.
const conPortada1 = (media) => buildPages({ ...base, album: { ...base.album, coverId: 1 }, media })

test('cuatro fotos seguidas sin mensaje forman un mosaico', () => {
  const pages = conPortada1([foto(1), foto(2), foto(3), foto(4), foto(5)])
  assert.deepEqual(layouts(pages), ['cover', 'mosaic', 'closing', 'blank'])
  assert.equal(pages[1].items.length, 4)
  assert.deepEqual(pages[1].items.map((item) => item.id), [2, 3, 4, 5])
})

test('el resto suelto se reparte en mosaico y dúo, sin perder ninguna', () => {
  const media = [foto(1), foto(2), foto(3), foto(4), foto(5), foto(6), foto(7)]
  const pages = conPortada1(media)
  const dentro = pages.flatMap((page) => page.items ?? []).map((item) => item.id)
  assert.deepEqual(dentro, [2, 3, 4, 5, 6, 7])
  assert.deepEqual(layouts(pages), ['cover', 'mosaic', 'duo', 'closing'])
})

test('una foto con mensaje corta la racha y se lleva su propia página', () => {
  const pages = conPortada1([
    foto(1),
    foto(2, { message: 'Qué linda fiesta', author: 'Tía Rosa' }),
    foto(3),
  ])
  assert.deepEqual(layouts(pages), ['cover', 'note', 'full', 'closing'])
  assert.equal(pages[1].items[0].id, 2)
  assert.equal(pages[1].items[0].message, 'Qué linda fiesta')
})

test('un video siempre va en su propia página', () => {
  const pages = conPortada1([foto(1), { ...foto(2), kind: 'video', duration: 12 }, foto(3)])
  assert.deepEqual(layouts(pages), ['cover', 'video', 'full', 'closing'])
  assert.equal(pages[1].items[0].kind, 'video')
})

test('un video nunca se usa de portada aunque sea lo primero', () => {
  const pages = buildPages({
    ...base,
    media: [{ ...foto(1), kind: 'video' }, foto(2)],
  })
  assert.equal(pages[0].image.id, 2)
  assert.equal(pages[0].image.kind, 'image')
})

test('el orden del organizador manda: nunca se reordena', () => {
  const media = [
    foto(5),
    foto(1, { message: 'hola' }),
    foto(9),
    { ...foto(3), kind: 'video' },
    foto(7),
  ]
  // La 5 se va a la portada; el interior conserva el orden exacto del resto.
  const pages = buildPages({ ...base, media })
  assert.equal(pages[0].image.id, 5)
  const dentro = pages.flatMap((page) => page.items ?? []).map((item) => item.id)
  assert.deepEqual(dentro, [1, 9, 3, 7])
})

test('con total impar se agrega una hoja en blanco para cerrar el pliego', () => {
  // La primera foto se va a la portada, así que queda: cover + note + closing
  // = 3 páginas. El pliego de escritorio muestra de a dos: hay que emparejar.
  const pages = buildPages({
    ...base,
    media: [foto(1), foto(2, { message: 'Gracias por invitarnos', author: 'Abuelo Juan' })],
  })
  assert.deepEqual(layouts(pages), ['cover', 'note', 'closing', 'blank'])
  assert.equal(pages.length % 2, 0)
})

test('con total par no se agrega hoja en blanco', () => {
  const pages = buildPages({ ...base, media: [foto(1)] })
  assert.deepEqual(layouts(pages), ['cover', 'closing'])
})

test('la página de cierre cuenta todos los recuerdos, incluida la portada', () => {
  const pages = buildPages({ ...base, media: [foto(1), foto(2), foto(3)] })
  const cierre = pages.find((page) => page.layout === 'closing')
  assert.equal(cierre.count, 3)
  assert.equal(cierre.eventName, 'Isidora')
})

test('datos incompletos no revientan el armado', () => {
  const pages = buildPages({})
  assert.deepEqual(layouts(pages), ['cover', 'closing'])
  assert.equal(pages[0].title, 'Álbum Recuerdo')
})
