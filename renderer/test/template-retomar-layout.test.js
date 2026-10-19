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
      for (const datos of [retomarEjemplo(), retomarEjemplo({ company_name: 'W'.repeat(120), client_name: 'W'.repeat(120), proyecto: 'W'.repeat(180), quedamos: 'W'.repeat(600), proponemos: 'W'.repeat(600), partir: 'W'.repeat(600), recomendacion: 'W'.repeat(300), demo_urls: Array.from({ length: 6 }, (_, i) => `https://example.com/demo${i}`) })]) {
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

test('el logo de la marca de agua mide lo mismo en la lámina de la demo que en las demás', async () => {
  const browser = await chromium.launch({ args: ['--disable-dev-shm-usage', '--no-sandbox'] });
  try {
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
    await page.route('**/*', (route) => route.abort());
    await page.setContent(renderRetomarHtml(retomarEjemplo({ demo_imagen: 'https://example.com/demo.png' })), { waitUntil: 'load' });
    await page.evaluate(() => { document.body.className = 'mode-list'; });
    const logos = await page.evaluate(() => [...document.querySelectorAll('.slide')].map((s) => {
      const r = s.querySelector('.at-watermark img').getBoundingClientRect();
      return [Math.round(r.width), Math.round(r.height)];
    }));
    assert.equal(logos.length, 6);
    assert.ok(logos.every(([w, h]) => w === logos[0][0] && h === logos[0][1]), `el logo mide igual en las seis láminas: ${JSON.stringify(logos)}`);
    assert.ok(logos[2][1] < 200, 'y en la lámina de la demo no se estira a la altura de la imagen (600 px)');
  } finally { await browser.close(); }
});

test('en Chromium las fotos de fondo quedan ancladas arriba y el acercamiento lento no recorta la parte de arriba', async () => {
  const browser = await chromium.launch({ args: ['--disable-dev-shm-usage', '--no-sandbox'] });
  try {
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
    await page.route('**/*', (route) => route.abort());
    await page.setContent(renderRetomarHtml(retomarEjemplo(), { portada: 'img/p.jpg', quedamos: 'img/q.jpg', cierre: 'img/c.jpg' }), { waitUntil: 'load' });
    const fondos = await page.evaluate(() => [...document.querySelectorAll('.slide')].map((s) => {
      const cs = getComputedStyle(s.querySelector('.slide-bg'));
      return { foto: cs.backgroundImage.includes('url('), pos: cs.backgroundPositionY, origen: cs.transformOrigin };
    }));
    for (const n of [0, 1, 5]) {
      assert.ok(fondos[n].foto, `lámina ${n + 1} con foto`);
      assert.match(fondos[n].pos, /^0%(, 0%)*$/, `lámina ${n + 1}: foto (y su degradado) anclada arriba`);
      assert.match(fondos[n].origen, /^(960px 0px|50% 0%)$/, `lámina ${n + 1}: el acercamiento crece desde arriba`);
    }
  } finally { await browser.close(); }
});
