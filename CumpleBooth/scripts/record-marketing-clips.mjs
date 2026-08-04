/**
 * Re-captura, EN VIVO, la ruleta girando + la pantalla de foto final para las
 * 3 temáticas del video explicativo — sin el parche de "pintar sobre el
 * verde": Chrome recibe la foto real como cámara falsa
 * (`--use-file-for-fake-video-capture`), así que el kiosco compone la foto
 * él mismo, igual que en un cliente real.
 *
 * Por qué reemplaza al parche anterior (fix-green-screen.mjs):
 * - Antes: cámara de prueba = verde sólido → había que detectar el verde y
 *   pegar una foto encima con ffmpeg. Frágil (el verde resultó ser un
 *   degradado, no un color plano) y quedaba SIEMPRE la misma cara en las 3
 *   temáticas con el mismo nombre de invitado ya grabado en la captura vieja.
 * - Ahora: cada temática recorre el flujo real con su propio invitado y su
 *   propia foto — nombre y cara coinciden siempre, y de paso queda variedad
 *   (Luis, 2026-07-26: "agrega fotos de cada tematica").
 *
 * De paso graba el GIRO REAL de la ruleta (antes solo había una foto fija con
 * zoom) vía CDP screencast, con la MISMA sesión de Chrome — no hace falta
 * abrir el kiosco dos veces.
 *
 * Uso:  node scripts/record-marketing-clips.mjs
 */
import puppeteer from 'puppeteer-core'
import { execSync } from 'node:child_process'
import { mkdirSync, writeFileSync, rmSync, existsSync } from 'node:fs'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const BASE = 'http://localhost/automatiza-tech/CumpleBooth/dist/'
const SCREENS = 'C:/wamp64/www/automatiza-tech/CumpleBooth/design/screens'
const VIDEO_OUT = 'C:/wamp64/www/automatiza-tech/CumpleBooth/design/video'
const TMP = `${process.env.TEMP}/cc-ruleta-frames`

const sh = (cmd) => execSync(cmd, { stdio: ['ignore', 'pipe', 'pipe'], maxBuffer: 1e9 }).toString()
const esperar = (ms) => new Promise((r) => setTimeout(r, ms))

// Cada temática con su propio invitado y su propia "cámara" — variedad real,
// no la misma cara repetida. El patrón de nombre debe existir en esa fiesta.
const OBJETIVOS = [
  {
    slug: 'demo-bluey', tema: 'bluey',
    fakeVideo: 'C:/wamp64/www/automatiza-tech/CumpleBooth/design/explicativo/fakecam/nina.y4m',
    genero: 'nina',
    archivoRuleta: 'ruleta-girando-bluey.mp4',
    archivoPreview: `${SCREENS}/screen-06-preview.png`,
    grabarRuleta: true,
  },
  {
    slug: 'demo', tema: 'carreras',
    fakeVideo: 'C:/wamp64/www/automatiza-tech/CumpleBooth/design/explicativo/fakecam/nino.y4m',
    genero: 'varon',
    archivoRuleta: null, // ya tenemos la de Bluey, no hace falta otra
    archivoPreview: `${SCREENS}/carreras-screen-06-preview.png`,
    grabarRuleta: false,
  },
  {
    slug: 'demo-tropical', tema: 'tropical',
    fakeVideo: 'C:/wamp64/www/automatiza-tech/CumpleBooth/design/explicativo/fakecam/nino2.y4m',
    genero: 'varon',
    archivoRuleta: null,
    archivoPreview: `${SCREENS}/tropical-screen-06-preview.png`,
    grabarRuleta: false,
  },
]

// Elige por GÉNERO, no por nombre exacto: la lista de invitados de las fiestas
// demo cambia con el tiempo (se resembró con nombres con tilde — "Sofía", no
// "Sofia" — y "Matías"/"Benjamín" que ni siquiera existen en todas las
// fiestas). Anclar a un nombre puntual rompía en las 3 temáticas.
// El DOM no agrupa en un <div> por género: es una lista plana de
// <p class="invitados-group">Niñas|Varones</p> seguida de
// <button class="invitado-item">, así que hay que recorrerla en orden y
// recordar en qué grupo se está parado.
const estadoDebug = (page) => page.evaluate(() => {
  const s = document.querySelector('.screen')
  return {
    pantalla: s ? s.className : '(sin .screen)',
    shutterExiste: Boolean(document.querySelector('button.shutter')),
    shutterDeshabilitado: document.querySelector('button.shutter')?.disabled ?? null,
    previewExiste: Boolean(document.querySelector('.preview-img')),
  }
})

async function elegirInvitado(page, genero) {
  return page.evaluate((generoDeseado) => {
    const nodos = [...document.querySelectorAll('.invitados-group, .invitado-item')]
    let grupoActual = null
    for (const n of nodos) {
      if (n.classList.contains('invitados-group')) {
        grupoActual = /varon/i.test(n.textContent) ? 'varon' : 'nina'
        continue
      }
      if (grupoActual === generoDeseado) {
        n.click()
        return n.textContent.trim()
      }
    }
    return null
  }, genero)
}

async function saltarBienvenidaYGirar(page) {
  await page.waitForFunction(() => {
    const btns = [...document.querySelectorAll('button')]
    const girar = btns.find((x) => /girar|ruleta/i.test(x.textContent))
    if (girar) return true
    const seguir = btns.find((x) => /continuar|saltar/i.test(x.textContent))
    if (seguir) seguir.click()
    return false
  }, { timeout: 30000, polling: 500 })
}

/** Graba la ruleta girando vía CDP screencast y devuelve la ruta del mp4. */
async function grabarRuleta(page, archivoSalida) {
  const dir = `${TMP}/${Date.now()}`
  mkdirSync(dir, { recursive: true })
  const client = await page.target().createCDPSession()
  // Sin esto, Chrome pinta la ventana a un ritmo mínimo cuando no la
  // considera "enfocada" — el primer intento capturó toda la rueda (3.6s en
  // JS real, confirmado con waitForFunction) en apenas ~1s de fotogramas
  // PINTADOS. Forzar el foco de emulación hace que renderice a ritmo normal.
  await page.bringToFront()
  await client.send('Emulation.setFocusEmulationEnabled', { enabled: true })
  const frames = []

  client.on('Page.screencastFrame', async (frame) => {
    frames.push({ t: Date.now(), data: frame.data })
    await client.send('Page.screencastFrameAck', { sessionId: frame.sessionId })
  })

  // Con el tamaño real del viewport (768x1024 @2x = 1536x2048) el primer
  // intento capturó solo 8 frames en ~4s: cada JPEG a esa resolución tarda
  // demasiado en codificar + viajar por el pipe CDP. Se pide la mitad del
  // tamaño — de todos modos se reescala a 1080x1920 al armar el video.
  await client.send('Page.startScreencast', {
    format: 'jpeg', quality: 80, everyNthFrame: 1, maxWidth: 540, maxHeight: 960,
  })

  const midiendo = page.waitForFunction(() => {
    const r = document.querySelector('.spinner-rotator')
    if (!r) return false
    return parseFloat(getComputedStyle(r).getPropertyValue('--spin') || '0') > 40
  }, { timeout: 20000, polling: 'raf' })

  await page.evaluate(() => {
    const b = [...document.querySelectorAll('button')].find((x) => /girar|ruleta/i.test(x.textContent))
    if (b) b.click()
  })

  await midiendo
  await esperar(900) // deja ver el resultado quieto un instante antes de cortar

  await client.send('Page.stopScreencast')
  client.removeAllListeners('Page.screencastFrame')

  console.log(`    ${frames.length} frames capturados`)
  if (frames.length < 5) throw new Error('muy pocos frames, la grabación falló')

  // El truco de "-f concat" con una línea `duration X` por archivo NO
  // respetó los tiempos reales: el primer intento dio 25 frames a 25fps
  // exactos (1.0s clavado) sin importar que la grabación real duró varios
  // segundos — se ignoraron las duraciones declaradas. En vez de pelear con
  // el demuxer concat, se calcula un framerate de ENTRADA a partir del
  // tiempo real transcurrido (frames / segundos reales) y se deja que
  // `-r 25` de salida rellene por duplicación — mismo resultado visual para
  // un inserto corto de 4-5s, mucho más simple y confiable.
  const segundosReales = (frames[frames.length - 1].t - frames[0].t) / 1000 || 1
  const fpsEntrada = Math.max(1, frames.length / segundosReales)
  frames.forEach((f, i) => {
    writeFileSync(`${dir}/f${String(i).padStart(4, '0')}.jpg`, Buffer.from(f.data, 'base64'))
  })
  console.log(`    ${segundosReales.toFixed(2)}s reales → fps de entrada ${fpsEntrada.toFixed(2)}`)

  mkdirSync(VIDEO_OUT, { recursive: true })
  const salida = `${VIDEO_OUT}/${archivoSalida}`
  sh(`ffmpeg -y -v error -framerate ${fpsEntrada.toFixed(3)} -i "${dir}/f%04d.jpg" ` +
     `-vf "scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920" -r 25 ` +
     `-c:v libx264 -pix_fmt yuv420p -movflags +faststart "${salida}"`)
  rmSync(dir, { recursive: true, force: true })
  console.log(`    ✓ ${archivoSalida}`)
  return salida
}

async function capturarUno(browser, obj) {
  console.log(`\n=== ${obj.tema} (${obj.slug}) ===`)
  const page = await browser.newPage()
  page.setDefaultTimeout(30000)
  page.on('pageerror', (e) => console.log(`  [pageerror] ${e.message}`))
  page.on('console', (m) => { if (m.type() === 'error') console.log(`  [console.error] ${m.text()}`) })
  await page.setViewport({ width: 768, height: 1024, deviceScaleFactor: 2 })

  await page.goto(`${BASE}?p=${obj.slug}`, { waitUntil: 'networkidle2', timeout: 30000 })
  await esperar(1200)

  await page.evaluate(() => {
    const b = [...document.querySelectorAll('button')].find((x) => /toca para entrar/i.test(x.textContent))
    if (b) b.click()
  })
  await esperar(1500)

  const invitado = await elegirInvitado(page, obj.genero)
  console.log(`  invitado: ${invitado || '(NO se encontró ningún botón de ese género — revisar la lista real)'}`)
  if (!invitado) throw new Error(`sin invitado ${obj.genero} en ${obj.slug}`)

  await saltarBienvenidaYGirar(page)

  if (obj.grabarRuleta && obj.archivoRuleta) {
    console.log('  grabando ruleta...')
    await grabarRuleta(page, obj.archivoRuleta)
  } else {
    // BUG corregido: esperaba a que la rueda estuviera girando ANTES de
    // hacer clic en "girar" — nunca iba a pasar, la espera siempre agotaba
    // los 20s porque el clic que arranca el giro todavía no había ocurrido.
    // El clic va primero (como en grabarRuleta, que sí lo hacía bien).
    const midiendo = page.waitForFunction(() => {
      const r = document.querySelector('.spinner-rotator')
      if (!r) return false
      return parseFloat(getComputedStyle(r).getPropertyValue('--spin') || '0') > 40
    }, { timeout: 20000, polling: 'raf' })
    await page.evaluate(() => {
      const b = [...document.querySelectorAll('button')].find((x) => /girar|ruleta/i.test(x.textContent))
      if (b) b.click()
    })
    await midiendo
    await esperar(4200)
  }

  console.log(`  tras ruleta: ${JSON.stringify(await estadoDebug(page))}`)

  // Saludo del personaje (+ transición/confetti si la temática la usa): saltar
  // si hay botón de saltar, hasta llegar a la pantalla de cámara.
  for (let i = 0; i < 8; i++) {
    await esperar(1000)
    const salteado = await page.evaluate(() => {
      const b = [...document.querySelectorAll('button')].find((x) => /saltar/i.test(x.textContent))
      if (b) { b.click(); return true }
      return false
    })
    const enCaptura = await page.evaluate(() => Boolean(document.querySelector('button.shutter')))
    if (enCaptura) break
    if (!salteado) await esperar(800)
  }
  console.log(`  antes de disparar: ${JSON.stringify(await estadoDebug(page))}`)

  await page.waitForSelector('button.shutter:not([disabled])', { visible: true, timeout: 15000 })
  await esperar(600) // que el feed de la cámara falsa ya esté pintando el frame real
  await page.click('.shutter')
  await esperar(5000) // countdown 3-2-1 + compositing
  console.log(`  tras disparar: ${JSON.stringify(await estadoDebug(page))}`)

  await page.waitForSelector('.preview-img', { visible: true, timeout: 15000 })
  await esperar(500)
  await page.screenshot({ path: obj.archivoPreview, type: 'png' })
  console.log(`  ✓ ${obj.archivoPreview.split('/').pop()}`)

  await page.close()
}

async function main() {
  // `--use-file-for-fake-video-capture` es un flag de PROCESO: hay que
  // relanzar Chrome por cada temática para cambiar qué foto "ve" la cámara.
  const soloEsto = process.argv[2] // opcional: node record-marketing-clips.mjs bluey
for (const obj of OBJETIVOS.filter((o) => !soloEsto || o.tema === soloEsto)) {
    const b = await puppeteer.launch({
      executablePath: CHROME,
      headless: false,
      args: [
        // ⚠️ `--use-file-for-fake-video-capture` SOLO no alcanza: sin
        // `--use-fake-device-for-media-stream` de acompañante, Chrome usó el
        // dispositivo real (la webcam física de la máquina) en vez del
        // archivo — el primer intento terminó capturando el cuarto real y a
        // una persona real de fondo en screen-06-preview.png. Se borró esa
        // captura al toque. Los dos flags juntos SÍ fuerzan el archivo.
        '--use-fake-device-for-media-stream',
        `--use-file-for-fake-video-capture=${obj.fakeVideo}`,
        '--use-fake-ui-for-media-stream',
        '--autoplay-policy=no-user-gesture-required',
        '--no-first-run',
      ],
      defaultViewport: null,
    })
    try {
      await capturarUno(b, obj)
    } catch (e) {
      console.log(`  ! ${obj.tema}: ${e.message}`)
    } finally {
      await b.close()
    }
  }
  console.log('\nListo.')
}

main().catch((e) => { console.error('Fatal:', e.message); process.exit(1) })
