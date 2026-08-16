const fs = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');

async function renderToFiles(html, outputDir) {
  await fs.mkdir(outputDir, { recursive: true });
  const htmlPath = path.join(outputDir, 'index.html');
  const pdfPath = path.join(outputDir, 'presentation.pdf');
  await fs.writeFile(htmlPath, html, 'utf8');

  const browser = await chromium.launch();
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
