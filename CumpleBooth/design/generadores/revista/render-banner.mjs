// Tarjetas del selector de las temáticas con diseño encima (public/themes/<tema>/fondo-banner.jpg). 04-10-2026.
// Cada una es una muestra SIN persona dibujada con el mismo código del kiosco (src/feria/portada.js), no una imagen aparte
// que pueda quedar distinta de la foto real:
//   adulto-revista     alfombra roja, título CLICK, titulares y "ERES TÚ"; sin fecha, para que la tarjeta no envejezca.
//   adulto-anio-nuevo  la bahía con el año que se celebra y el saludo. Por defecto, el año del próximo 31 de diciembre:
//                      cada temporada hay que volver a correrlo (o pasar la fecha).
//   adulto-empresa     el muro de prensa con "TU LOGO AQUÍ" repetido.
// Uso: node design/generadores/revista/render-banner.mjs [tema] [AAAA-MM-DD]   (requiere Chrome; CHROME_PATH para otra ruta)
import http from 'node:http'
import { readFileSync, writeFileSync, existsSync } from 'node:fs'
import { resolve, join, extname } from 'node:path'
import puppeteer from 'puppeteer-core'

const root = resolve(import.meta.dirname, '../../..')
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'
const tema = process.argv[2] || 'adulto-revista'
const fecha = process.argv[3] || `${new Date().getFullYear()}-12-31`
const MUESTRAS = ['adulto-revista', 'adulto-anio-nuevo', 'adulto-empresa']
if (!MUESTRAS.includes(tema)) { console.error('tema sin muestra: ' + tema + ' (hay ' + MUESTRAS.join(', ') + ')'); process.exit(1) }
const destino = join(root, 'public/themes', tema, 'fondo-banner.jpg')
const mime = { '.js': 'text/javascript', '.jpg': 'image/jpeg', '.png': 'image/png', '.woff2': 'font/woff2', '.html': 'text/html' }
const pagina = `<!doctype html><meta charset="utf-8"><canvas id="c" width="1080" height="1920"></canvas>
<script type="module">
import { prepararCapas } from '/src/feria/portada.js'
import { textosPortada } from '/src/feria/revista.js'
import { textosAnioNuevo } from '/src/feria/anioNuevo.js'
const MUESTRA = {
  'adulto-revista': ['revista-alfombra.jpg', { diseno: 'revista', textos: { ...textosPortada({ titulo: 'CLICK' }), fecha: '' }, estilo: { tinta: '#FFFFFF', acento: '#C1121F' } }],
  'adulto-anio-nuevo': ['fondo-escena.jpg', { diseno: 'anioNuevo', textos: textosAnioNuevo({ fecha: ${JSON.stringify(fecha)} }) }],
  'adulto-empresa': ['fondo-escena.jpg', { diseno: 'muroLogos', textos: { marca: 'Tu logo aquí' }, logo: '' }],
}
window.listo = (async () => {
  const [escena, portada] = MUESTRA[${JSON.stringify(tema)}]
  const capas = await prepararCapas(portada, '/')
  const fondo = new Image(); fondo.src = '/themes/${tema}/' + escena; await fondo.decode()
  const canvas = document.getElementById('c'); const ctx = canvas.getContext('2d')
  ctx.drawImage(fondo, 0, 0, 1080, 1920)
  capas.antes?.(ctx, 1080, 1920)
  capas.despues?.(ctx, 1080, 1920)
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
