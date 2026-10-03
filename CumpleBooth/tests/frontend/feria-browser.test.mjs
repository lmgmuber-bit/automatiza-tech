import test from 'node:test'
import assert from 'node:assert/strict'
import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { createServer } from 'vite'
import puppeteer from 'puppeteer-core'

const chrome = process.env.CHROME_PATH || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/google-chrome', '/usr/bin/chromium'].find(existsSync)
const root = resolve(import.meta.dirname, '../..')
const feria = { slug: 'feria-prueba', nombre: 'Encuentro de Primavera', recuerdo: 'Un recuerdo del Encuentro de Primavera', organizador: 'Equipo local', organizador_ig: '@encuentro', fecha: '2026-09-26', fecha_texto: '26 sep 2026' }
const theme = { slug: 'estudio', nombre: 'Estudio', personajes: [], images: { sala: 'themes/baby-nube/fondo-sala.jpg', banner: 'themes/baby-nube/fondo-banner.jpg' }, colors: { accent: '#6d308b' }, videos: {}, diploma: 'Estrella del encuentro' }
const party = { nombre: 'Celebración', invitados: [{ name: 'Ana', g: 'f' }], musica: false, fecha: '2026-09-26', frameBox: { x: .12, y: .1, w: .76, h: .4275 } }

test('navegador: selector, autorización, niños/adultos, QR, fallos y fiesta normal', { timeout: 120000 }, async (t) => {
  assert.ok(chrome, 'Instala Chrome/Chromium o indica CHROME_PATH para las pruebas de navegador.')
  const server = await createServer({ root, server: { host: '127.0.0.1', port: 0, strictPort: false, proxy: {} }, logLevel: 'error' })
  await server.listen()
  const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream', '--autoplay-policy=no-user-gesture-required'] })
  t.after(async () => { await browser.close(); await server.close() })
  const page = await browser.newPage()
  await page.setViewport({ width: 1200, height: 2000, deviceScaleFactor: 1 })
  // Fuente reproducible: no compite por un dispositivo de cámara con la integración PHP.
  await page.evaluateOnNewDocument(() => {
    navigator.mediaDevices.getUserMedia = async () => {
      const canvas = document.createElement('canvas'); canvas.width = 640; canvas.height = 480
      const ctx = canvas.getContext('2d')
      const paint = () => { ctx.fillStyle = '#d53181'; ctx.fillRect(0, 0, 640, 480); ctx.fillStyle = '#f5cb50'; ctx.fillRect(180, 70, 280, 340) }
      paint()
      const stream = canvas.captureStream(30), timer = setInterval(paint, 33)
      const track = stream.getVideoTracks()[0], stop = track.stop.bind(track)
      track.stop = () => { clearInterval(timer); stop() }
      return stream
    }
  })
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }])
  const origin = 'http://127.0.0.1:' + server.httpServer.address().port
  let uploads = 0, reserves = 0, failReserve = false, failUpload = false
  const errors = []
  page.on('pageerror', (error) => errors.push(error.message))
  await page.setRequestInterception(true)
  page.on('request', (request) => {
    const url = new URL(request.url())
    const json = (body, status = 200) => request.respond({ status, contentType: 'application/json', body: JSON.stringify(body) })
    if (url.pathname === '/feria-api.php') {
      if (request.method() === 'POST') {
        reserves++
        return failReserve ? json({ ok: false }, 503) : json({ ok: true, numero: 27, etiqueta: 'F-027', reserva: 'a'.repeat(32) })
      }
      return json({ ok: true, feria, video_espera: '', mundos: {
        infantil: [{ slug: 'estudio', nombre: 'Mundo de estrellas', imagen: theme.images.banner, personajes: true }],
        adulto: [{ slug: 'estudio', nombre: 'Estudio', imagen: theme.images.banner, personajes: false }],
      } })
    }
    if (url.pathname === '/api.php') {
      if (url.searchParams.get('p') === 'normal') return json({ ok: true, party, theme: { ...theme, personajes: [{ name: 'Estrella', emoji: '✦', img: theme.images.banner }] } })
      const child = url.searchParams.get('modo') === 'infantil'
      return json({ ok: true, party, theme: { ...theme, filtro: child ? undefined : 'bn', personajes: child ? [{ name: 'Estrella', emoji: '✦', img: theme.images.banner, game: {} }] : [] }, feria: { ...feria, modo: child ? 'infantil' : 'adulto' } })
    }
    if (url.pathname === '/upload.php') {
      uploads++
      const body = JSON.parse(request.postData())
      assert.equal(body.feria_reserva, 'a'.repeat(32))
      assert.ok(body.image.startsWith('data:image/jpeg;'))
      return failUpload ? json({ ok: false }, 503) : json({ ok: true, url: origin + '/ver.php?t=ejemplo-local' })
    }
    if (/\.(mp4|mp3)(\?|$)/.test(url.href)) return request.respond({ status: 404, body: '' })
    return request.continue()
  })
  const open = async (path) => { await page.goto(origin + path, { waitUntil: 'networkidle0' }) }
  const button = async (label) => {
    const handle = await page.waitForFunction((text) => [...document.querySelectorAll('button')].find((b) => b.textContent.includes(text) && !b.disabled), {}, label)
    await handle.asElement().click()
  }
  const step = (name) => page.waitForSelector('[data-step="' + name + '"]')
  await t.test('fiesta normal: no activa feria por URL, conserva bienvenida y lista de invitados', async () => {
    await open('/?p=normal&modo=adulto&tema=estudio')
    assert.equal(await page.$('.feria-kiosco'), null)
    await page.waitForSelector('.intro')
    const before = await page.$eval('.intro', (node) => node.textContent)
    await open('/?p=normal')
    assert.equal(await page.$eval('.intro', (node) => node.textContent), before)
    await page.click('.intro .cta')
    await page.waitForSelector('.invitados-list')
    assert.match(await page.$eval('.invitados-list', (node) => node.textContent), /Ana/)
    assert.equal(await page.$('.feria-name'), null)
  })
  await t.test('selector exige autorización y reinicia a los 45 s', async () => {
    await page.evaluateOnNewDocument(() => {
      const original = window.setTimeout.bind(window)
      window.setTimeout = (fn, delay, ...args) => original(fn, delay === 45000 ? 600 : delay === 60000 ? 900 : delay, ...args)
    })
    await open('/feria.html?f=feria-prueba')
    await button('Toca para empezar')
    await page.waitForSelector('.feria-modes')
    const boxes = await page.$eval('.feria-selector', (root) => ({ headerY: root.querySelector('header').getBoundingClientRect().y, buttonWidth: root.querySelector('.feria-mode').getBoundingClientRect().width }))
    assert.ok(boxes.headerY < 200 && boxes.buttonWidth > 400, 'cabecera arriba y botones amplios en 1200 × 2000')
    await page.waitForSelector('.feria-idle', { timeout: 3000 })
    await button('Toca para empezar')
    await button('Niños')
    await button('Mundo de estrellas')
    assert.equal(await page.$eval('.feria-primary', (node) => node.disabled), true)
    await page.click('input[type=checkbox]')
    await button('Continuar a mi foto')
    await step('name')
    assert.equal(new URL(page.url()).searchParams.get('modo'), 'infantil')
    assert.equal(await page.$('input[type=checkbox]'), null)
  })
  await t.test('niños: ruleta, personaje, foto, QR y diploma; no entra a minijuegos', async () => {
    await button('Saltar')
    await step('roulette')
    await page.waitForSelector('.spinner-winner', { timeout: 500 })
    await button('¡Me gusta!')
    await step('character')
    await button('Continuar')
    await step('camera')
    await button('Tomar mi foto')
    await page.waitForSelector('.feria-result')
    assert.equal(reserves, 1)
    await button('Guardar y ver mi QR')
    await step('qr')
    await button('Ver mi diploma')
    await page.waitForSelector('.feria-diploma img')
    assert.match(await page.$eval('.feria-diploma img', (img) => img.alt), /Encuentro de Primavera · 26 sep 2026/)
    await page.waitForSelector('.feria-idle', { timeout: 5000 })
  })
  await t.test('adultos: cámara directa, filtro BN, retry sin perder foto ni repetir número', async () => {
    await open('/?p=feria-prueba&modo=adulto&tema=estudio')
    await step('name')
    assert.doesNotMatch(await page.$eval('main', (node) => node.textContent), /responsable|diploma|niño/i)
    await button('Saltar')
    await step('camera')
    assert.equal(await page.$eval('video', (node) => node.style.filter), 'grayscale(1) contrast(1.08)')
    failReserve = true
    await button('Tomar mi foto')
    await page.waitForSelector('[role=alert]')
    assert.equal(await page.$('.feria-qr'), null)
    failReserve = false
    await button('Reintentar')
    await page.waitForSelector('.feria-result')
    const count = reserves
    const gray = await page.$eval('.feria-result', (image) => {
      const canvas = document.createElement('canvas'); canvas.width = 60; canvas.height = 100
      const ctx = canvas.getContext('2d'); ctx.drawImage(image, 0, 0, 60, 100)
      const px = ctx.getImageData(0, 0, 60, 100).data
      return Array.from({length:px.length/4}, (_,i)=>Math.max(px[i*4],px[i*4+1],px[i*4+2])-Math.min(px[i*4],px[i*4+1],px[i*4+2])).every((delta)=>delta<=3)
    })
    assert.equal(gray, true, 'la foto completa y su franja son blanco y negro')
    failUpload = true
    await button('Guardar y ver mi QR')
    await page.waitForSelector('[role=alert]')
    assert.ok(await page.$('.feria-result'))
    failUpload = false
    await button('Guardar y ver mi QR')
    await step('qr')
    assert.equal(reserves, count)
    assert.equal(await page.$('.feria-diploma'), null)
    assert.doesNotMatch(await page.$eval('main', (node) => node.textContent), /diploma/i)
    await page.waitForSelector('.feria-idle', { timeout: 5000 })
  })
  await t.test('cámara denegada: informa y permite reintentar sin fingir una captura', async () => {
    await page.evaluateOnNewDocument(() => { navigator.mediaDevices.getUserMedia = async () => { throw new DOMException('Prueba', 'NotAllowedError') } })
    await open('/?p=feria-prueba&modo=adulto&tema=estudio')
    await button('Saltar')
    await page.waitForSelector('[role=alert]')
    assert.equal(await page.$eval('.feria-camera .feria-primary', (node) => node.disabled), true)
    assert.match(await page.$eval('.feria-camera', (node) => node.textContent), /Reintentar cámara/)
  })
  assert.ok(uploads >= 3)
  assert.deepEqual(errors, [])
})
