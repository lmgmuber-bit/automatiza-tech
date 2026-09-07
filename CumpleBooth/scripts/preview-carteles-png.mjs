// Captura cada cartel como PNG para revisarlo a ojo antes de imprimir.
// Uso: node scripts/preview-carteles-png.mjs <slug> <tamaño> <estilo> <prefijo> [--album]
// Con --album pide primero el QR de aportes del Álbum (emite un token nuevo).
import puppeteer from 'puppeteer-core'

const CHROME = String.raw`C:\Program Files\Google\Chrome\Application\chrome.exe`
const BASE = 'http://localhost/cc-dist'
const [slug, tamano, estilo, prefijo, ...resto] = process.argv.slice(2)

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new' })
const pag = await nav.newPage()
await pag.setViewport({ width: 1400, height: 1200, deviceScaleFactor: 1.5 })
await pag.goto(`${BASE}/admin/login-prueba.php`, { waitUntil: 'networkidle0' })
await pag.goto(`${BASE}/carteles.html?p=${slug}`, { waitUntil: 'networkidle0' })
await pag.waitForSelector('.cartel__qr img', { timeout: 15000 })
if (resto.includes('--album')) {
  await pag.click('.panel__album .boton')
  await pag.waitForFunction(() => document.querySelector('[data-cartel="album"]') !== null, { timeout: 15000 })
}
const selects = await pag.$$('.panel__campos select')
await selects[0].select(tamano)
await selects[1].select(estilo)
await new Promise((r) => setTimeout(r, 700))
for (const [i, c] of (await pag.$$('.cartel')).entries()) {
  const id = await c.evaluate((el) => el.dataset.cartel)
  const salida = `${prefijo}-${i + 1}-${id}.png`
  await c.screenshot({ path: salida })
  console.log('ok', salida)
}
await nav.close()
