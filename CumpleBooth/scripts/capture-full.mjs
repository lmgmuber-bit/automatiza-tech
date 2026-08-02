import puppeteer from 'puppeteer-core'
import { mkdirSync } from 'fs'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const BASE = 'http://localhost/automatiza-tech/CumpleBooth/dist'
const OUT = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\screens'
const FLOW = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\screens\\flow'
const ADMIN_PASSWORD = process.env.CUMPLECLICK_ADMIN_PASSWORD || ''
const GALLERY_PIN = process.env.CUMPLECLICK_DEMO_GALLERY_PIN || ''

mkdirSync(FLOW, { recursive: true })

const sleep = (ms) => new Promise((r) => setTimeout(r, ms))

async function snap(page, name) {
  await sleep(300)
  await page.screenshot({ path: `${OUT}\\${name}` })
  console.log(`  ✓ ${name}`)
}

async function snapFlow(page, name) {
  await sleep(200)
  await page.screenshot({ path: `${FLOW}\\${name}` })
}

async function getScreen(page) {
  return page.evaluate(() => {
    const el = document.querySelector('.screen')
    if (!el) return 'none'
    for (const c of el.classList) {
      if (c !== 'screen') return c
    }
    return 'screen-only'
  })
}

async function captureFullFlow(page, slug, prefix) {
  console.log(`\n=== ${slug} ===`)

  await page.goto(`${BASE}/?p=${slug}`, { waitUntil: 'networkidle2' })
  await sleep(1500)

  // 01 - Intro
  await snap(page, `${prefix}screen-01-intro.png`)

  // Click intro → invitados
  await page.evaluate(() => document.querySelector('.intro')?.click())
  await sleep(2000)
  await snap(page, `${prefix}screen-02-invitados.png`)

  // Pick first guest
  await page.waitForSelector('.invitado-item', { visible: true, timeout: 10000 })
  await page.click('.invitado-item')
  await sleep(6500)

  // Click spin
  try { await page.click('.spin-ready-popup .cta', { timeout: 5000 }) } catch {}

  // 03 - Ruleta (capture mid-spin)
  await sleep(800)
  await snap(page, `${prefix}screen-03-ruleta.png`)

  // Wait for spinner to finish
  await sleep(5500)

  // Screen after spinner — could be photo-session or video-personaje
  const screenAfterSpin = await getScreen(page)
  console.log(`  after spin: ${screenAfterSpin}`)

  // Skip through screens to get to capture
  let maxSkips = 4
  for (let i = 0; i < maxSkips; i++) {
    try {
      const btn = await page.$('.skip')
      if (btn) {
        await btn.click()
        await sleep(2500)
      } else break
    } catch { break }
  }
  await sleep(2000)

  // 04 - Personaje / character screen (try to capture wherever we are after skips)
  const screenNow = await getScreen(page)
  console.log(`  screen now: ${screenNow}`)
  await snap(page, `${prefix}screen-04-personaje.png`)

  // If we're still on video-screen, skip once more
  if (screenNow === 'video-screen' || screenNow === 'photo-session-screen') {
    try {
      const btn = await page.$('.skip')
      if (btn) { await btn.click(); await sleep(2500) }
    } catch {}
  }

  // 05 - Capture
  try {
    await page.waitForSelector('button.shutter', { visible: true, timeout: 10000 })
    await sleep(500)
    await snap(page, `${prefix}screen-05-captura.png`)
    await page.click('.shutter')
    await sleep(5000)
  } catch (e) { console.log(`  ! shutter: ${e.message}`) }

  // 06 - Preview
  try {
    await page.waitForSelector('.preview-img', { visible: true, timeout: 10000 })
    await sleep(500)
    await snap(page, `${prefix}screen-06-preview.png`)

    // Click Guardar
    await page.evaluate(() => {
      const buttons = document.querySelectorAll('.preview-bar button')
      for (const b of buttons) {
        if (b.textContent.includes('Guardar') && !b.disabled) { b.click(); return }
      }
    })
    await sleep(6000)
  } catch (e) { console.log(`  ! preview: ${e.message}`) }

  // 07 - QR
  await sleep(1000)
  await snap(page, `${prefix}screen-07-qr.png`)

  // Click Ver diploma
  try {
    await page.evaluate(() => {
      const buttons = document.querySelectorAll('button')
      for (const b of buttons) {
        if (b.textContent.includes('diploma') || b.textContent.includes('Diploma')) {
          b.click(); return
        }
      }
    })
    await sleep(3000)
  } catch {}

  // 08 - Diploma
  try {
    await page.waitForSelector('.diploma-img', { visible: true, timeout: 20000 })
    await sleep(1000)
    await snap(page, `${prefix}screen-08-diploma.png`)
    console.log('  ✓ diploma')
    // Download to get QR
    try { await page.click('.diploma-bar .cta'); await sleep(3000) } catch {}
  } catch {
    console.log('  ! no diploma')
    await snap(page, `${prefix}screen-08-diploma.png`)
  }

  // 09 - Diploma QR
  try { await page.waitForSelector('.diploma-qr__img', { visible: true, timeout: 10000 }); await sleep(500) } catch {}
  await snap(page, `${prefix}screen-09-diploma-qr.png`)

  console.log(`  ✓ ${slug} done`)
}

async function captureFlowFrames(page, slug, label) {
  console.log(`\n--- Flow frames: ${slug} (${label}) ---`)

  await page.goto(`${BASE}/?p=${slug}`, { waitUntil: 'networkidle2' })
  await sleep(1000)

  // Frame 1: Intro with background
  await snapFlow(page, `${slug}-01-intro.png`)

  // Click intro → invitados
  await page.evaluate(() => document.querySelector('.intro')?.click())
  await sleep(1500)
  await snapFlow(page, `${slug}-02-invitados.png`)

  // Pick first guest
  await page.waitForSelector('.invitado-item', { visible: true, timeout: 10000 })
  await page.click('.invitado-item')
  await sleep(6500)

  // Click spin
  try { await page.click('.spin-ready-popup .cta', { timeout: 5000 }) } catch {}

  // Capture several frames during spinner (characters visible!)
  await sleep(600)
  await snapFlow(page, `${slug}-03a-spinner-start.png`)
  await sleep(1200)
  await snapFlow(page, `${slug}-03b-spinner-mid.png`)
  await sleep(1600)
  await snapFlow(page, `${slug}-03c-spinner-end.png`)

  // Wait for character screen
  await sleep(3000)
  await snapFlow(page, `${slug}-04-character.png`)

  // Skip through
  for (let i = 0; i < 4; i++) {
    try {
      const btn = await page.$('.skip')
      if (btn) { await btn.click(); await sleep(2000) } else break
    } catch { break }
  }

  // Capture camera screen
  await sleep(1000)
  await snapFlow(page, `${slug}-05-camera.png`)

  // Click shutter
  try {
    await page.waitForSelector('button.shutter', { visible: true, timeout: 10000 })
    await page.click('.shutter')
    await sleep(5000)
  } catch {}

  // Preview
  try {
    await page.waitForSelector('.preview-img', { visible: true, timeout: 10000 })
    await sleep(500)
    await snapFlow(page, `${slug}-06-preview.png`)
    // Guardar
    await page.evaluate(() => {
      for (const b of document.querySelectorAll('.preview-bar button')) {
        if (b.textContent.includes('Guardar') && !b.disabled) { b.click(); return }
      }
    })
    await sleep(5000)
  } catch {}

  await snapFlow(page, `${slug}-07-qr.png`)
  await sleep(1000)

  // Diploma
  try {
    await page.evaluate(() => {
      for (const b of document.querySelectorAll('button')) {
        if (b.textContent.includes('diploma') || b.textContent.includes('Diploma')) {
          b.click(); return
        }
      }
    })
    await sleep(4000)
    await snapFlow(page, `${slug}-08-diploma.png`)
  } catch {}

  console.log(`  ✓ ${slug} flow done`)
}

async function captureGalleryAndAdmin(browser) {
  console.log('\n=== Gallery & Admin ===')
  const page = await browser.newPage()
  page.setDefaultTimeout(20000)

  // Set download path
  try {
    const client = await page.createCDPSession()
    await client.send('Page.setDownloadBehavior', {
      behavior: 'allow',
      downloadPath: 'C:\\Users\\luis_\\AppData\\Local\\Temp\\opencode\\downloads',
    })
  } catch {}

  await page.goto(`${BASE}/galeria.php?p=demo`, { waitUntil: 'networkidle2' })
  await sleep(1000)
  try {
    if (!GALLERY_PIN) throw new Error('CUMPLECLICK_DEMO_GALLERY_PIN no definido')
    await page.type('input[name="pin"]', GALLERY_PIN)
    await page.click('.btn')
    await sleep(2500)
  } catch {}
  await snap(page, 'screen-10-galeria.png')

  await page.goto(`${BASE}/admin/`, { waitUntil: 'networkidle2' })
  await sleep(1000)
  try {
    if (!ADMIN_PASSWORD) throw new Error('CUMPLECLICK_ADMIN_PASSWORD no definido')
    await page.type('#password', ADMIN_PASSWORD)
    await page.click('button[type="submit"]')
    await sleep(2500)
  } catch {}
  await snap(page, 'screen-11-admin.png')

  await page.close()
  console.log('  ✓ gallery & admin done')
}

async function main() {
  console.log('Launching Chrome for full capture...')
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: false,
    args: [
      '--use-fake-device-for-media-stream',
      '--use-fake-ui-for-media-stream',
      '--autoplay-policy=no-user-gesture-required',
      '--no-first-run',
    ],
    defaultViewport: { width: 768, height: 1024, deviceScaleFactor: 2 },
  })

  const page = await browser.newPage()
  page.setDefaultTimeout(30000)

  try {
    const client = await page.createCDPSession()
    await client.send('Page.setDownloadBehavior', {
      behavior: 'allow',
      downloadPath: 'C:\\Users\\luis_\\AppData\\Local\\Temp\\opencode\\downloads',
    })
  } catch {}

  try {
    // Party 1: Demo (familia-canina) — Bluey/Bingo characters
    await captureFullFlow(page, 'demo', '')

    // Party 2: Demo-tropical — Lilo/Stitch characters
    await captureFullFlow(page, 'demo-tropical', 'tropical-')

    // Flow frames with character visibility (only re-run demo + tropical, carreras already done)
    console.log('\n=== Capturing flow character frames ===')
    const p1 = await browser.newPage()
    p1.setDefaultTimeout(30000)
    await captureFlowFrames(p1, 'demo', 'familia-canina')
    await p1.close()

    const p2 = await browser.newPage()
    p2.setDefaultTimeout(30000)
    await captureFlowFrames(p2, 'demo-tropical', 'tropical')
    await p2.close()

    await captureGalleryAndAdmin(browser)

    console.log('\n✓ All captures done!')
  } finally {
    await browser.close()
  }
}

main().catch(e => {
  console.error('Fatal:', e.message)
  process.exit(1)
})
