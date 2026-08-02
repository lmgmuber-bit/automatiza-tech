import puppeteer from 'puppeteer-core'
import { mkdirSync } from 'fs'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const BASE = 'http://localhost/automatiza-tech/CumpleBooth/dist'
const OUT = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\video-frozen'
const FAKE_CAM = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\explicativo\\fakecam\\nino.y4m'

mkdirSync(OUT, { recursive: true })
const sleep = ms => new Promise(r => setTimeout(r, ms))

async function main() {
  const browser = await puppeteer.launch({
    executablePath: CHROME, headless: false,
    defaultViewport: { width: 768, height: 1024, deviceScaleFactor: 1 },
    args: [
      '--window-position=0,0', '--window-size=786,1080',
      '--use-fake-device-for-media-stream',
      `--use-file-for-fake-video-capture=${FAKE_CAM}`,
      '--use-fake-ui-for-media-stream',
      '--autoplay-policy=no-user-gesture-required', '--no-first-run',
    ],
  })

  const page = await browser.newPage()

  console.log('=== DEBUG INVITADOS ===')
  await page.goto(`${BASE}/?p=demo-hielo`, { waitUntil: 'networkidle2' })
  await sleep(2000)

  // Click intro
  await page.evaluate(() => document.querySelector('.intro')?.click())
  await sleep(2500)
  await page.screenshot({ path: `${OUT}\\debug-01-invitados.png` })
  console.log('Screenshot: invitados screen')

  // Check invitado items
  const items = await page.$$('.invitado-item')
  console.log(`Found ${items.length} invitado items`)

  // Check HTML structure
  const html = await page.evaluate(() => {
    const first = document.querySelector('.invitado-item')
    return first ? first.outerHTML.substring(0, 300) : 'NOT FOUND'
  })
  console.log('First invitado-item:', html)

  // Try clicking first item
  console.log('Clicking first invitado-item...')
  await page.click('.invitado-item')
  await sleep(1000)
  await page.screenshot({ path: `${OUT}\\debug-02-after-click.png` })
  console.log('Screenshot after click')

  // Check if popup appeared
  const popup = await page.$('.spin-ready-popup')
  console.log(`spin-ready-popup found: ${!!popup}`)

  // Check what buttons exist
  const buttons = await page.evaluate(() => {
    return [...document.querySelectorAll('button')].map(b => ({
      text: b.textContent.trim().substring(0, 60),
      class: b.className.substring(0, 60)
    }))
  })
  console.log('Buttons:', JSON.stringify(buttons, null, 2))

  // Try alternate approach: click first girl using evaluate
  console.log('\nTrying evaluate-based click...')
  const result = await page.evaluate(() => {
    const nodos = [...document.querySelectorAll('.invitados-group, .invitado-item')]
    let grupoActual = null
    for (const n of nodos) {
      if (n.classList.contains('invitados-group')) {
        grupoActual = /varon/i.test(n.textContent) ? 'varon' : 'nina'
        continue
      }
      if (grupoActual === 'nina') {
        n.click()
        return { name: n.textContent.trim(), group: grupoActual }
      }
    }
    return null
  })
  console.log('Selected:', result)

  await sleep(2000)
  await page.screenshot({ path: `${OUT}\\debug-03-after-eval-click.png` })
  console.log('Screenshot after eval click')

  const popup2 = await page.$('.spin-ready-popup')
  console.log(`spin-ready-popup found: ${!!popup2}`)

  // Wait longer and check again
  await sleep(4000)
  await page.screenshot({ path: `${OUT}\\debug-04-after-wait.png` })

  const popup3 = await page.$('.spin-ready-popup')
  console.log(`spin-ready-popup after wait: ${!!popup3}`)

  const buttons2 = await page.evaluate(() => {
    return [...document.querySelectorAll('button')].map(b => ({
      text: b.textContent.trim().substring(0, 60)
    }))
  })
  console.log('Buttons after wait:', JSON.stringify(buttons2))

  if (popup3) {
    try {
      await page.click('.spin-ready-popup .cta')
      console.log('Clicked spin CTA!')
      await sleep(2000)
      await page.screenshot({ path: `${OUT}\\debug-05-spinning.png` })
    } catch (e) { console.log('Spin click error:', e.message) }
  }

  await browser.close()
  console.log('\nDone.')
}

main().catch(e => { console.error(e); process.exit(1) })
