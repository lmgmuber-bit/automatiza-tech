const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { chromium } = require('playwright');
const { renderEstadoHtml } = require('../src/template-estado');
const { renderToFiles } = require('../src/render');
const { estadoCorto, estadoLargo } = require('./fixtures/estado-ejemplo');

// Medido en Chromium de verdad a 1920×1080 y en modo impresión (lo que ve el PDF), igual que el plan
// (template-plan-layout.test.js): ningún elemento de una lámina termina a menos de 20 px del borde de abajo ni se sale
// por los lados, con el ejemplo corto y con el peor caso que acepta WordPress.
async function medir(datos) {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-estado-layout-'));
  const archivo = path.join(dir, 'index.html');
  await fs.writeFile(archivo, renderEstadoHtml(datos, {}), 'utf8');
  const browser = await chromium.launch({ args: ['--disable-dev-shm-usage', '--no-sandbox'] });
  try {
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
    await page.goto(pathToFileURL(archivo).href, { waitUntil: 'load' });
    await page.emulateMedia({ media: 'print' });
    return await page.evaluate(() => {
      const fuera = [];
      const slides = document.querySelectorAll('.slide');
      slides.forEach((slide, n) => {
        const s = slide.getBoundingClientRect();
        slide.querySelectorAll('*').forEach((el) => {
          if (el.closest('.slide-bg') || el.closest('.at-watermark')) return;
          // Lo que una caja recorta a propósito (overflow hidden + line-clamp) no se ve: se mide la caja, no su contenido.
          const recorte = el.parentElement && el.parentElement.closest('.hito-col, .ev, .cambio, .alcance > div, .sigue > div, .estado-bloque');
          if (recorte) return;
          const r = el.getBoundingClientRect();
          if ((!r.width && !r.height) || r.height >= s.height - 1) return;
          if (r.bottom > s.bottom - 20 || r.top < s.top - 1 || r.left < s.left - 1 || r.right > s.right + 1) {
            fuera.push(`lámina ${n + 1}: ${el.tagName.toLowerCase()}.${String(el.className)} termina a ${Math.round(r.bottom - s.top)} px`);
          }
        });
      });
      // En cada lámina de cierre, las tres columnas terminan antes de las tarjetas de evidencia (y el título antes de las columnas).
      const pisan = [...document.querySelectorAll('.estado-hito')].filter((s) => {
        const cab = s.querySelector('.panel-head').getBoundingClientRect();
        const cols = s.querySelector('.hito-cols').getBoundingClientRect();
        const evs = s.querySelector('.hito-evidencias');
        return cab.bottom > cols.top - 4 || (evs && cols.bottom > evs.getBoundingClientRect().top - 8);
      }).length;
      return { laminas: slides.length, fuera, pisan };
    });
  } finally {
    await browser.close();
    await fs.rm(dir, { recursive: true, force: true });
  }
}

test('el estado de ejemplo cabe en sus láminas de 1920×1080', async () => {
  const r = await medir(estadoCorto());
  assert.equal(r.laminas, 8);
  assert.deepEqual(r.fuera, []);
});

test('el peor caso (14 bloques, textos y listas en sus topes, 13 evidencias) no se sale de ninguna lámina', async () => {
  const r = await medir(estadoLargo());
  assert.deepEqual(r.fuera, []);
  assert.equal(r.pisan, 0, 'en las láminas de cierre el título, las columnas y las evidencias no se pisan');
});

// Peor caso de «Dónde vamos»: 4 columnas × 7 bloques (una fase con bloques de sobra), uno en curso con «Estás aquí».
function estadoDondeLleno() {
  const base = estadoCorto();
  const largo = (n, t) => t.repeat(Math.ceil(n / t.length)).slice(0, n);
  const fases = ['diseno_desarrollo', 'implementacion', 'soporte', 'otra_cosa'];
  const bloques = fases.flatMap((f, fi) =>
    Array.from({ length: 7 }, (_, k) => {
      const n = fi * 7 + k;
      const en_curso = n === 10;
      return {
        nombre: largo(70, `Bloque ${n + 1} con un nombre largo `), fase: f, plan_desde: '2026-10-05', plan_hasta: '2026-10-09', entregable: '',
        estado: n < 10 ? 'listo' : en_curso ? 'en_curso' : 'por_venir', inicio_real: n <= 10 ? '2026-10-05' : '', fin_real: n < 10 ? '2026-10-09' : '',
      };
    })
  );
  return Object.assign(base, { bloques });
}

test('«Dónde vamos» en su peor caso: las columnas empiezan bajo el logo y no pisan la caja del plan inicial', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-estado-donde-'));
  const archivo = path.join(dir, 'index.html');
  await fs.writeFile(archivo, renderEstadoHtml(estadoDondeLleno(), {}), 'utf8');
  const browser = await chromium.launch({ args: ['--disable-dev-shm-usage', '--no-sandbox'] });
  try {
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
    await page.goto(pathToFileURL(archivo).href, { waitUntil: 'load' });
    await page.emulateMedia({ media: 'print' });
    const m = await page.evaluate(() => {
      const slide = document.querySelector('.estado-donde');
      const s = slide.getBoundingClientRect();
      const fases = slide.querySelector('.estado-fases').getBoundingClientRect();
      const plan = slide.querySelector('.estado-plan').getBoundingClientRect();
      const cols = slide.querySelectorAll('.estado-fase').length;
      const aqui = slide.querySelectorAll('.is-aqui').length;
      return { cols, aqui, fasesTop: fases.top - s.top, fasesBottom: fases.bottom - s.top, planTop: plan.top - s.top, slideH: s.height };
    });
    assert.equal(m.cols, 4);
    assert.equal(m.aqui, 1);
    assert.ok(m.fasesTop >= 230, `las columnas empiezan en ${Math.round(m.fasesTop)} px: el logo y el título ocupan hasta 230`);
    assert.ok(m.fasesBottom <= m.planTop - 8, `las columnas terminan a ${Math.round(m.fasesBottom)} px y la caja del plan empieza a ${Math.round(m.planTop)} px`);
    assert.ok(m.fasesBottom <= m.slideH - 20);
    const r = await medir(estadoDondeLleno());
    assert.deepEqual(r.fuera, []);
  } finally {
    await browser.close();
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('el PDF del estado tiene una página por lámina', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-estado-pdf-'));
  try {
    const { pdfPath } = await renderToFiles(renderEstadoHtml(estadoCorto(), {}), dir);
    const pdf = (await fs.readFile(pdfPath)).toString('latin1');
    assert.equal((pdf.match(/\/Type\s*\/Page(?!s)/g) || []).length, 8);
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});
