import assert from 'node:assert/strict'
import test from 'node:test'
import { prepararVideo, urlDeVideo, olvidarVideos } from '../../src/videoListo.js'

const URL_RED = 'themes/spidey/despedida-spidey.mp4?v=abc123'

function fetchFalso({ ok = true, falla = false, cuentas = { llamadas: 0 } } = {}) {
  return async (url) => {
    cuentas.llamadas += 1
    cuentas.ultima = url
    if (falla) throw new Error('sin red')
    return {
      ok,
      status: ok ? 200 : 503,
      blob: async () => ({ size: 2691071, type: 'video/mp4' }),
    }
  }
}

const crearUrlFalso = (blob) => `blob:kiosco/${blob.size}`

test('sin preparar, la URL de red se usa tal cual', () => {
  olvidarVideos()
  assert.equal(urlDeVideo(URL_RED), URL_RED)
})

test('preparado con éxito, la despedida sale del blob y no de la red', async () => {
  olvidarVideos()
  const cuentas = { llamadas: 0 }
  const listo = await prepararVideo(URL_RED, { fetchFn: fetchFalso({ cuentas }), crearUrl: crearUrlFalso })
  assert.equal(listo, 'blob:kiosco/2691071')
  assert.equal(urlDeVideo(URL_RED), 'blob:kiosco/2691071')
  assert.equal(cuentas.llamadas, 1)
  assert.equal(cuentas.ultima, URL_RED, 'se pide exactamente la URL versionada')
})

test('se descarga una sola vez aunque se pida varias veces', async () => {
  olvidarVideos()
  const cuentas = { llamadas: 0 }
  const opciones = { fetchFn: fetchFalso({ cuentas }), crearUrl: crearUrlFalso }
  await Promise.all([prepararVideo(URL_RED, opciones), prepararVideo(URL_RED, opciones)])
  await prepararVideo(URL_RED, opciones)
  assert.equal(cuentas.llamadas, 1)
})

test('si la red falla, se cae a la URL original sin romper nada', async () => {
  olvidarVideos()
  const listo = await prepararVideo(URL_RED, { fetchFn: fetchFalso({ falla: true }), crearUrl: crearUrlFalso })
  assert.equal(listo, URL_RED)
  assert.equal(urlDeVideo(URL_RED), URL_RED)
})

test('una respuesta que no es 200 tampoco se usa', async () => {
  olvidarVideos()
  const listo = await prepararVideo(URL_RED, { fetchFn: fetchFalso({ ok: false }), crearUrl: crearUrlFalso })
  assert.equal(listo, URL_RED)
})

test('mientras baja, urlDeVideo sigue dando la de red (no se espera a nadie)', async () => {
  olvidarVideos()
  let soltar
  const fetchLento = () => new Promise((ok) => { soltar = ok })
  const pendiente = prepararVideo(URL_RED, { fetchFn: fetchLento, crearUrl: crearUrlFalso })
  assert.equal(urlDeVideo(URL_RED), URL_RED)
  soltar({ ok: true, status: 200, blob: async () => ({ size: 7, type: 'video/mp4' }) })
  assert.equal(await pendiente, 'blob:kiosco/7')
  assert.equal(urlDeVideo(URL_RED), 'blob:kiosco/7')
})

test('sin URL no hace nada', async () => {
  olvidarVideos()
  assert.equal(await prepararVideo('', { fetchFn: fetchFalso(), crearUrl: crearUrlFalso }), '')
  assert.equal(urlDeVideo(''), '')
})
