import puppeteer from 'puppeteer-core'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const BASE = 'http://localhost/automatiza-tech/CumpleBooth/dist'
const OUT = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\screens'

const sleep = (ms) => new Promise((r) => setTimeout(r, ms))
async function snap(page, name) { await sleep(300); await page.screenshot({ path: `${OUT}\\${name}` }); console.log(`  snap ${name}`) }

async function trySpin(page, slug, targetNames) {
  await page.goto(`${BASE}/?p=${slug}`, { waitUntil: 'networkidle2' })
  await sleep(1200)
  await page.evaluate(() => document.querySelector('.intro')?.click())
  await sleep(1500)
  try {
    await page.waitForSelector('.invitado-item', { visible: true, timeout: 10000 })
    await page.click('.invitado-item')
  } catch { return null }
  await sleep(6400)
  try { await page.click('.spin-ready-popup .cta', { timeout: 4000 }) } catch { return null }

  // Wait for winner-name to appear (spinner 3.6s)
  try {
    await page.waitForSelector('.winner-name', { visible: true, timeout: 8000 })
    await sleep(100)
    const name = await page.$eval('.winner-name', el => el.textContent.trim())
    if (targetNames.includes(name)) return name
  } catch {}
  return null
}

async function captureTheme(browser, slug, targets, prefix) {
  console.log(`\n${slug}: targeting ${targets.join('|')}`)
  const page = await browser.newPage()
  page.setDefaultTimeout(20000)
  
  let winner = null
  for (let i = 0; i < 30; i++) {
    winner = await trySpin(page, slug, targets)
    if (winner) { console.log(`  GOT ${winner} on try ${i+1}`); break }
    if (i % 5 === 4) console.log(`  ...try ${i+1}`)
  }
  if (!winner) { console.log('  FAILED'); await page.close(); return }

  // Capture screenshots
  await snap(page, `${prefix}screen-03-ruleta.png`)
  await sleep(2000)
  
  // Skip to capture
  for (let i = 0; i < 6; i++) {
    try { const btn = await page.$('.skip'); if (btn) { await btn.click(); await sleep(2000) } else break } catch { break }
  }
  await sleep(1000)
  await snap(page, `${prefix}screen-04-personaje.png`)

  // Capture
  try {
    await page.waitForSelector('button.shutter', { visible: true, timeout: 8000 })
    await sleep(500)
    await snap(page, `${prefix}screen-05-captura.png`)
    await page.click('.shutter')
    await sleep(4500)
  } catch {}

  // Preview
  try {
    await page.waitForSelector('.preview-img', { visible: true, timeout: 10000 })
    await sleep(500)
    await snap(page, `${prefix}screen-06-preview.png`)
    await page.evaluate(() => {
      for (const b of document.querySelectorAll('.preview-bar button')) {
        if (b.textContent.includes('Guardar') && !b.disabled) { b.click(); return }
      }
    })
    await sleep(5000)
  } catch {}

  await snap(page, `${prefix}screen-07-qr.png`)
  
  try {
    await page.evaluate(() => {
      for (const b of document.querySelectorAll('button')) {
        if (b.textContent.includes('diploma') || b.textContent.includes('Diploma')) { b.click(); return }
      }
    })
    await sleep(4000)
    await page.waitForSelector('.diploma-img', { visible: true, timeout: 20000 })
    await sleep(500)
    await snap(page, `${prefix}screen-08-diploma.png`)
  } catch { await snap(page, `${prefix}screen-08-diploma.png`) }
  
  await snap(page, `${prefix}screen-09-diploma-qr.png`)
  await page.close()
}

async function main() {
  const browser = await puppeteer.launch({
    executablePath: CHROME, headless: false,
    args: ['--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream','--autoplay-policy=no-user-gesture-required'],
    defaultViewport: { width: 768, height: 1024, deviceScaleFactor: 2 },
  })

  await captureTheme(browser, 'demo-tropical', ['Stitch','Lilo'], 'tropical-')
  await captureTheme(browser, 'demo-carreras', ['Rayo McQueen'], 'carreras-')
  await captureTheme(browser, 'demo', ['Bluey','Bingo'], '')
  await browser.close()
  console.log('\nDone!')
}

main().catch(e => { console.error('Fatal:', e.message); process.exit(1) })
