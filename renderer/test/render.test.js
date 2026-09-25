const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { renderToFiles } = require('../src/render');

test('renders a minimal html string to an html file and a non-empty pdf', async () => {
  const outputDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-test-'));
  const html = '<!doctype html><html><body><h1>Hola</h1></body></html>';
  const { htmlPath, pdfPath } = await renderToFiles(html, outputDir);

  const htmlContent = await fs.readFile(htmlPath, 'utf8');
  assert.ok(htmlContent.includes('Hola'));

  const pdfStat = await fs.stat(pdfPath);
  assert.ok(pdfStat.size > 1000, `expected a real pdf, got ${pdfStat.size} bytes`);
});
