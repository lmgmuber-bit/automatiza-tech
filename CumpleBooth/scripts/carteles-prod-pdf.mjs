// Genera el PDF imprimible de los carteles de una fiesta REAL, con los enlaces de PROD.
//
// Usa la misma pantalla que el admin (así el resultado es idéntico a imprimir desde ahí),
// pero intercepta la respuesta de la API y le pone los enlaces de producción. Sirve para
// entregar el PDF sin emitir otro token de aportes: el del Álbum ya está vivo y volver a
// generarlo lo revocaría, dejando muerto el enlace ya compartido.
//
// Uso: node scripts/carteles-prod-pdf.mjs <slug-local> <tamaño> <estilo> <salida.pdf> <json-carteles>
import puppeteer from 'puppeteer-core'

const CHROME = String.raw`C:\Program Files\Google\Chrome\Application\chrome.exe`
const BASE = 'http://localhost/cc-dist'
const [slug, tamano, estilo, salida, cartelesJson, nombreFiesta] = process.argv.slice(2)
const CARTELES = JSON.parse(cartelesJson)

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new' })
const pag = await nav.newPage()
await pag.setViewport({ width: 1400, height: 1200, deviceScaleFactor: 2 })

// La sesión de admin primero: la API la exige y de ahí salen las cookies que necesita la
// petición espejo que se hace más abajo.
await pag.goto(`${BASE}/admin/login-prueba.php`, { waitUntil: 'networkidle0' })
const cookies = (await pag.cookies()).map((c) => `${c.name}=${c.value}`).join('; ')

await pag.setRequestInterception(true)
pag.on('request', async (req) => {
  if (!req.url().includes('carteles-api.php')) { req.continue(); return }
  try {
    // Se pide la respuesta real para heredar los colores y el banner de la temática, y solo
    // se reemplaza la lista de carteles por la de producción.
    const resp = await fetch(req.url(), { headers: { cookie: cookies } })
    const datos = await resp.json()
    datos.carteles = CARTELES
    // La fiesta local solo aporta los colores y el banner de la tematica; el nombre que se
    // imprime es el de la fiesta real.
    if (nombreFiesta) { datos.fiesta.nombre = nombreFiesta }
    req.respond({ status: 200, contentType: 'application/json; charset=utf-8', body: JSON.stringify(datos) })
  } catch (e) {
    req.abort()
  }
})

await pag.goto(`${BASE}/carteles.html?p=${slug}`, { waitUntil: 'networkidle0' })
await pag.waitForSelector('.cartel__qr img', { timeout: 15000 })
const selects = await pag.$$('.panel__campos select')
await selects[0].select(tamano)
await selects[1].select(estilo)
await new Promise((r) => setTimeout(r, 800))
await pag.pdf({ path: salida, preferCSSPageSize: true, printBackground: true })
const hojas = await pag.$$eval('.cartel', (els) => els.map((e) => e.dataset.cartel))
console.log('ok', salida, '->', hojas.join(', '))
await nav.close()
