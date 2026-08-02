import puppeteer from 'puppeteer-core'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const SRC = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\explicativo\\src'
const OUT = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\explicativo'

const pieces = [
  { html: 'info-01.html', out: 'info-01-como-funciona.png' },
  { html: 'info-02.html', out: 'info-02-que-se-lleva.png' },
  { html: 'info-03.html', out: 'info-03-planes.png' },
  { html: 'carrusel-01.html', out: 'carrusel-01.png' },
  { html: 'carrusel-02.html', out: 'carrusel-02.png' },
  { html: 'carrusel-03.html', out: 'carrusel-03.png' },
  { html: 'carrusel-04.html', out: 'carrusel-04.png' },
  { html: 'carrusel-05.html', out: 'carrusel-05.png' },
  { html: 'carrusel-06.html', out: 'carrusel-06.png' },
]

async function render(browser, html, out) {
  const page = await browser.newPage()
  await page.goto(`file:///${SRC.replace(/\\/g, '/')}/${html}`, { waitUntil: 'networkidle0', timeout: 15000 })
  await new Promise(r => setTimeout(r, 2000))
  await page.screenshot({ path: `${OUT}\\${out}`, type: 'png' })
  await page.close()
  console.log(`  ✓ ${out}`)
}

async function main() {
  console.log(`Rendering ${pieces.length} pieces...`)
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--allow-file-access-from-files'],
    defaultViewport: { width: 1080, height: 1350, deviceScaleFactor: 2 },
  })

  for (const p of pieces) {
    await render(browser, p.html, p.out)
  }

  await browser.close()
  console.log('\n✓ All rendered!')
}

main().catch(e => { console.error('Fatal:', e.message); process.exit(1) })
