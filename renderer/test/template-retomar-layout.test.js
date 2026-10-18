const test = require('node:test');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const { renderRetomarHtml } = require('../src/template-retomar');
const { retomarEjemplo } = require('./fixtures/retomar-ejemplo');

test('seis láminas caben en Chromium 1920×1080, pantalla e impresión, sin red externa ni temporales', async () => {
  const browser = await chromium.launch({ args: ['--disable-dev-shm-usage', '--no-sandbox'] });
  try {
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
    await page.route('**/*', (route) => route.abort());
    for (const media of ['screen', 'print']) {
      for (const datos of [retomarEjemplo(), retomarEjemplo({ company_name: 'W'.repeat(120), client_name: 'W'.repeat(120), proyecto: 'W'.repeat(180), quedamos: 'W'.repeat(600), proponemos: 'W'.repeat(600), partir: 'W'.repeat(600), demo_urls: Array.from({ length: 6 }, (_, i) => `https://example.com/demo${i}`) })]) {
      await page.emulateMedia({ media });
      await page.setContent(renderRetomarHtml(datos), { waitUntil: 'load' });
      await page.evaluate(() => { document.body.className = 'mode-list'; document.documentElement.style.setProperty('--zoom', '1'); });
      const m = await page.evaluate(() => {
        const fuera = []; const slides = [...document.querySelectorAll('.slide')];
        slides.forEach((slide, n) => {
          const s = slide.getBoundingClientRect();
          slide.querySelectorAll('*').forEach((el) => {
            if (el.closest('.slide-bg, .at-watermark')) return;
            const r = el.getBoundingClientRect();
            if ((!r.width && !r.height) || r.height >= s.height - 1) return;
            if (r.bottom > s.bottom - 20 || r.top < s.top - 1 || r.left < s.left - 1 || r.right > s.right + 1) fuera.push(`${n + 1}: ${el.tagName}.${el.className}`);
            if (el.scrollHeight > el.clientHeight + 1 && getComputedStyle(el).overflow === 'hidden' && ['H1', 'H2', 'P'].includes(el.tagName)) fuera.push(`${n + 1}: texto recortado`);
          });
        });
        return { count: slides.length, fuera };
      });
      assert.equal(m.count, 6); assert.deepEqual(m.fuera, [], media);
      }
    }
  } finally { await browser.close(); }
});
