// Aritmética del paginador del admin (public/admin/paginador.js): páginas, rangos y
// qué botones dibujar. La parte de DOM se mira en el navegador con el servidor local.
import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import vm from 'node:vm'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

// El archivo se incluye inline en album.php y no es un módulo ES (el package.json del kiosco
// declara "type": "module", así que un require() directo no sirve): se evalúa como en el
// navegador, con un `module` de CommonJS para recoger la API.
const fuente = readFileSync(join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'public', 'admin', 'paginador.js'), 'utf8')
const contexto = { module: { exports: {} }, window: undefined }
vm.runInNewContext(fuente, contexto)
// Lo que sale del contexto tiene otros prototipos (otro "reino"): se pasa por JSON para que
// deepEqual estricto compare valores y no prototipos.
const plano = (x) => JSON.parse(JSON.stringify(x))
const ccPaginas = (...a) => plano(contexto.module.exports.ccPaginas(...a))
const ccRangoPaginas = (...a) => plano(contexto.module.exports.ccRangoPaginas(...a))
const TAMANOS = plano(contexto.module.exports.TAMANOS)

test('los tamaños ofrecidos son los que pidió Luis', () => {
  assert.deepEqual(TAMANOS, [10, 20, 50, 100])
})

test('84 fotos de a 20 son 5 páginas; la última va del 81 al 84', () => {
  assert.deepEqual(ccPaginas(84, 20, 1), { total: 84, porPagina: 20, paginas: 5, actual: 1, desde: 1, hasta: 20 })
  assert.deepEqual(ccPaginas(84, 20, 5), { total: 84, porPagina: 20, paginas: 5, actual: 5, desde: 81, hasta: 84 })
})

test('una página fuera de rango se acota, y un tamaño raro cae a 20', () => {
  assert.equal(ccPaginas(84, 20, 99).actual, 5)
  assert.equal(ccPaginas(84, 20, -3).actual, 1)
  assert.equal(ccPaginas(84, 33, 1).porPagina, 20)
  assert.equal(ccPaginas(84, 'abc', 1).porPagina, 20)
})

test('sin elementos hay una sola página vacía', () => {
  assert.deepEqual(ccPaginas(0, 10, 4), { total: 0, porPagina: 10, paginas: 1, actual: 1, desde: 0, hasta: 0 })
})

test('exactamente 100 de a 100 es una página completa', () => {
  const p = ccPaginas(100, 100, 1)
  assert.equal(p.paginas, 1)
  assert.equal(p.hasta, 100)
})

test('con pocas páginas se dibujan todas; con muchas, primera, última y una ventana con puntos', () => {
  assert.deepEqual(ccRangoPaginas(5, 3), [1, 2, 3, 4, 5])
  assert.deepEqual(ccRangoPaginas(20, 1), [1, 2, 3, 4, 5, 6, '…', 20])
  assert.deepEqual(ccRangoPaginas(20, 10), [1, '…', 8, 9, 10, 11, 12, '…', 20])
  assert.deepEqual(ccRangoPaginas(20, 20), [1, '…', 15, 16, 17, 18, 19, 20])
})

test('el rango siempre contiene la página actual, la primera y la última', () => {
  for (let paginas = 1; paginas <= 30; paginas++) {
    for (let actual = 1; actual <= paginas; actual++) {
      const r = ccRangoPaginas(paginas, actual)
      assert.ok(r.includes(actual), `falta la actual ${actual} de ${paginas}: ${r}`)
      assert.equal(r[0], 1)
      assert.equal(r[r.length - 1], paginas)
      assert.ok(r.length <= 9, `demasiados botones para ${paginas}/${actual}: ${r.length}`)
    }
  }
})
