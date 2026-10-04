// Tarjeta del selector de la Portada de Revista (public/themes/adulto-revista/fondo-banner.jpg). 04-10-2026.
// Es una portada de muestra SIN persona, dibujada con el mismo src/feria/revista.js que usa el kiosco (no una imagen
// aparte que pueda quedar distinta): la escena de la alfombra roja, el título CLICK y los titulares, con "ERES TÚ" en el
// lugar del nombre. Sin fecha a propósito: la tarjeta no puede envejecer.
// Uso: node design/generadores/revista/render-banner.mjs   (requiere Chrome; CHROME_PATH para otra ruta)
import http from 'node:http'
import { readFileSync, writeFileSync, existsSync } from 'node:fs'
import { resolve, join, extname } from 'node:path'
import puppeteer from 'puppeteer-core'

const root = resolve(import.meta.dirname, '../../..')
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'
const destino = join(root, 'public/themes/adulto-revista/fondo-banner.jpg')
const mime = { '.js': 'text/javascript', '.jpg': 'image/jpeg', '.woff2': 'font/woff2', '.html': 'text/html' }
const pagina = `<!doctype html><meta charset="utf-8"><canvas id="c" width="1080" height="1920"></canvas>
<script type="module">
import { textosPortada, capasPortada, cargarFuentesRevista } from '/src/feria/revista.js'
window.listo = (async () => {
  if (!(await cargarFuentesRevista('/'))) throw new Error('sin fuentes')
  const escena = new Image(); escena.src = '/themes/adulto-revista/revista-alfombra.jpg'; await escena.decode()
  const canvas = document.getElementById('c'); const ctx = canvas.getContext('2d')
  const textos = { ...textosPortada({ titulo: 'CLICK' }), fecha: '' }
  const capas = capasPortada(textos, { tinta: '#FFFFFF', acento: '#C1121F' })
  ctx.drawImage(escena, 0, 0, 1080, 1920)
  capas.antes(ctx, 1080, 1920)
  capas.despues(ctx, 1080, 1920)
  return canvas.toDataURL('image/jpeg', 0.86)
})()
</script>`

const servidor = http.createServer((req, res) => {
  const ruta = decodeURIComponent(new URL(req.url, 'http://x').pathname)
  if (ruta === '/') { res.setHeader('Content-Type', 'text/html'); res.end(pagina); return }
  const archivo = ruta.startsWith('/src/') ? join(root, ruta) : join(root, 'public', ruta)
  if (!existsSync(archivo)) { res.statusCode = 404; res.end(); return }
  res.setHeader('Content-Type', mime[extname(archivo)] || 'application/octet-stream'); res.end(readFileSync(archivo))
})
await new Promise((ok) => servidor.listen(0, '127.0.0.1', ok))
const navegador = await puppeteer.launch({ executablePath: chrome, headless: true })
try {
  const page = await navegador.newPage()
  await page.goto('http://127.0.0.1:' + servidor.address().port + '/', { waitUntil: 'load' })
  const datos = await page.evaluate(() => window.listo)
  writeFileSync(destino, Buffer.from(datos.split(',')[1], 'base64'))
  console.log('ok', destino)
} finally {
  await navegador.close()
  servidor.close()
}
