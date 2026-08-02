import puppeteer from 'puppeteer-core'
import { spawn, execSync } from 'child_process'
import { mkdirSync, existsSync } from 'fs'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const BASE = 'http://localhost/automatiza-tech/CumpleBooth/dist'
const OUT = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\video-frozen'
const FAKE_CAM = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\explicativo\\fakecam\\nino.y4m'

mkdirSync(OUT, { recursive: true })
const sleep = ms => new Promise(r => setTimeout(r, ms))

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

async function main() {
  console.log('=== TEST GDIGRAB ===')

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

  try {
    // 1. Load party
    console.log('1. Loading demo-hielo...')
    await page.goto(`${BASE}/?p=demo-hielo`, { waitUntil: 'networkidle2' })
    await sleep(2000)
    console.log(`   Screen: ${await getScreen(page)}`)

    // 2. Click intro
    console.log('2. Clicking intro...')
    await page.evaluate(() => document.querySelector('.intro')?.click())
    await sleep(2000)
    console.log(`   Screen: ${await getScreen(page)}`)

    // 3. Click first guest
    console.log('3. Clicking first guest...')
    await page.waitForSelector('.invitado-item', { visible: true, timeout: 10000 })
    await page.click('.invitado-item')

    // Wait for welcome popup to appear, then click to skip it
    console.log('4. Waiting for welcome popup...')
    try {
      await page.waitForSelector('.welcome-popup', { visible: true, timeout: 8000 })
      console.log('   Welcome popup found, clicking to skip...')
      await page.click('.welcome-popup')
      await sleep(1000)
    } catch {
      console.log('   No welcome popup (timed out or already done)')
    }

    // 5. Wait for spin-ready popup
    console.log('5. Waiting for spin-ready popup...')
    try {
      await page.waitForSelector('.spin-ready-popup .cta', { visible: true, timeout: 10000 })
      console.log('   Found! Clicking spin CTA...')
      await page.click('.spin-ready-popup .cta')
    } catch (e) {
      console.log(`   Timeout: ${e.message}`)
      // Try fallback: maybe already on spinner
      const scr = await getScreen(page)
      console.log(`   Current screen: ${scr}`)
      if (scr !== 'spinner') {
        // Try clicking any CTA button
        try { await page.click('button.cta'); await sleep(1000) } catch {}
      }
    }

    // 6. Wait for spin to finish + character reveal
    await sleep(8000)
    let screen = await getScreen(page)
    console.log(`6. After spin: ${screen}`)

    // 7. Skip through video-personaje to reach game
    console.log('7. Skipping to game...')
    for (let i = 0; i < 5; i++) {
      screen = await getScreen(page)
      if (screen === 'juego' || screen === 'juego--muneco') {
        console.log(`   Game screen reached (attempt ${i})`)
        break
      }
      try {
        const btn = await page.$('.skip')
        if (btn) { await btn.click(); await sleep(2500) }
        else break
      } catch { break }
    }

    // 8. Navigate through games to copos
    console.log('8. Navigating to copos...')
    for (let g = 0; g < 5; g++) {
      screen = await getScreen(page)
      const hasCampo = await page.$('.juego-campo')
      const hasPuzzle = await page.$('.puzzle-tablero')
      const hasMuneco = await page.$('.mp-stage')
      const hasOferta = await page.$('.juego-oferta')

      if (hasCampo) { console.log('   *** COPOS FOUND ***'); break }

      if (hasOferta) {
        console.log('   Bonus offer → "Sí, jugar"')
        try { await page.click('.juego-oferta__btn--si'); await sleep(2000); continue } catch {}
        try { await page.click('.cta'); await sleep(2000); continue } catch {}
      }

      if (hasPuzzle || hasMuneco) {
        const kind = hasPuzzle ? 'fichas' : 'armar-muneco'
        console.log(`   Skipping ${kind}...`)
        try { await page.click('.juego-skip'); await sleep(2500); continue } catch {}
      }

      // Generic advance
      try { const s = await page.$('.skip'); if (s) { await s.click(); await sleep(2500); continue } } catch {}
      try { const c = await page.$('button.cta'); if (c) { await c.click(); await sleep(2500); continue } } catch {}

      console.log(`   Stuck on ${screen}, waiting...`)
      await sleep(2000)
    }

    // 9. Start gdigrab if copos found
    const hasCampo = await page.$('.juego-campo')
    if (!hasCampo) {
      screen = await getScreen(page)
      console.log(`   ❌ Not on copos. Screen: ${screen}`)
      await browser.close()
      return
    }

    console.log('\n9. Starting gdigrab (5s)...')
    const videoPath = `${OUT}\\test-gdigrab.mp4`
    const ffmpeg = spawn('ffmpeg', [
      '-f', 'gdigrab', '-framerate', '30',
      '-offset_x', '0', '-offset_y', '0',
      '-video_size', '786x1080', '-i', 'desktop',
      '-t', '5', '-c:v', 'libx264', '-preset', 'ultrafast',
      '-pix_fmt', 'yuv420p', '-y', videoPath,
    ], { stdio: 'pipe' })

    await sleep(7000)
    ffmpeg.kill('SIGINT')
    await sleep(1500)

    if (existsSync(videoPath)) {
      const result = execSync(
        `ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "${videoPath}"`,
        { encoding: 'utf8' }
      )
      const dur = parseFloat(result.trim())
      console.log(`\n   Duration: ${dur.toFixed(2)}s (expected ~5s)`)
      console.log(dur >= 3.5 && dur <= 7 ? '   ✅ GDIGRAB WORKS!' : '   ⚠️ Check manually')
    } else {
      console.log('   ❌ No video created')
    }

  } finally {
    await browser.close()
    console.log('\nDone.')
  }
}

main().catch(e => { console.error(e); process.exit(1) })
