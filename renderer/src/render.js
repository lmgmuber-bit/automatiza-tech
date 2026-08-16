const fs = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');

async function renderToFiles(html, outputDir) {
  await fs.mkdir(outputDir, { recursive: true });
  const htmlPath = path.join(outputDir, 'index.html');
  const pdfPath = path.join(outputDir, 'presentation.pdf');
  await fs.writeFile(htmlPath, html, 'utf8');

  // --disable-dev-shm-usage: Docker containers default to a 64MB /dev/shm,
  // far too small for Chromium's shared memory needs; without this flag
  // Chromium crashes/hangs under real container memory limits (this never
  // surfaces on a local Windows/Mac dev machine, only in the deployed
  // Linux container). --no-sandbox: this Dockerfile runs as root (no USER
  // directive), and Chromium's sandbox refuses to initialize as root
  // without either a dedicated non-root user or this flag.
  const browser = await chromium.launch({
    args: ['--disable-dev-shm-usage', '--no-sandbox'],
  });
  try {
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
    await page.goto(`file://${htmlPath}`, { waitUntil: 'networkidle' });
    await page.pdf({
      path: pdfPath,
      width: '1920px',
      height: '1080px',
      printBackground: true,
    });
  } finally {
    await browser.close();
  }

  return { htmlPath, pdfPath };
}

module.exports = { renderToFiles };
