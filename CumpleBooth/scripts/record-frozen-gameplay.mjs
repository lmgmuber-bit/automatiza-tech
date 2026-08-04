import puppeteer from 'puppeteer-core'
import { spawn, execSync } from 'child_process'
import { mkdirSync, existsSync } from 'fs'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const BASE = 'http://localhost/automatiza-tech/CumpleBooth/dist'
const OUT = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\video-frozen'
const FAKE_CAM = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\explicativo\\fakecam\\nina.y4m'
const SLUG = 'demo-frozen-vip'

mkdirSync(OUT, { recursive: true })
const sleep = ms => new Promise(r => setTimeout(r, ms))

// Región REAL de la ventana de Chrome en pantalla. Sin esto, gdigrab con
// offset 0,0 fijo graba la esquina del escritorio (o sea, lo que haya
// encima: otras ventanas, el chat del agente, la barra de tareas) en vez del
// kiosco. Pasó el 2026-07-26 y el video salió con pantalla privada dentro.
let REGION = null

/**
 * Emula el viewport de tablet (768x1024) pero dibujado al 72%, para que quepa
 * en una pantalla de 768 de alto. `scale` solo afecta el pintado: las
 * coordenadas de mouse siguen siendo CSS, así que los arrastres de los juegos
 * aciertan igual que a tamaño completo.
 */
async function aplicarViewport(page) {
  const cdp = await page.target().createCDPSession()
  await cdp.send('Emulation.setDeviceMetricsOverride', {
    width: 768, height: 1024, deviceScaleFactor: 1, mobile: false, scale: 0.72,
  })
  await sleep(600)
  const v = await page.evaluate(() => ({ w: window.innerWidth, h: window.innerHeight }))
  console.log(`   viewport CSS: ${v.w}x${v.h}`)
  return v
}

async function medirVentana(page) {
  const r = await page.evaluate(() => ({
    x: window.screenX, y: window.screenY,
    ow: window.outerWidth, oh: window.outerHeight,
    iw: window.innerWidth, ih: window.innerHeight,
  }))
  // El viewport arranca debajo del chrome del navegador (barra de título +
  // barra de direcciones); se recorta solo el contenido, sin la UI.
  // El viewport va emulado al 72%, así que ocupa menos px físicos que su
  // tamaño CSS: la región a grabar se calcula sobre el tamaño ya escalado.
  const ESCALA = 0.72
  const anchoFisico = Math.round(r.iw * ESCALA)
  const altoFisico = Math.round(r.ih * ESCALA)
  const bordeX = Math.max(0, Math.round((r.ow - anchoFisico) / 2))
  const altoUI = Math.max(0, r.oh - altoFisico - bordeX)
  REGION = {
    x: r.x + bordeX,
    y: r.y + altoUI,
    // gdigrab exige dimensiones pares para yuv420p
    w: anchoFisico - (anchoFisico % 2),
    h: altoFisico - (altoFisico % 2),
  }
  console.log(`   región: ${REGION.w}x${REGION.h} en (${REGION.x},${REGION.y})`)
  return REGION
}

function startRec(filename, dur) {
  if (!REGION) throw new Error('medirVentana() no se llamó: no se sabe qué grabar')
  return spawn('ffmpeg', [
    '-f', 'gdigrab', '-framerate', '30',
    '-offset_x', String(REGION.x), '-offset_y', String(REGION.y),
    '-video_size', `${REGION.w}x${REGION.h}`, '-i', 'desktop',
    '-t', String(dur), '-c:v', 'libx264', '-preset', 'ultrafast',
    '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-y', filename,
  ], { stdio: ['pipe', 'pipe', 'pipe'] })
}

function stopRec(proc) {
  try { proc.stdin.write('q\n') } catch {}
  return new Promise((resolve) => {
    proc.on('close', resolve)
    setTimeout(() => { try { proc.kill() } catch {}; resolve() }, 3000)
  })
}

/**
 * Clases COMPLETAS de la pantalla activa. Devolver solo la primera rompía las
 * esperas: "screen juego juego-oferta" (¿Jugamos otro?) y "screen juego
 * juego--muneco" se reportaban ambas como "juego", así que esperar la oferta
 * hacía match con cualquier juego y el flujo se desincronizaba.
 */
function getScreen(page) {
  return page.evaluate(() => {
    const el = document.querySelector('.screen')
    if (!el) return 'none'
    return [...el.classList].filter((c) => c !== 'screen').join(' ') || 'screen-only'
  })
}

async function getCharacter(page) {
  return page.evaluate(() => {
    const el = document.querySelector('.photo-prep-char')
    return el ? el.textContent.trim() : ''
  })
}

/**
 * Click en el primer botón cuyo texto haga match, EXCLUYENDO siempre los que
 * abandonan el flujo. "Omitir e ir a la foto 📸" contiene "foto", así que un
 * patrón como /continuar|foto/ lo cazaba y descartaba los juegos que faltaban:
 * el juego de copos nunca se llegaba a grabar.
 */
const clickPorTexto = (page, fuente) => page.evaluate((f) => {
  const rx = new RegExp(f, 'i')
  const salida = /omitir|saltar/i
  const b = [...document.querySelectorAll('button')]
    .find((x) => rx.test(x.textContent) && !salida.test(x.textContent) && !x.disabled)
  if (b) { b.click(); return b.textContent.trim() }
  return null
}, fuente)

async function goToParty(page) {
  await page.goto(`${BASE}/?p=${SLUG}`, { waitUntil: 'networkidle2' })
  await sleep(1800)
  // El gate de entrada es un <button> con texto, no un elemento .intro.
  await clickPorTexto(page, 'toca para entrar')
  await sleep(1800)
}

/**
 * Elige invitado y lanza la ruleta. Se selecciona por GÉNERO recorriendo el
 * DOM en orden, nunca por nombre exacto: los nombres llevan tilde y cambian
 * si la fiesta se resiembra.
 */
async function selectGuestAndSpin(page, saltarBienvenida = true) {
  try {
    await page.waitForSelector('.invitado-item', { visible: true, timeout: 12000 })
  } catch {
    console.log(`   sin lista de invitados (pantalla: ${await getScreen(page)})`)
    return false
  }
  const invitado = await page.evaluate(() => {
    let grupo = null
    for (const n of document.querySelectorAll('.invitados-group, .invitado-item')) {
      if (n.classList.contains('invitados-group')) {
        grupo = /varon/i.test(n.textContent) ? 'varon' : 'nina'
        continue
      }
      if (grupo === 'nina') { n.click(); return n.textContent.trim() }
    }
    return null
  })
  if (!invitado) { console.log('   no se pudo elegir invitado'); return false }
  console.log(`   invitado: ${invitado}`)
  await sleep(700)

  // Al elegir invitado se abre el popup de bienvenida con el video inmersivo
  // (en Reino de Hielo dura 14s). El botón de girar NO existe hasta que ese
  // video termina. El popup es un <div> con onClick={skipWelcome}, no un
  // <button>: hay que cliquearlo directo para saltarlo.
  if (saltarBienvenida) {
    await page.evaluate(() => document.querySelector('.welcome-popup')?.click())
    await sleep(600)
  }

  for (let i = 0; i < 30; i++) {
    if (await clickPorTexto(page, 'girar|ruleta')) return true
    await page.evaluate(() => document.querySelector('.welcome-popup')?.click())
    await sleep(700)
  }
  console.log(`   nunca apareció el botón de girar (pantalla: ${await getScreen(page)})`)
  return false
}

/**
 * Igual que selectGuestAndSpin pero se detiene ANTES de tocar "girar", para
 * poder empezar a grabar y recién entonces lanzar la ruleta.
 */
async function prepararGiro(page) {
  try {
    await page.waitForSelector('.invitado-item', { visible: true, timeout: 12000 })
  } catch {
    console.log(`   sin lista de invitados (pantalla: ${await getScreen(page)})`)
    return false
  }
  const invitado = await page.evaluate(() => {
    let grupo = null
    for (const n of document.querySelectorAll('.invitados-group, .invitado-item')) {
      if (n.classList.contains('invitados-group')) {
        grupo = /varon/i.test(n.textContent) ? 'varon' : 'nina'
        continue
      }
      if (grupo === 'nina') { n.click(); return n.textContent.trim() }
    }
    return null
  })
  if (!invitado) return false
  console.log(`   invitado: ${invitado}`)
  await sleep(700)
  // Se salta la bienvenida: ya quedó grabada entera en grabarEntrada().
  await page.evaluate(() => document.querySelector('.welcome-popup')?.click())
  await sleep(600)

  for (let i = 0; i < 30; i++) {
    const hay = await page.evaluate(() =>
      [...document.querySelectorAll('button')].some((b) => /girar|ruleta/i.test(b.textContent)))
    if (hay) return true
    await page.evaluate(() => document.querySelector('.welcome-popup')?.click())
    await sleep(700)
  }
  console.log(`   nunca apareció el botón de girar (pantalla: ${await getScreen(page)})`)
  return false
}

/**
 * Graba la ENTRADA a la experiencia, que es lo primero que ve el invitado:
 * portada del kiosco → elige su nombre → video inmersivo de bienvenida.
 *
 * Va en una pasada aparte de la búsqueda de Olaf porque estas tres pantallas
 * no dependen del personaje que salga en la ruleta: la bienvenida es de la
 * temática, no del personaje. Así se graba una sola vez, sin saltarla.
 */
async function grabarEntrada(page) {
  console.log('\n=== ENTRADA: portada + invitados + video inmersivo ===')
  await page.goto(`${BASE}/?p=${SLUG}`, { waitUntil: 'networkidle2' })
  await sleep(2000)

  const cIntro = `${OUT}\\clip-00a-intro.mp4`
  let rec = startRec(cIntro, 11)
  await sleep(1800)                       // se ve la portada quieta
  await clickPorTexto(page, 'toca para entrar')
  await sleep(3200)                       // se ve la lista de invitados
  await page.evaluate(() => {
    let grupo = null
    for (const n of document.querySelectorAll('.invitados-group, .invitado-item')) {
      if (n.classList.contains('invitados-group')) {
        grupo = /varon/i.test(n.textContent) ? 'varon' : 'nina'
        continue
      }
      if (grupo === 'nina') { n.click(); return }
    }
  })
  await sleep(2500)
  await stopRec(rec); await sleep(500)
  console.log(`   ✓ intro: ${probe(cIntro).toFixed(1)}s`)

  // El video de bienvenida ya está corriendo: se deja completo, sin saltarlo.
  const cInm = `${OUT}\\clip-00b-inmersivo.mp4`
  rec = startRec(cInm, 15)
  await sleep(15500)
  await stopRec(rec); await sleep(500)
  console.log(`   ✓ inmersivo: ${probe(cInm).toFixed(1)}s`)
  return [cIntro, cInm]
}

/**
 * Busca a Olaf girando la ruleta, y graba SIEMPRE el giro y el saludo: el
 * archivo se sobrescribe en cada intento, así el que queda al final es el de
 * la tirada buena. La ruleta se sortea con Math.random() sin semilla ni
 * parámetro de URL, no hay forma de forzarla.
 */
async function spinForOlaf(page) {
  const cRuleta = `${OUT}\\clip-00c-ruleta.mp4`
  const cSaludo = `${OUT}\\clip-00d-saludo.mp4`

  for (let a = 1; a <= 25; a++) {
    console.log(`\n--- Attempt ${a}/25 ---`)
    await goToParty(page)
    if (!(await prepararGiro(page))) { console.log('   Spin failed'); continue }

    // Grabando ANTES de tocar "girar": el giro es el momento de tensión.
    const rec = startRec(cRuleta, 12)
    await sleep(600)
    await clickPorTexto(page, 'girar|ruleta')
    await sleep(9000)
    await stopRec(rec); await sleep(400)

    let char = ''
    for (let w = 0; w < 12; w++) {
      const s = await getScreen(page)
      if (/video-screen/.test(s)) { char = await getCharacter(page); if (char) break }
      if (/juego/.test(s)) break
      await sleep(500)
    }

    console.log(`   Char: "${char}"`)
    if (char.toLowerCase() === 'olaf') {
      console.log('   ✅')
      const rec2 = startRec(cSaludo, 9)
      await sleep(8500)
      await stopRec(rec2); await sleep(400)
      console.log(`   ✓ saludo: ${probe(cSaludo).toFixed(1)}s`)
      return [cRuleta, cSaludo]
    }
  }
  console.log('❌ Not found')
  return null
}

// === GAME PLAYERS ===

/**
 * Arrastra las piezas del muñeco con PointerEvents sintéticos, no con
 * page.mouse. Con el viewport emulado a escala 0.72 las coordenadas del mouse
 * real de CDP no coinciden con las CSS y el arrastre nunca acertaba el destino.
 * El juego escucha pointerdown en la pieza y pointermove/pointerup en window
 * usando clientX/clientY, así que despacharlos a mano es exacto y fiable.
 */
async function playArmarMuneco(page) {
  console.log('   Playing armar-muneco...')

  // Mismos destinos que MUNECO_PARTES en App.jsx (en % del escenario).
  const targets = {
    cabeza: { x: 50, y: 18 }, panza: { x: 50, y: 44 }, base: { x: 50, y: 77 },
    'brazo-izq': { x: 21, y: 45 }, 'brazo-der': { x: 79, y: 45 }, nariz: { x: 50, y: 21 },
  }

  for (let i = 0; i < 8; i++) {
    const restantes = await page.evaluate(() => document.querySelectorAll('.mp-pieza--suelta').length)
    if (!restantes) break

    const puesta = await page.evaluate(async (tgts) => {
      const dormir = (ms) => new Promise((r) => setTimeout(r, ms))
      const stage = document.querySelector('.mp-stage')
      const pieza = document.querySelector('.mp-pieza--suelta')
      if (!stage || !pieza) return null
      const id = (pieza.getAttribute('aria-label') || '').replace(/^Pieza /, '')
      const t = tgts[id]
      if (!t) return `desconocida:${id}`

      const rs = stage.getBoundingClientRect()
      const rp = pieza.getBoundingClientRect()
      const x0 = rp.x + rp.width / 2
      const y0 = rp.y + rp.height / 2
      const x1 = rs.left + (t.x / 100) * rs.width
      const y1 = rs.top + (t.y / 100) * rs.height

      const ev = (tipo, x, y, destino) => destino.dispatchEvent(
        new PointerEvent(tipo, { bubbles: true, cancelable: true, clientX: x, clientY: y, pointerId: 1, isPrimary: true })
      )

      ev('pointerdown', x0, y0, pieza)
      const pasos = 14
      for (let s = 1; s <= pasos; s++) {
        ev('pointermove', x0 + ((x1 - x0) * s) / pasos, y0 + ((y1 - y0) * s) / pasos, window)
        await dormir(28)
      }
      ev('pointerup', x1, y1, window)
      return id
    }, targets)

    if (!puesta) break
    console.log(`   pieza: ${puesta}`)
    await sleep(500)
  }

  // Al completarlo aparece "¡Vamos a tu foto!" con un CTA, pero además hay un
  // auto-avance a los 3s: se intenta el CTA y si ya avanzó solo, da igual.
  await sleep(1500)
  const cta = await clickPorTexto(page, 'continuar|listo|seguir')
  console.log(cta ? `   → "${cta}"` : '   (avanzó solo)')
  await sleep(1500)
}

/** Espera a que la clase de .screen haga match. Devuelve la clase o null. */
async function esperarPantalla(page, rx, timeout = 15000) {
  const t0 = Date.now()
  while (Date.now() - t0 < timeout) {
    const s = await getScreen(page)
    if (rx.test(s)) return s
    await sleep(300)
  }
  return null
}

/** Espera la pantalla "¿Jugamos otro?" y acepta el siguiente juego. */
async function aceptarOferta(page) {
  const ok = await esperarPantalla(page, /juego-oferta/, 12000)
  if (!ok) { console.log(`   sin oferta (pantalla: ${await getScreen(page)})`); return false }
  await sleep(1200) // que se vea la pantalla en el video antes de responder
  await page.evaluate(() => document.querySelector('.juego-oferta__btn--si')?.click())
  await sleep(1500)
  return true
}

/**
 * Resuelve el rompecabezas de verdad, no a lo bruto: intercambios al azar casi
 * nunca ordenan 9 piezas, y el video terminaba mostrando un "Saltar" en vez de
 * la satisfacción de armarlo.
 *
 * Qué pieza hay en cada casilla se deduce del backgroundPosition, que el
 * componente calcula como (col/(cols-1))% (fila/(filas-1))%.
 */
async function leerOrden(page) {
  return page.evaluate(() => {
    const tablero = document.querySelector('.puzzle-tablero')
    if (!tablero) return null
    const cols = parseInt(getComputedStyle(tablero).getPropertyValue('--puzzle-cols')) || 3
    const filas = parseInt(getComputedStyle(tablero).getPropertyValue('--puzzle-filas')) || 3
    const piezas = [...tablero.querySelectorAll('.puzzle-pieza')]
    return {
      cols,
      filas,
      // Se lee el style INLINE, no el computado: getComputedStyle resuelve los
      // porcentajes a píxeles y el cálculo de col/fila salía mal, así que el
      // rompecabezas nunca se resolvía y el video terminaba mostrando "Saltar".
      orden: piezas.map((b) => {
        const [px, py] = (b.style.backgroundPosition || '0% 0%').split(' ')
        const col = cols > 1 ? Math.round((parseFloat(px) / 100) * (cols - 1)) : 0
        const fila = filas > 1 ? Math.round((parseFloat(py) / 100) * (filas - 1)) : 0
        return fila * cols + col
      }),
    }
  })
}

async function playFichas(page) {
  console.log('   Playing fichas...')
  const estado = await leerOrden(page)
  if (!estado) {
    console.log('   sin tablero; se salta')
    try { await page.click('.juego-skip'); await sleep(2000) } catch {}
    return
  }
  const orden = estado.orden.slice()
  const total = orden.length

  // Ordenamiento por selección: cada par de toques deja una pieza en su lugar,
  // así se ve un avance constante (el HUD sube 1/9, 2/9…) en vez de caos.
  // Las piezas se vuelven a buscar en cada toque DENTRO de la página: React
  // re-renderiza el tablero tras cada intercambio y los ElementHandle de
  // Puppeteer quedan apuntando a nodos ya desmontados.
  const tocar = (pos) => page.evaluate((p) => {
    const b = document.querySelectorAll('.puzzle-pieza')[p]
    if (b) { b.click(); return true }
    return false
  }, pos)

  for (let i = 0; i < total; i++) {
    if (orden[i] === i) continue
    const j = orden.indexOf(i)
    if (j < 0) continue
    await tocar(i); await sleep(200)
    await tocar(j); await sleep(360)
    ;[orden[i], orden[j]] = [orden[j], orden[i]]
  }

  await sleep(1000)
  if (await page.$('.puzzle-tablero--listo')) {
    console.log('   ¡Resuelto!')
    await sleep(2200)
    await clickPorTexto(page, 'continuar|listo|seguir|foto')
    await sleep(1500)
  } else {
    console.log('   no quedó resuelto; se salta')
    await page.evaluate(() => document.querySelector('.juego-skip')?.click())
    await sleep(2000)
  }
}

async function playCopos(page) {
  console.log('   Playing copos...')
  const start = Date.now()
  while (Date.now() - start < 9000) {
    try {
      const copos = await page.$$('button.copo')
      if (copos.length > 0) {
        await copos[Math.floor(Math.random() * copos.length)].click()
      }
    } catch {}
    await sleep(150 + Math.random() * 250)
  }
  await sleep(10000)
}

function probe(file) {
  try {
    const r = execSync(`ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "${file}"`, { encoding: 'utf8' })
    return parseFloat(r.trim())
  } catch { return 0 }
}

// === MAIN ===

async function main() {
  console.log('=== RECORD FROZEN GAMEPLAY ===')
  
  // La pantalla real es 1366x768, así que una ventana de 1024 de alto se corta
  // contra la barra de tareas. Se usa una ventana vertical que SÍ entra, en
  // modo --app (sin barra de direcciones) y sin el infobar de automatización,
  // para que el cuadro grabado sea solo el kiosco.
  const browser = await puppeteer.launch({
    executablePath: CHROME, headless: false,
    ignoreDefaultArgs: ['--enable-automation'],
    defaultViewport: null,
    args: [
      `--app=${BASE}/?p=${SLUG}`,
      // La ventana física entra en la pantalla real (1366x768). El viewport
      // CSS de tablet (768x1024) se consigue aparte, emulándolo con escala
      // vía CDP en aplicarViewport(): sin ese viewport los juegos no funcionan
      // (el arrastre del muñeco no acierta y el tablero del puzzle no aparece).
      '--window-position=0,0', '--window-size=560,748',
      '--use-fake-device-for-media-stream',
      `--use-file-for-fake-video-capture=${FAKE_CAM}`,
      '--use-fake-ui-for-media-stream',
      '--autoplay-policy=no-user-gesture-required', '--no-first-run',
      '--disable-infobars', '--hide-scrollbars',
      // Evita el diálogo "quiere descargar varios archivos" al guardar foto
      // y diploma, que en la corrida anterior tapó la pantalla de cierre.
      '--disable-features=DownloadBubble,DownloadBubbleV2',
    ],
  })

  // En modo --app la ventana ya viene abierta con la URL: se reusa esa pestaña
  // en vez de abrir una nueva (que saldría como ventana aparte, sin grabar).
  const paginas = await browser.pages()
  const page = paginas.find((p) => p.url().includes(SLUG)) || paginas[0] || await browser.newPage()
  const clips = []

  try {
    await page.goto(`${BASE}/?p=${SLUG}`, { waitUntil: 'networkidle2' })
    await sleep(1200)
    await aplicarViewport(page)
    await medirVentana(page)

    // La experiencia arranca antes de los juegos: portada, elegir nombre y el
    // video inmersivo de bienvenida. Se graban primero para que el video
    // promocional cuente el recorrido completo, no solo el gameplay.
    clips.push(...await grabarEntrada(page))

    const inicio = await spinForOlaf(page)
    if (!inicio) { console.log('Aborting.'); return }
    clips.push(...inicio)

    console.log('\n--- Skipping character video ---')
    for (let i = 0; i < 5; i++) {
      const s = await getScreen(page)
      if (/juego/.test(s)) break
      try { const btn = await page.$('.skip'); if (btn) { await btn.click(); await sleep(2000) } else break }
      catch { break }
    }

    // === GAME 1: armar-muneco ===
    console.log('\n=== GAME 1: armar-muneco ===')
    const c1 = `${OUT}\\clip-01-armar-muneco.mp4`
    let rec = startRec(c1, 30)
    await sleep(400)
    await playArmarMuneco(page)
    await sleep(1500)
    await stopRec(rec)
    await sleep(500)
    if (existsSync(c1) && probe(c1) > 0.5) clips.push(c1)
    console.log(`   → ${probe(c1).toFixed(1)}s`)

    // === OFFER 1 ===
    console.log('\n--- Offer 1 ---')
    const co1 = `${OUT}\\clip-o1-oferta.mp4`
    rec = startRec(co1, 9)
    await sleep(300)
    await aceptarOferta(page)
    await stopRec(rec); await sleep(500)
    if (existsSync(co1) && probe(co1) > 0.3) clips.push(co1)

    // === GAME 2: fichas ===
    console.log('\n=== GAME 2: fichas ===')
    await esperarPantalla(page, /^juego$|juego--/, 10000)
    await sleep(800)
    const c2 = `${OUT}\\clip-02-fichas.mp4`
    rec = startRec(c2, 20)
    await sleep(400)
    await playFichas(page)
    await sleep(1500)
    await stopRec(rec); await sleep(500)
    if (existsSync(c2) && probe(c2) > 0.5) clips.push(c2)
    console.log(`   → ${probe(c2).toFixed(1)}s`)

    // === OFFER 2 ===
    console.log('\n--- Offer 2 ---')
    const co2 = `${OUT}\\clip-o2-oferta.mp4`
    rec = startRec(co2, 9)
    await sleep(300)
    await aceptarOferta(page)
    await stopRec(rec); await sleep(500)
    if (existsSync(co2) && probe(co2) > 0.3) clips.push(co2)

    // === GAME 3: copos ===
    console.log('\n=== GAME 3: copos ===')
    await esperarPantalla(page, /^juego$|juego--/, 10000)
    await sleep(800)
    const c3 = `${OUT}\\clip-03-copos.mp4`
    rec = startRec(c3, 25)
    await sleep(400)
    await playCopos(page)
    await sleep(2000)
    await stopRec(rec); await sleep(500)
    if (existsSync(c3) && probe(c3) > 0.5) clips.push(c3)
    console.log(`   → ${probe(c3).toFixed(1)}s`)

    // === RESULT: Photo + Diploma ===
    console.log('\n--- Result ---')
    // Tras el último juego ya no hay oferta: se va directo a la cámara.
    await clickPorTexto(page, 'continuar|listo|seguir|foto')
    await esperarPantalla(page, /capture|transition|video-screen/, 15000)
    console.log(`   pantalla: ${await getScreen(page)}`)

    const c4 = `${OUT}\\clip-04-resultado.mp4`
    rec = startRec(c4, 26)
    await sleep(300)
    // Los clicks van por JS, no con page.click: con el viewport emulado a
    // escala las coordenadas del mouse real de CDP no caen sobre el botón.
    try {
      await page.waitForSelector('button.shutter:not([disabled])', { visible: true, timeout: 12000 })
      await sleep(600)
      await page.evaluate(() => document.querySelector('button.shutter')?.click())
      await sleep(5000)
    } catch { console.log('   no se pudo disparar la foto') }
    try { await page.waitForSelector('.preview-img', { visible: true, timeout: 12000 }); await sleep(1800) } catch {}
    await page.evaluate(() => {
      for (const b of document.querySelectorAll('.preview-bar button')) {
        if (b.textContent.includes('Guardar') && !b.disabled) { b.click(); return }
      }
    })
    await sleep(6000)
    console.log(`   pantalla: ${await getScreen(page)}`)
    await stopRec(rec); await sleep(500)
    if (existsSync(c4) && probe(c4) > 0.5) clips.push(c4)
    console.log(`   → ${probe(c4).toFixed(1)}s`)

    // === QR + DIPLOMA + DESPEDIDA ===
    // Luis pidió que el video llegue HASTA la despedida. Ojo: los botones
    // "Siguiente invitado" de QRScreen/DiplomaScreen históricamente saltaban
    // directo a reset() sin pasar por 'farewell'; se avanza pantalla por
    // pantalla y se verifica en qué quedó.
    console.log('\n--- QR / diploma / despedida ---')
    const c5 = `${OUT}\\clip-05-cierre.mp4`
    rec = startRec(c5, 34)
    await sleep(300)
    for (let paso = 0; paso < 8; paso++) {
      const s = await getScreen(page)
      console.log(`   paso ${paso}: ${s}`)
      // Llegó la despedida: se deja correr entera, sin tocar "Terminar".
      if (/farewell|finale/.test(s)) { console.log('   despedida en curso'); await sleep(11000); break }
      // "Ver diploma" avanza; "Descargar diploma" NO (solo baja el archivo y
      // deja la pantalla igual, así que el loop se quedaba pegado ahí).
      // "Siguiente invitado" es el que lleva a la despedida.
      const avanzo = await page.evaluate(() => {
        const avanza = /ver diploma|siguiente invitado/i
        const b = [...document.querySelectorAll('button')]
          .find((x) => avanza.test(x.textContent) && !x.disabled)
        if (b) { b.click(); return b.textContent.trim() }
        return null
      })
      if (!avanzo) { await sleep(1200); continue }
      console.log(`      → "${avanzo}"`)
      await sleep(3500)
    }
    console.log(`   pantalla final: ${await getScreen(page)}`)
    await stopRec(rec); await sleep(500)
    if (existsSync(c5) && probe(c5) > 0.5) clips.push(c5)
    console.log(`   → ${probe(c5).toFixed(1)}s`)

    // === SUMMARY ===
    console.log('\n=== CLIPS ===')
    let total = 0
    for (const c of clips) {
      const d = probe(c)
      total += d
      console.log(`  ${c.split('\\').pop()}: ${d.toFixed(2)}s`)
    }
    console.log(`  TOTAL: ${total.toFixed(1)}s`)

  } finally {
    await browser.close()
    console.log('\nDone.')
  }
}

main().catch(e => { console.error(e); process.exit(1) })
