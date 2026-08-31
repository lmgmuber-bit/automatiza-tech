import puppeteer from 'puppeteer-core'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const BASE = 'http://localhost/automatiza-tech/CumpleBooth/dist'
const OUT = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\screens'

const sleep = (ms) => new Promise((r) => setTimeout(r, ms))

async function screenshot(page, name) {
  await sleep(400)
  await page.screenshot({ path: `${OUT}\\${name}` })
  console.log(`  ✓ ${name}`)
}

async function waitThenClick(page, selector, timeout = 15000) {
  await page.waitForSelector(selector, { visible: true, timeout })
  await sleep(300)
  await page.click(selector)
  await sleep(400)
}

async function waitAndClickAny(page, selectors, timeout = 15000) {
  for (const sel of selectors) {
    try {
      await page.waitForSelector(sel, { visible: true, timeout })
      await sleep(200)
      await page.click(sel)
      await sleep(400)
      return true
    } catch {}
  }
  return false
}

async function captureDemoFamily(browser) {
  console.log('\n--- Demo (familia-canina) ---')
  const page = await browser.newPage()
  page.setDefaultTimeout(20000)
  await page.goto(`${BASE}/?p=demo`, { waitUntil: 'networkidle2' })
  await sleep(1500)
  await screenshot(page, 'screen-01-intro.png')

  // Intro → invitados: click anywhere on intro section
  await page.evaluate(() => {
    const el = document.querySelector('.intro')
    if (el) el.click()
  })
  await sleep(2000)
  await screenshot(page, 'screen-02-invitados.png')

  // Pick first guest "Ana"
  await waitThenClick(page, '.invitado-item')
  await sleep(6500) // welcome video + animation
  // Click "Toca para girar"
  try {
    await waitThenClick(page, '.spin-ready-popup .cta', 5000)
  } catch {}
  await sleep(800)
  await screenshot(page, 'screen-03-ruleta.png')

  // Wait for spinner to finish (3.6s spin + 1.6s winner)
  await sleep(5500)

  // Skip through photo-session and/or video-personaje
  for (let i = 0; i < 3; i++) {
    try {
      const btn = await page.$('.skip')
      if (btn) { 
        await btn.click()
        await sleep(2500)
      } else break
    } catch { break }
  }

  // Character/fallback screen
  await screenshot(page, 'screen-04-personaje.png')

  // Capture screen
  try {
    await page.waitForSelector('button.shutter', { visible: true, timeout: 20000 })
    await sleep(1000)
    await screenshot(page, 'screen-05-captura.png')
    await page.click('.shutter')
    console.log('  shutter clicked, waiting for countdown...')
    await sleep(4000) // countdown 3×800ms + capture
  } catch (e) {
    console.log(`  ! Capture: ${e.message}`)
    await screenshot(page, 'screen-05-captura.png')
  }

  // Preview
  try {
    await page.waitForSelector('.preview-img', { visible: true, timeout: 10000 })
    await sleep(1000)
    await screenshot(page, 'screen-06-preview.png')

    // Click Guardar
    await page.waitForSelector('.preview-bar .cta:not([disabled])', { visible: true, timeout: 5000 })
    await page.click('.preview-bar .cta')
    await sleep(2500)
  } catch (e) {
    console.log(`  ! Preview: ${e.message}`)
    await screenshot(page, 'screen-06-preview.png')
  }

  // QR — skip for now since it needs the upload endpoint
  // Navigate directly to diploma by skipping QR
  try {
    // Try clicking "Siguiente invitado" or "Ver diploma"
    await waitAndClickAny(page, ['button.cta', '.qr-actions button.cta'], 5000)
    await sleep(2000)
    await screenshot(page, 'screen-07-qr.png')
  } catch {
    await screenshot(page, 'screen-07-qr.png')
  }

  // Diploma
  try {
    // Another click for diploma
    await waitAndClickAny(page, ['button.cta'], 5000)
    await sleep(3000)
  } catch {}
  try {
    await page.waitForSelector('.diploma-img', { visible: true, timeout: 10000 })
    await sleep(1000)
    await screenshot(page, 'screen-08-diploma.png')
    // Click descargar to trigger QR
    const dlBtn = await page.$('.diploma-bar .cta')
    if (dlBtn) { await dlBtn.click(); await sleep(3000) }
  } catch { console.log('  ! Diploma render issue') }
  await screenshot(page, 'screen-09-diploma-qr.png')
  await page.close()
}

async function captureTropicalScreenshots(browser) {
  console.log('\n--- Demo (tropical) ---')
  const page = await browser.newPage()
  page.setDefaultTimeout(20000)

  await page.goto(`${BASE}/?p=demo-tropical`, { waitUntil: 'domcontentloaded', timeout: 15000 })
  await sleep(3000)
  await screenshot(page, 'screen-01t-intro.png')

  // Intro → invitados
  await page.evaluate(() => {
    const el = document.querySelector('.intro')
    if (el) el.click()
  })
  await sleep(2000)

  // Pick first guest
  try {
    await page.waitForSelector('.invitado-item', { visible: true, timeout: 8000 })
    await page.click('.invitado-item')
    await sleep(6500)
  } catch { console.log('  ! Guest pick failed') }

  // Spin
  try { await waitThenClick(page, '.spin-ready-popup .cta', 5000) } catch {}
  await sleep(800)
  await screenshot(page, 'screen-03t-ruleta.png')
  await sleep(5500)

  // Skip screens
  for (let i = 0; i < 3; i++) {
    try {
      const btn = await page.$('.skip')
      if (btn) { await btn.click(); await sleep(2500) } else break
    } catch { break }
  }
  await sleep(2000)

  // Capture
  try {
    await page.waitForSelector('button.shutter', { visible: true, timeout: 15000 })
    await sleep(500)
    await screenshot(page, 'screen-05t-captura.png')
    await page.click('.shutter')
    await sleep(4000)
  } catch { console.log('  ! Tropical capture') }

  // Preview
  try {
    await page.waitForSelector('.preview-img', { visible: true, timeout: 10000 })
    await sleep(500)
    await screenshot(page, 'screen-06t-preview.png')
  } catch { console.log('  ! Tropical preview') }

  await page.close()
  console.log('  ✓ Tropical done')
}

async function captureGalleryAdmin(browser) {
  console.log('\n--- Gallery & Admin ---')
  const page = await browser.newPage()
  page.setDefaultTimeout(20000)

  await page.goto(`${BASE}/galeria.php?p=demo`, { waitUntil: 'networkidle2' })
  await sleep(1500)
  try {
    await page.type('input[name="pin"]', '2026')
    await page.click('.btn')
    await sleep(2000)
  } catch {}
  await screenshot(page, 'screen-10-galeria.png')

  await page.goto(`${BASE}/admin/`, { waitUntil: 'networkidle2' })
  await sleep(1500)
  try {
    await page.type('#password', 'booth2026')
    await page.click('button[type="submit"]')
    await sleep(2500)
  } catch {}
  await screenshot(page, 'screen-11-admin.png')

  await page.close()
  console.log('  ✓ Gallery & Admin done')
}

async function main() {
  console.log('Launching Chrome...')
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

  try {
    await captureDemoFamily(browser)
    await captureTropicalScreenshots(browser)
    await captureGalleryAdmin(browser)
    console.log('\n✓ All screenshots done!')
  } finally {
    await browser.close()
  }
}

main().catch((e) => {
  console.error('Fatal:', e.message)
  process.exit(1)
})
