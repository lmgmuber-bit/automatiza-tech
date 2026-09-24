/**
 * qa-album-visual.mjs — QA visual real de la revista del Álbum Recuerdo en
 * Chrome de verdad (puppeteer-core sobre el Chrome instalado).
 *
 * Cubre lo que pedía la Fase D del plan y el pulido responsive de 2026-08-12:
 * desktop / tablet / teléfono en portrait Y landscape, sin WebGL, con
 * prefers-reduced-motion, álbum de 100+ fotos, consola limpia, aviso de
 * "gira tu celular" solo donde corresponde, y el cartel QR en pantalla chica.
 *
 * Las capturas quedan en qa-output/album-<fecha>/ (carpeta gitignored).
 *
 * Uso:  node scripts/qa-album-visual.mjs [filtro-de-escenario]
 */
import puppeteer from 'puppeteer-core'
import { mkdirSync, writeFileSync } from 'node:fs'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const BASE = 'http://localhost/automatiza-tech/CumpleBooth/dist/'
// Álbum demo sembrado con scripts/seed-demo-album.php (104 fotos + 1 video).
// Los tokens se revocan al re-sembrar y NO se versionan: pasarlos por entorno.
const VIEW_TOKEN = process.env.CB_QA_VIEW_TOKEN
const INTAKE_TOKEN = process.env.CB_QA_INTAKE_TOKEN
if (!VIEW_TOKEN || !INTAKE_TOKEN) {
  console.error('Faltan CB_QA_VIEW_TOKEN y/o CB_QA_INTAKE_TOKEN. Los entrega el admin al re-sembrar; no se versionan.')
  process.exit(1)
}
const ALBUM_URL = `${BASE}album.html?t=${VIEW_TOKEN}`
const CARTEL_URL = `${BASE}cartel-qr.html?t=${INTAKE_TOKEN}`

const OUT = `qa-output/album-2026-08-12`
mkdirSync(OUT, { recursive: true })

const esperar = (ms) => new Promise((r) => setTimeout(r, ms))
const solo = process.argv[2] || null
const resultados = []

function escucharConsola(page, errores) {
  page.on('pageerror', (e) => errores.push(`pageerror: ${e.message}`))
  page.on('console', (m) => {
    if (m.type() === 'error') errores.push(`console.error: ${m.text()}`)
  })
  page.on('requestfailed', (r) => {
    const fallo = r.failure()?.errorText || ''
    // Las peticiones abortadas al navegar rápido (carga perezosa) no son error.
    if (!/ERR_ABORTED/i.test(fallo)) errores.push(`requestfailed: ${r.url()} ${fallo}`)
  })
}

async function medirLayout(page) {
  return page.evaluate(() => {
    const vp = { w: window.innerWidth, h: window.innerHeight }
    const doc = document.documentElement
    const libro = document.querySelector('.flipbook')?.getBoundingClientRect() || null
    const controles = document.querySelector('.flipbook-controls')?.getBoundingClientRect() || null
    return {
      viewport: vp,
      overflowX: doc.scrollWidth > vp.w + 1,
      libro: libro && {
        cabeAncho: libro.width <= vp.w + 1,
        cabeAlto: libro.top >= -1 && libro.bottom <= vp.h + 1,
        w: Math.round(libro.width), h: Math.round(libro.height),
      },
      controles: controles && {
        dentro: controles.left >= -1 && controles.right <= vp.w + 1 && controles.bottom <= vp.h + 60,
        bottom: Math.round(controles.bottom),
      },
      paginasMontadas: document.querySelectorAll('.flip-page').length,
      modoUnaPagina: Boolean(document.querySelector('.flipbook-stage.is-single')),
      avisoGiro: Boolean(document.querySelector('.rotate-hint')),
      indicador: document.querySelector('.flip-indicator')?.textContent?.trim() || null,
    }
  })
}

async function pasarPagina(page, veces = 1) {
  for (let i = 0; i < veces; i++) {
    await page.keyboard.press('ArrowRight')
    await esperar(1150) // FLIP_MS 820 + colchón
  }
}

async function capturar(page, nombre) {
  await page.screenshot({ path: `${OUT}/${nombre}.png`, type: 'png' })
  console.log(`    ✓ ${nombre}.png`)
}

function registrar(nombre, ok, detalle, errores) {
  resultados.push({ nombre, ok, detalle, errores: [...errores] })
  console.log(`  ${ok ? 'OK ' : 'FALLA'} ${nombre} — ${detalle}`)
  if (errores.length) console.log(`    consola: ${errores.join(' | ')}`)
}

/** Abre el álbum y espera a que la revista esté lista. Devuelve métricas base. */
async function abrirAlbum(page) {
  await page.goto(ALBUM_URL, { waitUntil: 'networkidle2', timeout: 45000 })
  await page.waitForSelector('.flipbook, .album-scroll', { visible: true, timeout: 30000 })
  await esperar(1400) // portada pintada + primeras imágenes
}

const ESCENARIOS = [
  {
    nombre: 'desktop-1440x900',
    viewport: { width: 1440, height: 900, deviceScaleFactor: 1 },
    async correr(page) {
      await abrirAlbum(page)
      const m0 = await medirLayout(page)
      await capturar(page, '01-desktop-portada')
      await pasarPagina(page, 1)
      const m1 = await medirLayout(page)
      await capturar(page, '02-desktop-pliego-interior')
      await pasarPagina(page, 3)
      await capturar(page, '03-desktop-mosaicos')
      const m2 = await medirLayout(page)
      const ok = !m0.overflowX && !m0.modoUnaPagina && !m0.avisoGiro
        && m0.libro?.cabeAncho && m0.libro?.cabeAlto
        && m1.indicador !== m0.indicador && m2.paginasMontadas <= 16
      return [ok, `pliego=${m0.libro?.w}x${m0.libro?.h} indicador ${m0.indicador}→${m1.indicador} montadas=${m2.paginasMontadas}`]
    },
  },
  {
    nombre: 'tablet-portrait-768x1024',
    viewport: { width: 768, height: 1024, deviceScaleFactor: 2, isMobile: true, hasTouch: true },
    async correr(page) {
      await abrirAlbum(page)
      const m = await medirLayout(page)
      await capturar(page, '04-tablet-portrait')
      const ok = !m.overflowX && m.modoUnaPagina && m.libro?.cabeAncho && m.libro?.cabeAlto && !m.avisoGiro
      return [ok, `una página=${m.modoUnaPagina} libro=${m.libro?.w}x${m.libro?.h}`]
    },
  },
  {
    nombre: 'tablet-landscape-1024x768',
    viewport: { width: 1024, height: 768, deviceScaleFactor: 2, isMobile: true, hasTouch: true },
    async correr(page) {
      await abrirAlbum(page)
      const m = await medirLayout(page)
      await capturar(page, '05-tablet-landscape')
      const ok = !m.overflowX && !m.modoUnaPagina && !m.avisoGiro && m.libro?.cabeAncho && m.libro?.cabeAlto
      return [ok, `pliego=${m.libro?.w}x${m.libro?.h}`]
    },
  },
  {
    nombre: 'telefono-portrait-390x844',
    viewport: { width: 390, height: 844, deviceScaleFactor: 3, isMobile: true, hasTouch: true },
    async correr(page) {
      await abrirAlbum(page)
      const m = await medirLayout(page)
      await capturar(page, '06-telefono-portrait-aviso')
      // El aviso no bloquea: la página debe poder pasarse igual.
      await pasarPagina(page, 1)
      const m1 = await medirLayout(page)
      // Cierre manual del aviso.
      await page.click('.rotate-hint__close').catch(() => {})
      await esperar(400)
      const m2 = await medirLayout(page)
      await capturar(page, '07-telefono-portrait-sin-aviso')
      const ok = m.avisoGiro && m.modoUnaPagina && !m.overflowX
        && m1.indicador !== m.indicador /* pasó de página con el aviso puesto */
        && !m2.avisoGiro /* se cerró */
        && m.libro?.cabeAncho && m.libro?.cabeAlto
      return [ok, `aviso=${m.avisoGiro} unaPágina=${m.modoUnaPagina} pasóPágina=${m1.indicador !== m.indicador} cerró=${!m2.avisoGiro}`]
    },
  },
  {
    nombre: 'telefono-landscape-844x390',
    viewport: { width: 844, height: 390, deviceScaleFactor: 3, isMobile: true, hasTouch: true },
    async correr(page) {
      await abrirAlbum(page)
      const m = await medirLayout(page)
      await capturar(page, '08-telefono-landscape')
      const ok = !m.avisoGiro && !m.overflowX && m.libro?.cabeAncho && m.libro?.cabeAlto && m.controles?.dentro
      return [ok, `avisoOculto=${!m.avisoGiro} pliego=${m.libro?.w}x${m.libro?.h} dosPáginas=${!m.modoUnaPagina}`]
    },
  },
  {
    nombre: 'telefono-chico-360x640',
    viewport: { width: 360, height: 640, deviceScaleFactor: 2, isMobile: true, hasTouch: true },
    async correr(page) {
      await abrirAlbum(page)
      const m = await medirLayout(page)
      await capturar(page, '09-telefono-chico-portrait')
      const ok = m.avisoGiro && m.modoUnaPagina && !m.overflowX && m.libro?.cabeAncho && m.libro?.cabeAlto
      return [ok, `aviso=${m.avisoGiro} libro=${m.libro?.w}x${m.libro?.h}`]
    },
  },
  {
    nombre: 'telefono-landscape-bajo-740x360',
    viewport: { width: 740, height: 360, deviceScaleFactor: 2, isMobile: true, hasTouch: true },
    async correr(page) {
      await abrirAlbum(page)
      const m = await medirLayout(page)
      await capturar(page, '10-telefono-landscape-bajo')
      // 740px < 820: sigue en una página, pero debe caber en los 360px de alto.
      const ok = !m.avisoGiro && !m.overflowX && m.libro?.cabeAncho && m.libro?.cabeAlto && m.controles?.dentro
      return [ok, `libro=${m.libro?.w}x${m.libro?.h} unaPágina=${m.modoUnaPagina}`]
    },
  },
  {
    nombre: 'escritorio-angosto-500x800-sin-aviso',
    viewport: { width: 500, height: 800, deviceScaleFactor: 1 },
    async correr(page) {
      await abrirAlbum(page)
      const m = await medirLayout(page)
      await capturar(page, '11-escritorio-angosto')
      // Ventana angosta de PC: portrait y < 640px, pero puntero fino → sin aviso.
      const ok = !m.avisoGiro && m.modoUnaPagina && !m.overflowX
      return [ok, `aviso=${m.avisoGiro} (debe ser false con puntero fino)`]
    },
  },
  {
    nombre: 'reduced-motion-lista',
    viewport: { width: 1280, height: 800, deviceScaleFactor: 1 },
    reducedMotion: true,
    async correr(page) {
      await abrirAlbum(page)
      await esperar(800)
      const info = await page.evaluate(() => ({
        lista: Boolean(document.querySelector('.album-scroll')),
        flipbook: Boolean(document.querySelector('.flipbook')),
        paginas: document.querySelectorAll('.album-scroll__page').length,
      }))
      await page.evaluate(() => window.scrollTo(0, 1200))
      await esperar(600)
      await capturar(page, '12-reduced-motion-lista')
      const ok = info.lista && !info.flipbook && info.paginas > 30
      return [ok, `lista=${info.lista} flipbook=${info.flipbook} páginasEnColumna=${info.paginas}`]
    },
  },
  {
    nombre: 'sin-webgl',
    viewport: { width: 1440, height: 900, deviceScaleFactor: 1 },
    args: ['--disable-webgl', '--disable-webgl2'],
    async correr(page) {
      await abrirAlbum(page)
      const webgl = await page.evaluate(() => {
        const c = document.createElement('canvas')
        return Boolean(c.getContext('webgl') || c.getContext('experimental-webgl'))
      })
      await pasarPagina(page, 2)
      const m = await medirLayout(page)
      await capturar(page, '13-sin-webgl')
      const ok = !webgl && Boolean(m.libro) && m.libro?.cabeAlto
      return [ok, `webglDisponible=${webgl} (debe ser false) revistaFunciona=${Boolean(m.libro)}`]
    },
  },
  {
    nombre: 'album-104-fotos-fondo',
    viewport: { width: 1440, height: 900, deviceScaleFactor: 1 },
    async correr(page) {
      await abrirAlbum(page)
      const m0 = await medirLayout(page)
      const total = Number((m0.indicador || '').split('/').pop()?.trim() || 0)
      // Avanza hasta que la página de video quede montada y visible (su <video>
      // solo recibe src cuando la página está a la vista, por diseño).
      let m = m0
      let videoVisto = false
      for (let i = 0; i < 40 && !videoVisto; i++) {
        videoVisto = await page.evaluate(() => {
          const v = document.querySelector('video.mag__video')
          return Boolean(v && (v.currentSrc || v.src))
        })
        if (videoVisto) break
        await pasarPagina(page, 1)
        m = await medirLayout(page)
      }
      // Deja que el video cargue metadata y captura la página. El elemento usa
      // preload="none": no descarga nada hasta que se le pide (así lo vería un
      // invitado al apretar play). Se fuerza la carga como haría ese gesto.
      const video = await page.evaluate(async () => {
        const v = document.querySelector('video.mag__video')
        if (!v || !(v.currentSrc || v.src)) return { hay: false }
        v.preload = 'metadata'
        v.load()
        if (v.readyState < 1) {
          await new Promise((res) => {
            v.addEventListener('loadedmetadata', res, { once: true })
            v.addEventListener('error', () => res(), { once: true })
            setTimeout(res, 10000)
          })
        }
        if (v.readyState < 1) return { hay: true, src: true, w: 0, h: 0, error: 'sin metadata' }
        // Y que decodifique de verdad: reproducir un instante, en silencio.
        let reprodujo = false
        try {
          v.muted = true
          await v.play()
          await new Promise((res) => setTimeout(res, 700))
          reprodujo = v.currentTime > 0.05
          v.pause()
        } catch (e) {
          reprodujo = false
        }
        return { hay: true, src: true, w: v.videoWidth, h: v.videoHeight, reprodujo }
      })
      await capturar(page, '14-album-pagina-video')
      // Un paso atrás: el pliego anterior (dúo/nota) sí tiene fotos a la
      // vista. Ahí se verifica que las imágenes de un álbum de 104 fotos
      // cargan de verdad al llegar al fondo (carga perezosa).
      await page.keyboard.press('ArrowLeft')
      await esperar(1400)
      const imgs = await page.evaluate(() =>
        [...document.querySelectorAll('.flip-page img.mag__photo')]
          .filter((i) => i.getBoundingClientRect().width > 0)
          .map((i) => i.complete && i.naturalWidth > 0))
      await page.keyboard.press('ArrowRight')
      await esperar(1400)
      // Sigue hasta el cierre: la última página y el conteo de recuerdos.
      let guard = 0
      while (guard++ < 40) {
        const actual = Number((m.indicador || '0 /').split('/')[0].trim())
        if (actual >= total) break
        await pasarPagina(page, 1)
        m = await medirLayout(page)
      }
      await capturar(page, '15-album-fondo-cierre')
      const ok = total >= 36 && m.paginasMontadas <= 16 && video.hay && video.w > 0 && video.reprodujo
        && imgs.length > 0 && imgs.every(Boolean)
      return [ok, `páginas=${total} montadas=${m.paginasMontadas} video=${JSON.stringify(video)} imgs=${imgs.length} cargadas=${imgs.every(Boolean)}`]
    },
  },
  {
    nombre: 'cartel-movil-390x844',
    url: CARTEL_URL,
    viewport: { width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true },
    async correr(page) {
      await page.goto(CARTEL_URL, { waitUntil: 'networkidle2', timeout: 45000 })
      await page.waitForSelector('.sign', { visible: true, timeout: 30000 })
      await esperar(1200)
      await capturar(page, '16-cartel-movil')
      const info = await page.evaluate(() => {
        const doc = document.documentElement
        const qr = document.querySelector('.sign__qr')?.getBoundingClientRect()
        return {
          overflowX: doc.scrollWidth > window.innerWidth + 1,
          qrDentro: qr ? qr.left >= -1 && qr.right <= window.innerWidth + 1 : false,
          qrAncho: qr ? Math.round(qr.width) : 0,
        }
      })
      const ok = !info.overflowX && info.qrDentro
      return [ok, `overflowX=${info.overflowX} qr=${info.qrAncho}px dentro=${info.qrDentro}`]
    },
  },
  {
    nombre: 'cartel-desktop-1280x800',
    url: CARTEL_URL,
    viewport: { width: 1280, height: 800, deviceScaleFactor: 1 },
    async correr(page) {
      await page.goto(CARTEL_URL, { waitUntil: 'networkidle2', timeout: 45000 })
      await page.waitForSelector('.sign', { visible: true, timeout: 30000 })
      await esperar(1200)
      await capturar(page, '17-cartel-desktop')
      const info = await page.evaluate(() => ({
        overflowX: document.documentElement.scrollWidth > window.innerWidth + 1,
        hoja: document.querySelector('.sign')?.getBoundingClientRect().width || 0,
      }))
      return [!info.overflowX && info.hoja > 600, `hoja=${Math.round(info.hoja)}px overflowX=${info.overflowX}`]
    },
  },
]

async function main() {
  const elegidos = ESCENARIOS.filter((e) => !solo || e.nombre.includes(solo))
  if (!elegidos.length) {
    console.error(`Ningún escenario calza con "${solo}".`)
    process.exit(1)
  }

  for (const esc of elegidos) {
    console.log(`\n=== ${esc.nombre} ===`)
    const browser = await puppeteer.launch({
      executablePath: CHROME,
      headless: 'new',
      args: ['--no-first-run', '--mute-audio', '--autoplay-policy=no-user-gesture-required', ...(esc.args || [])],
    })
    const errores = []
    try {
      const page = await browser.newPage()
      page.setDefaultTimeout(40000)
      escucharConsola(page, errores)
      await page.setViewport(esc.viewport)
      if (esc.reducedMotion) {
        await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }])
      }
      const [ok, detalle] = await esc.correr(page)
      registrar(esc.nombre, ok && errores.length === 0, detalle, errores)
    } catch (e) {
      registrar(esc.nombre, false, `excepción: ${e.message}`, errores)
    } finally {
      await browser.close()
    }
  }

  const fallas = resultados.filter((r) => !r.ok)
  console.log(`\n── Resumen: ${resultados.length - fallas.length}/${resultados.length} escenarios OK ──`)
  for (const r of resultados) {
    console.log(`  ${r.ok ? '✓' : '✗'} ${r.nombre}`)
  }
  writeFileSync(`${OUT}/resultados.json`, JSON.stringify({ cuando: new Date().toISOString(), resultados }, null, 2))
  if (fallas.length) process.exit(1)
  console.log(`\nEvidencia en ${OUT}/`)
}

main().catch((e) => { console.error('Fatal:', e); process.exit(1) })
