// Rasteriza un PDF a PNG para poder revisarlo a ojo (no hay poppler en esta máquina).
// Usa pdf.js dentro de Chrome, que es fiable y no depende del visor interno.
// Uso: node scripts/pdf-a-png.mjs <archivo.pdf> <salida.png> [pagina]
import puppeteer from 'puppeteer-core'
import { readFileSync } from 'node:fs'

const CHROME = String.raw`C:\Program Files\Google\Chrome\Application\chrome.exe`
const [entrada, salida, paginaTxt] = process.argv.slice(2)
const pagina = Number(paginaTxt || 1)
const b64 = readFileSync(entrada).toString('base64')

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new' })
const pag = await nav.newPage()
await pag.setViewport({ width: 1000, height: 1400, deviceScaleFactor: 2 })
await pag.setContent('<canvas id="c"></canvas>', { waitUntil: 'domcontentloaded' })
await pag.addScriptTag({ url: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js' })
const medidas = await pag.evaluate(async (datos, n) => {
  const pdfjs = window.pdfjsLib
  pdfjs.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js'
  const bin = Uint8Array.from(atob(datos), (c) => c.charCodeAt(0))
  const doc = await pdfjs.getDocument({ data: bin }).promise
  const p = await doc.getPage(n)
  const vista = p.getViewport({ scale: 2 })
  const c = document.getElementById('c')
  c.width = vista.width
  c.height = vista.height
  await p.render({ canvasContext: c.getContext('2d'), viewport: vista }).promise
  return { paginas: doc.numPages, w: vista.width, h: vista.height }
}, b64, pagina)
await pag.$eval('#c', (c) => { c.style.width = c.width / 2 + 'px' })
await (await pag.$('#c')).screenshot({ path: salida })
console.log('ok', salida, JSON.stringify(medidas))
await nav.close()
