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

async function main() {
  console.log('Launching Chrome...')
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: false,
    args: [
      '--use-fake-device-for-media-stream',
      '--use-fake-ui-for-media-stream',
      '--autoplay-policy=no-user-gesture-required',
    ],
    defaultViewport: { width: 768, height: 1024, deviceScaleFactor: 2 },
  })

  const page = await browser.newPage()
  page.setDefaultTimeout(30000)

  // Enable auto-downloads to temp dir
  const downloadPath = 'C:\\Users\\luis_\\AppData\\Local\\Temp\\opencode\\downloads'
  try {
    const client = await page.createCDPSession()
    await client.send('Page.setDownloadBehavior', {
      behavior: 'allow',
      downloadPath: downloadPath,
    })
  } catch {}

  try {
    await page.goto(`${BASE}/?p=demo`, { waitUntil: 'networkidle2' })
    await sleep(1500)

    // Click intro
    await page.evaluate(() => { document.querySelector('.intro')?.click() })
    await sleep(2000)

    // Pick Ana
    await page.waitForSelector('.invitado-item', { visible: true, timeout: 10000 })
    await page.click('.invitado-item')
    await sleep(6500)

    // Click spin
    try { await page.click('.spin-ready-popup .cta') } catch {}
    await sleep(5500)

    // Skip through screens — important: skip photo-session AND video-personaje
    for (let i = 0; i < 4; i++) {
      try {
        const btn = await page.$('.skip')
        if (btn) {
          await btn.click()
          await sleep(2500)
        } else break
      } catch { break }
    }
    await sleep(2000)

    // Capture - click shutter
    try {
      await page.waitForSelector('button.shutter', { visible: true, timeout: 10000 })
      await sleep(500)
      await page.click('.shutter')
      // Countdown: 3×800ms = 2400ms, then doCapture
      await sleep(5000)
    } catch (e) { console.log('shutter fail:', e.message) }

    // Preview - wait for composed image, then click Guardar
    try {
      await page.waitForSelector('.preview-img', { visible: true, timeout: 10000 })
      await sleep(500)

      // Click Guardar using page.evaluate (bypasses download dialog somewhat)
      await page.evaluate(() => {
        const buttons = document.querySelectorAll('.preview-bar button')
        for (const b of buttons) {
          if (b.textContent.includes('Guardar') && !b.disabled) {
            b.click()
            return
          }
        }
      })
      console.log('Clicked Guardar')
    } catch (e) { console.log('preview fail:', e.message) }

    // Wait for transition to QR — after confetti (2.6s) + onSave delay (1.6s) + render
    await sleep(6000)

    // Check if we landed on QR
    let onQr = false
    try {
      await page.waitForSelector('.qr-screen', { visible: true, timeout: 10000 })
      onQr = true
      console.log('On QR screen')
    } catch {
      console.log('Not on QR, trying to find current screen')
      const cls = await page.evaluate(() => document.querySelector('.screen')?.className || 'none')
      console.log('Current screen:', cls)
    }

    // Capture QR
    await sleep(1000)
    await screenshot(page, 'screen-07-qr.png')

    if (onQr) {
      // Click "Ver diploma"
      await page.evaluate(() => {
        const buttons = document.querySelectorAll('.qr-actions button')
        for (const b of buttons) {
          if (b.textContent.includes('diploma') || b.textContent.includes('Diploma')) {
            b.click()
            return
          }
        }
      })
      await sleep(3000)
    }

    // Check for diploma
    try {
      await page.waitForSelector('.diploma-img', { visible: true, timeout: 20000 })
      await sleep(1000)
      await screenshot(page, 'screen-08-diploma.png')
      console.log('Diploma captured!')
    } catch {
      console.log('No diploma rendered, capturing current state')
      await screenshot(page, 'screen-08-diploma.png')
    }

    // Trigger diploma QR
    try {
      await page.click('.diploma-bar .cta')
      await sleep(4000)
      try {
        await page.waitForSelector('.diploma-qr__img', { visible: true, timeout: 15000 })
        await sleep(500)
      } catch {}
    } catch {}
    await screenshot(page, 'screen-09-diploma-qr.png')

  } finally {
    await browser.close()
  }
  console.log('\nDone!')
}

main().catch((e) => {
  console.error('Fatal:', e.message)
  process.exit(1)
})
