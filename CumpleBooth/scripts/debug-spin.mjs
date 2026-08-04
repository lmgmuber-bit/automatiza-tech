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

  console.log('Loading demo-hielo...')
  await page.goto(`${BASE}/?p=demo-hielo`, { waitUntil: 'networkidle2' })
  await sleep(2000)
  
  // Click intro
  console.log('Click intro...')
  await page.evaluate(() => document.querySelector('.intro')?.click())
  await sleep(2000)

  // Click first guest
  console.log('Click first guest...')
  await page.waitForSelector('.invitado-item', { visible: true, timeout: 10000 })
  await page.click('.invitado-item')
  
  // Wait for welcome popup
  console.log('Wait for welcome popup...')
  try {
    await page.waitForSelector('.welcome-popup', { visible: true, timeout: 10000 })
    console.log('  Welcome popup found')
    await page.screenshot({ path: `${OUT}\\debug-welcome.png` })
    await page.click('.welcome-popup')
    await sleep(1000)
  } catch (e) {
    console.log(`  No welcome popup: ${e.message}`)
  }

  // Check state
  console.log('Check for spin-ready...')
  const spinReady = await page.$('.spin-ready-popup')
  console.log(`  spin-ready-popup: ${!!spinReady}`)

  if (spinReady) {
    const nameText = await page.evaluate(() => {
      const h2 = document.querySelector('.spin-ready-popup h2')
      return h2 ? h2.textContent : 'n/a'
    })
    console.log(`  Text: ${nameText}`)
    
    try {
      await page.click('.spin-ready-popup .cta')
      console.log('  Clicked spin!')
    } catch (e) { console.log(`  Error: ${e.message}`) }
  }

  // Wait and see what screen we're on
  await sleep(8000)
  
  const screenClass = await page.evaluate(() => {
    const el = document.querySelector('.screen')
    return el ? el.className : 'none'
  })
  console.log(`\nScreen class: ${screenClass}`)

  await page.screenshot({ path: `${OUT}\\debug-after-spin.png` })
  console.log('Screenshot saved')

  // Find all headings and text
  const texts = await page.evaluate(() => {
    return [...document.querySelectorAll('h1, h2, h3, p')].map(el => ({
      tag: el.tagName,
      text: el.textContent.trim().substring(0, 80),
      class: el.className?.substring?.(0, 40) || ''
    }))
  })
  console.log('\nTexts on screen:')
  for (const t of texts.slice(0, 20)) {
    console.log(`  ${t.tag} .${t.class}: "${t.text}"`)
  }

  // Check for video element
  const videoInfo = await page.evaluate(() => {
    const v = document.querySelector('video')
    if (!v) return 'no video'
    return { src: v.src?.substring(v.src.length - 40), currentTime: v.currentTime, duration: v.duration }
  })
  console.log(`\nVideo: ${JSON.stringify(videoInfo)}`)

  // Check for character name specifically
  const charFromState = await page.evaluate(() => {
    // Try to find character from any React state visible in DOM
    const all = document.body.textContent
    const names = ['Olaf', 'Elsa', 'Anna', 'Kristoff', 'Sven', 'Bruni']
    for (const n of names) {
      if (all.includes(n)) return n
    }
    return 'none found'
  })
  console.log(`\nCharacter from body text: ${charFromState}`)

  await browser.close()
  console.log('\nDone.')
}

main().catch(e => { console.error(e); process.exit(1) })
