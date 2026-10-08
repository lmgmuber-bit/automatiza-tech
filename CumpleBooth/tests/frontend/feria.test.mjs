import test from 'node:test'
import assert from 'node:assert/strict'
import { apiQuery, feriaFromResponse, firstName, returnUrl, kioskUrl, enabledModes, initialPhotoStep, filterFor, reservation, upload, armIdle, footerLines } from '../../src/feria/contract.js'

test('una fiesta normal no activa feria aunque su URL tenga modo y tema', () => {
  assert.equal(feriaFromResponse({ ok: true, party: {}, theme: {} }), null)
  assert.equal(apiQuery('?p=fiesta'), 'p=fiesta')
  assert.equal(apiQuery('?p=fiesta&modo=adulto&tema=estudio'), 'p=fiesta&tema=estudio&modo=adulto')
})
test('solo el servidor activa feria y decide la modalidad', () => {
  assert.equal(feriaFromResponse({ feria: { slug: 'f', modo: 'inventado' } }), null)
  assert.equal(feriaFromResponse({ feria: { slug: 'f', modo: 'adulto' } }).modo, 'adulto')
})
test('nombre opcional, primer nombre Unicode, 20 caracteres, sin etiquetas ni números', () => {
  assert.equal(firstName('  María   José '), 'María')
  assert.equal(firstName('Ana-Élise'), 'Ana-Élise')
  assert.equal(firstName('123'), '')
  assert.equal([...firstName('á'.repeat(40))].length, 20)
})
test('volver se limita al selector local de la misma feria, también bajo /app/', () => {
  const here = 'https://ejemplo.test/app/?p=feria'
  const expected = 'https://ejemplo.test/app/feria.html?f=feria'
  for (const candidate of ['https://otro.test/', '//otro.test/feria.html', 'javascript:alert(1)', '/admin/', './feria.html?f=otra', './feria.html?f=feria&extra=1']) {
    assert.equal(returnUrl(candidate, 'feria', here), expected)
  }
  assert.equal(returnUrl('./feria.html?f=feria', 'feria', here), expected)
  const target = new URL(kioskUrl('feria', 'mundo', 'adulto', expected))
  assert.equal(target.searchParams.get('volver'), expected)
  assert.equal(target.searchParams.get('tema'), 'mundo')
})
test('mundos y recorridos: sin mundos no hay botón, sin personajes no hay ruleta', () => {
  assert.deepEqual(enabledModes({ infantil: [], adulto: [{ slug: 'estudio' }] }), ['adulto'])
  assert.equal(initialPhotoStep([]), 'camera')
  assert.equal(initialPhotoStep([{ name: 'Estrella' }]), 'roulette')
  assert.equal(filterFor('bn'), 'grayscale(1) contrast(1.08)')
  assert.equal(filterFor(undefined), 'none')
})
test('franja mantiene recuerdo, organizador y número del servidor', () => {
  assert.deepEqual(footerLines({ recuerdo: 'Un día especial', organizador_ig: '@feria' }, { etiqueta: 'F-027' }), ['Un día especial', '@feria', 'F-027'])
})
test('reserva y subida usan el contrato real, sin enviar el token en URLs', async () => {
  const calls = []
  const fetcher = async (url, init) => {
    calls.push([url, JSON.parse(init.body)])
    return { ok: true, json: async () => calls.length === 1 ? { ok: true, numero: 27, etiqueta: 'F-027', reserva: 'a'.repeat(32) } : { ok: true, url: 'https://ejemplo.test/foto' } }
  }
  const held = await reservation('./', { slug: 'feria', modo: 'adulto' }, 'estudio', 'Ana', fetcher)
  await upload('./', 'feria', 'Ana', 'data:image/jpeg;base64,foto', held, fetcher)
  assert.deepEqual(calls[0], ['./feria-api.php', { accion: 'numero', f: 'feria', modo: 'adulto', tema: 'estudio', nombre: 'Ana' }])
  assert.equal(calls[1][1].feria_reserva, 'a'.repeat(32))
  assert.equal(calls[1][1].party, 'feria')
  assert.equal(calls[1][0], './upload.php')
})
test('no acepta reserva incompleta, error HTTP ni URL de descarga ejecutable', async () => {
  const response = (data, ok = true) => async () => ({ ok, json: async () => data })
  await assert.rejects(reservation('./', {}, '', '', response({ ok: true, numero: 3 })))
  await assert.rejects(reservation('./', {}, '', '', response({ ok: false, error: 'rate_limit' }, false)))
  await assert.rejects(upload('./', '', '', '', {}, response({ ok: true, url: 'javascript:alert(1)' })))
})
test('inactividad: reinicia al tocar, dispara una vez y cancela al salir', () => {
  let id = 0; const tasks = new Map(); const calls = []
  const clock = { setTimeout(fn, ms) { tasks.set(++id, { fn, ms }); return id }, clearTimeout(key) { tasks.delete(key) } }
  const idle = armIdle(() => calls.push('idle'), 45000, clock)
  assert.equal(tasks.get(id).ms, 45000)
  idle.touch(); assert.equal(tasks.size, 1)
  tasks.get(id).fn(); assert.deepEqual(calls, ['idle'])
  idle.stop(); assert.equal(tasks.size, 0)
  const qr = armIdle(() => {}, 60000, clock)
  assert.equal(tasks.get(id).ms, 60000)
  qr.stop(); assert.equal(tasks.size, 0)
})
