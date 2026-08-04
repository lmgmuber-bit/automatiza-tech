import puppeteer from 'puppeteer-core'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const HTML = 'file:///C:/wamp64/www/automatiza-tech/CumpleBooth/design/explicativo/src/info-01.html'
const OUT = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\explicativo\\info-01-como-funciona.png'

async function main() {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--allow-file-access-from-files'],
    defaultViewport: { width: 1080, height: 1350, deviceScaleFactor: 2 },
  })

  const page = await browser.newPage()
  await page.goto(HTML, { waitUntil: 'networkidle0', timeout: 15000 })
  await new Promise(r => setTimeout(r, 2000)) // wait for fonts/images to load
  await page.screenshot({ path: OUT, type: 'png' })
  console.log('Rendered to', OUT)

  await browser.close()
}

main().catch(e => {
  console.error('Fatal:', e.message)
  process.exit(1)
})
