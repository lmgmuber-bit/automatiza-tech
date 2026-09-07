/**
 * record-album-promo.mjs — graba la revista del Álbum Recuerdo FUNCIONANDO en
 * Chrome real, para el video promocional 9:16 (0 créditos de IA: es la
 * revista misma, grabada con CDP screencast como ya hizo
 * record-marketing-clips.mjs para la ruleta).
 *
 * Álbum usado: el demo sembrado por scripts/seed-demo-album.php (contenido
 * 100% sintético: ninguna cara, ningún menor, ningún personaje).
 * OJO: re-sembrar revoca el token de lectura. Se pasa siempre por
 * CB_QA_VIEW_TOKEN; no se versiona.
 *
 * Clips (quedan en design/album-promo/clips/):
 *   portrait-portada   portada + hojeo en una página (ventana 540x960)
 *   portrait-nota      página de mensaje de un invitado
 *   portrait-video     la página de video reproduciéndose
 *   portrait-cierre    llegada a la página de cierre
 *   landscape-pliego   pliego de dos páginas pasando (ventana 1280x720)
 *   mobile-aviso       celular portrait: aviso de giro + pase a una página
 *
 * Uso:  node scripts/record-album-promo.mjs [solo-este-clip]
 */
import puppeteer from 'puppeteer-core'
import { execSync } from 'node:child_process'
import { mkdirSync, writeFileSync, rmSync } from 'node:fs'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const BASE = 'http://localhost/automatiza-tech/CumpleBooth/dist/'
const VIEW_TOKEN = process.env.CB_QA_VIEW_TOKEN
if (!VIEW_TOKEN) {
  console.error('Falta CB_QA_VIEW_TOKEN. Lo entrega el admin al re-sembrar; no se versiona.')
  process.exit(1)
}
const ALBUM_URL = `${BASE}album.html?t=${VIEW_TOKEN}`
const CLIPS = 'C:/wamp64/www/automatiza-tech/CumpleBooth/design/album-promo/clips'
const TMP = `${process.env.TEMP}/cc-album-promo-frames`
const FLIP_ESPERA = 1150 // FLIP_MS 820 + colchón de pintado

mkdirSync(CLIPS, { recursive: true })
const esperar = (ms) => new Promise((r) => setTimeout(r, ms))
const sh = (cmd) => execSync(cmd, { stdio: ['ignore', 'pipe', 'pipe'], maxBuffer: 1e9 }).toString()

const paginaActual = (page) => page.evaluate(() =>
  Number((document.querySelector('.flip-indicator')?.textContent || '0 /').split('/')[0].trim()))

/** Avanza sin grabar hasta llegar a la página indicada (modo una página). */
async function navegarHasta(page, destino, maxPasos = 44) {
  for (let i = 0; i < maxPasos; i++) {
    const actual = await paginaActual(page)
    if (!actual || actual >= destino) return actual
    await page.keyboard.press('ArrowRight')
    await esperar(950)
  }
  return paginaActual(page)
}

/** Graba `durMs` de screencast mientras corre `accion`, y deja el mp4 listo. */
async function grabarClip(page, nombre, durMs, ancho, alto, accion) {
  const dir = `${TMP}/${nombre}-${Date.now()}`
  mkdirSync(dir, { recursive: true })
  const client = await page.target().createCDPSession()
  // Sin foco de emulación Chrome pinta al mínimo y el clip sale acelerado.
  await page.bringToFront()
  await client.send('Emulation.setFocusEmulationEnabled', { enabled: true })

  const frames = []
  client.on('Page.screencastFrame', async (frame) => {
    frames.push({ t: Date.now(), data: frame.data })
    await client.send('Page.screencastFrameAck', { sessionId: frame.sessionId })
  })
  await client.send('Page.startScreencast', {
    format: 'jpeg', quality: 82, everyNthFrame: 1, maxWidth: ancho, maxHeight: alto,
  })

  // El screencast tarda en arrancar: si la acción parte de inmediato, su
  // "segundo cero" queda dentro de la zona muerta y el clip empieza a media
  // animación. Se espera el PRIMER frame y se deja correr un calentamiento:
  // los primeros frames suelen traer restos de composición de la apertura de
  // la ventana (franjas a medio pintar). Todo eso se descarta y el clip
  // empieza limpio, alineado con la acción.
  for (let i = 0; i < 100 && frames.length === 0; i++) await esperar(60)
  if (frames.length === 0) throw new Error(`${nombre}: el screencast no emitió frames`)
  await esperar(600)
  frames.length = 0

  const inicio = Date.now()
  if (accion) await accion()
  const restante = durMs - (Date.now() - inicio)
  if (restante > 0) await esperar(restante)

  // Cierre asentado: la página quieta NO genera frames nuevos, así que el
  // clip terminaría con el último frame a media vuelta. Dos nudges de estilo
  // (imperceptibles) fuerzan repintados ya asentados: esos quedan al final.
  for (let i = 0; i < 2; i++) {
    await page.evaluate(() => {
      document.body.style.filter = 'brightness(0.999)'
      requestAnimationFrame(() => { document.body.style.filter = '' })
    }).catch(() => {})
    await esperar(280)
  }

  await client.send('Page.stopScreencast')
  client.removeAllListeners('Page.screencastFrame')
  if (frames.length < 5) throw new Error(`${nombre}: solo ${frames.length} frames`)

  // fps de ENTRADA desde el tiempo real (el concat con `duration` no los
  // respetó en su momento); `-r 25` de salida rellena por duplicación.
  const segundos = (frames[frames.length - 1].t - frames[0].t) / 1000 || 1
  const fpsEntrada = Math.max(1, frames.length / segundos)
  frames.forEach((f, i) => writeFileSync(`${dir}/f${String(i).padStart(4, '0')}.jpg`, Buffer.from(f.data, 'base64')))

  const salida = `${CLIPS}/${nombre}.mp4`
  sh(`ffmpeg -y -v error -framerate ${fpsEntrada.toFixed(3)} -i "${dir}/f%04d.jpg" ` +
     `-r 25 -c:v libx264 -preset medium -crf 19 -pix_fmt yuv420p -movflags +faststart "${salida}"`)
  rmSync(dir, { recursive: true, force: true })
  console.log(`    ✓ ${nombre}.mp4 (${frames.length} frames, ${segundos.toFixed(1)}s reales)`)
}

async function abrirAlbum(browser, viewport) {
  const page = await browser.newPage()
  page.setDefaultTimeout(40000)
  page.on('pageerror', (e) => console.log(`  [pageerror] ${e.message}`))
  await page.setViewport(viewport)
  await page.goto(ALBUM_URL, { waitUntil: 'networkidle2', timeout: 45000 })
  await page.waitForSelector('.flipbook', { visible: true, timeout: 30000 })
  await esperar(1600)
  return page
}

const PORTRAIT = { width: 540, height: 960, deviceScaleFactor: 1 }
const LANDSCAPE = { width: 1280, height: 720, deviceScaleFactor: 1 }
const MOBILE = { width: 444, height: 960, deviceScaleFactor: 1, isMobile: true, hasTouch: true }

async function main() {
  const solo = process.argv[2] || null
  const quiero = (nombre) => !solo || nombre.includes(solo)
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: false,
    args: ['--no-first-run', '--mute-audio', '--autoplay-policy=no-user-gesture-required', '--window-position=40,40'],
    defaultViewport: null,
  })

  try {
    // ── Sesión portrait: portada → hojeo → nota → video → cierre ────────────
    if (quiero('portrait')) {
      const page = await abrirAlbum(browser, PORTRAIT)

      if (quiero('portrait-portada')) {
        console.log('■ portrait-portada')
        await grabarClip(page, 'portrait-portada', 8600, PORTRAIT.width, PORTRAIT.height, async () => {
          await esperar(1500) // portada quieta un momento
          for (let i = 0; i < 3; i++) { // tres pases: portada y dos páginas
            await page.keyboard.press('ArrowRight')
            await esperar(FLIP_ESPERA + 450)
          }
        })
      }

      if (quiero('portrait-nota')) {
        console.log('■ portrait-nota')
        await navegarHasta(page, 27) // primera página nota (mensaje de invitado)
        await grabarClip(page, 'portrait-nota', 4800, PORTRAIT.width, PORTRAIT.height, async () => {
          await esperar(2100)
          await page.keyboard.press('ArrowRight') // al siguiente mensaje
          await esperar(FLIP_ESPERA + 300)
        })
      }

      if (quiero('portrait-video')) {
        console.log('■ portrait-video')
        await navegarHasta(page, 35) // página de video
        // El <video> solo recibe src cuando la página está a la vista; sin
        // esperarlo, el clip abre con el marco en negro (flash de carga).
        await page.waitForFunction(() => {
          const v = document.querySelector('video.mag__video')
          return Boolean(v && (v.currentSrc || v.src))
        }, { timeout: 15000, polling: 250 })
        await esperar(700) // póster/frame inicial ya pintado
        await grabarClip(page, 'portrait-video', 5200, PORTRAIT.width, PORTRAIT.height, async () => {
          await esperar(900)
          await page.evaluate(() => {
            const v = document.querySelector('video.mag__video')
            if (v) { v.muted = true; v.play().catch(() => {}) }
          })
          await esperar(4200)
        })
      }

      if (quiero('portrait-cierre')) {
        console.log('■ portrait-cierre')
        await navegarHasta(page, 35)
        await grabarClip(page, 'portrait-cierre', 4000, PORTRAIT.width, PORTRAIT.height, async () => {
          await esperar(700)
          await page.keyboard.press('ArrowRight') // entra la página de cierre
          await esperar(FLIP_ESPERA + 1400)
        })
      }
      await page.close()
    }

    // ── Pliego de dos páginas en ventana ancha ──────────────────────────────
    if (quiero('landscape-pliego')) {
      console.log('■ landscape-pliego')
      const page = await abrirAlbum(browser, LANDSCAPE)
      await grabarClip(page, 'landscape-pliego', 5800, 960, 540, async () => {
        await esperar(1300)
        for (let i = 0; i < 2; i++) {
          await page.keyboard.press('ArrowRight')
          await esperar(FLIP_ESPERA + 450)
        }
      })
      await page.close()
    }

    // ── Celular de verdad (emulado): aviso de giro + una página ─────────────
    if (quiero('mobile-aviso')) {
      console.log('■ mobile-aviso')
      const page = await abrirAlbum(browser, MOBILE)
      const hayAviso = await page.evaluate(() => Boolean(document.querySelector('.rotate-hint')))
      if (!hayAviso) console.log('  ! el aviso de giro no apareció en la emulación móvil')
      await grabarClip(page, 'mobile-aviso', 5600, MOBILE.width, MOBILE.height, async () => {
        await esperar(2400) // el aviso quieto, leíble
        await page.touchscreen.tap(390, 500) // mitad derecha: pasa de página con el aviso puesto
        await esperar(FLIP_ESPERA + 700)
      })
      await page.close()
    }
  } finally {
    await browser.close()
  }
  console.log(`\nListo. Clips en ${CLIPS}`)
}

main().catch((e) => { console.error('Fatal:', e.message); process.exit(1) })
