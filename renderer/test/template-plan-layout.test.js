const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { chromium } = require('playwright');
const { renderPlanHtml } = require('../src/template-plan');
const { renderToFiles } = require('../src/render');
const { planLargo } = require('./fixtures/plan-ejemplo');

// Caso 6 del plan de trabajo, medido en Chromium de verdad (el mismo de render.js, con sus mismos args) a
// 1920×1080 y en modo impresión, que es lo que ve el PDF: ningún elemento de una lámina puede terminar a
// menos de 20 px del borde de abajo ni salirse por los lados, la tabla de una fase no pisa la tarjeta y la
// carta Gantt termina antes de 1050 px con letra legible. Los planes usan los topes que acepta
// at_pt_validar_plan (Task 2): 14 bloques, 12 insumos de 160 letras, 8 reuniones (nombre 80 + detalle 200)
// y 6 servicios mensuales de 160. Datos inventados: el repositorio es público.

const FERIADOS = new Set(['2026-10-12', '2026-12-08', '2026-12-25', '2027-01-01']);
const DIA_MS = 86400000;

function mover(fecha, dias) {
  return new Date(new Date(`${fecha}T00:00:00Z`).getTime() + dias * DIA_MS).toISOString().slice(0, 10);
}

function esHabil(fecha) {
  const d = new Date(`${fecha}T00:00:00Z`).getUTCDay();
  return d !== 0 && d !== 6 && !FERIADOS.has(fecha);
}

function siguiente(fecha) {
  let x = mover(fecha, 1);
  while (!esHabil(x)) x = mover(x, 1);
  return x;
}

function sumar(desde, n) {
  let x = desde;
  while (!esHabil(x)) x = mover(x, 1);
  for (let i = 1; i < n; i++) x = siguiente(x);
  return x;
}

function largo(t, n) {
  return `${t} `.repeat(Math.ceil(n / (t.length + 1))).slice(0, n).trim();
}

// 14 bloques de trabajo (el tope de la Task 3) con su revisión en 27 semanas, textos de 80 a 420 letras,
// un bloque de 30 actividades, 18 insumos y 9 reuniones: nada de esto debería llegar, pero no puede romper.
function planExtremo() {
  const p = planLargo();
  p.unique_id = 'PlanPrueba99';
  p.proyecto = largo('Plataforma de reservas, pagos, inventario y reportes', 118);
  p.fases[0].descripcion = largo('Diseñamos las pantallas contigo y construimos la plataforma por partes.', 420);
  p.fases[0].bloques.splice(1, 0, {
    nombre: largo('Bloque gigante de desarrollo con muchas actividades', 80),
    entregable: largo('Entregable con un nombre muy largo', 90),
    entrega: true,
    actividades: Array.from({ length: 30 }, (_, i) => ({
      nombre: `${i + 1}. ${largo('Actividad con un nombre larguísimo', 100)}`,
      detalle: i % 2 ? largo('Detalle muy largo de la actividad', 150) : '',
      responsable: ['at', 'cliente', 'ambos'][i % 3],
      dias_habiles: 60,
      en_paralelo: i % 4 === 3,
      desde: '2026-10-09',
      hasta: '2026-10-14',
    })),
  });
  p.necesitamos_de_ti = Array.from({ length: 18 }, (_, i) => `${i + 1}. ${largo('Insumo con descripción larga', 80)}`);
  p.reuniones = Array.from({ length: 9 }, (_, i) => ({ nombre: `Reunión ${i + 1}`, detalle: largo('Detalle largo', 120) }));
  p.soporte = { garantia_meses: 12, mensuales: Array.from({ length: 6 }, (_, i) => `Servicio mensual ${i + 1}`) };
  const barras = [];
  for (let i = 0; i < 14; i++) {
    const lunes = mover('2026-10-05', i * 14);
    const fase = i < 10 ? 'diseno_desarrollo' : i < 12 ? 'implementacion' : 'soporte';
    barras.push({ fase, etiqueta: `Bloque ${i + 1} ${largo('con nombre largo', 60)}`, tipo: 'trabajo', responsable: 'at', desde: lunes, hasta: mover(lunes, 4) });
    barras.push({ fase, etiqueta: 'Tu revisión', tipo: 'revision', responsable: 'cliente', desde: mover(lunes, 7), hasta: mover(lunes, 11) });
  }
  p.cronograma = {
    inicio: '2026-10-05',
    fin: barras[barras.length - 1].hasta,
    semanas: 28,
    barras,
    hitos: [
      { nombre: 'Diseño aprobado', fecha: '2026-10-16' },
      { nombre: 'Núcleo aprobado', fecha: '2026-10-30' },
      { nombre: 'Módulos aprobados', fecha: '2026-11-13' },
      { nombre: 'Plataforma aprobada', fecha: '2027-03-19' },
      { nombre: 'Entrega estimada', fecha: '2027-03-26' },
    ],
  };
  return p;
}

// El peor caso de la Task 3 (Arranque + 13 bloques con entrega, una actividad cada uno) con entregables de
// dos líneas, como los del plan largo del fixture. Sin tope de entregas por lámina, la tarjeta «Qué
// aprobamos juntos» de la segunda lámina de la fase termina a 1118 px.
function planPeorCaso() {
  const p = planLargo();
  p.unique_id = 'PlanPrueba04';
  const inicio = '2026-10-05';
  const finArranque = sumar(siguiente(inicio), 3);
  const bloques = [
    {
      nombre: 'Arranque',
      entregable: '',
      entrega: false,
      actividades: [
        { nombre: 'Reunión de inicio', detalle: '', responsable: 'ambos', dias_habiles: 1, en_paralelo: false, desde: inicio, hasta: inicio },
        { nombre: 'Entrega de logo, textos y accesos', detalle: '', responsable: 'cliente', dias_habiles: 3, en_paralelo: false, desde: siguiente(inicio), hasta: finArranque },
      ],
    },
  ];
  const barras = [{ fase: 'diseno_desarrollo', etiqueta: 'Arranque', tipo: 'trabajo', responsable: 'ambos', desde: inicio, hasta: finArranque }];
  let cursor = siguiente(finArranque);
  for (let i = 1; i <= 13; i++) {
    const dias = i <= 9 ? 5 : 4;
    const hasta = sumar(cursor, dias);
    bloques.push({
      nombre: `Etapa ${i}`,
      entregable: `Módulo ${i} funcionando en ambiente de prueba con sus reportes`,
      entrega: true,
      actividades: [{ nombre: `Trabajo ${i}`, detalle: '', responsable: 'at', dias_habiles: dias, en_paralelo: false, desde: cursor, hasta }],
    });
    barras.push({ fase: 'diseno_desarrollo', etiqueta: `Etapa ${i}`, tipo: 'trabajo', responsable: 'at', desde: cursor, hasta });
    const revisionDesde = siguiente(hasta);
    const revisionHasta = sumar(revisionDesde, 5);
    barras.push({ fase: 'diseno_desarrollo', etiqueta: 'Tu revisión', tipo: 'revision', responsable: 'cliente', desde: revisionDesde, hasta: revisionHasta });
    cursor = siguiente(revisionHasta);
  }
  p.fases = [{ ...p.fases[0], bloques }];
  p.cronograma = {
    inicio,
    fin: barras[barras.length - 1].hasta,
    semanas: 27,
    barras,
    hitos: [{ nombre: 'Entrega estimada', fecha: barras[barras.length - 2].hasta }],
  };
  return p;
}

// Las tres listas en los topes de at_pt_validar_plan, con textos largos de verdad.
function planListasAlTope() {
  const p = planLargo();
  p.unique_id = 'PlanPrueba05';
  p.necesitamos_de_ti = Array.from(
    { length: 12 },
    (_, i) => `${i + 1}. ${largo('Acceso al panel del proveedor de correo con usuario administrador y clave vigente', 155)}`
  );
  p.reuniones = Array.from({ length: 8 }, (_, i) => ({
    nombre: `Reunión ${i + 1} ${largo('de seguimiento semanal del avance', 68)}`,
    detalle: largo('Revisamos contigo lo avanzado, resolvemos dudas y acordamos los próximos pasos', 200),
  }));
  p.soporte = {
    garantia_meses: 12,
    mensuales: Array.from({ length: 6 }, (_, i) => `${i + 1}. ${largo('Mantención mensual del sitio con respaldos y actualizaciones de seguridad', 155)}`),
  };
  return p;
}

async function medir(plan) {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-plan-layout-'));
  const archivo = path.join(dir, 'index.html');
  await fs.writeFile(archivo, renderPlanHtml(plan, {}), 'utf8');
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
          const r = el.getBoundingClientRect();
          // Sin tamaño, o a lo alto de toda la lámina (las dos columnas del cierre): no es contenido que se corte.
          if ((!r.width && !r.height) || r.height >= s.height - 1) return;
          if (r.bottom > s.bottom - 20 || r.top < s.top - 1 || r.left < s.left - 1 || r.right > s.right + 1) {
            fuera.push(`lámina ${n + 1}: ${el.tagName.toLowerCase()}.${String(el.className)} termina a ${Math.round(r.bottom - s.top)} px`);
          }
        });
        const tabla = slide.querySelector('.plan-texto');
        const tarjeta = slide.querySelector('.plan-tarjeta');
        if (tabla && tarjeta && tabla.getBoundingClientRect().right > tarjeta.getBoundingClientRect().left) {
          fuera.push(`lámina ${n + 1}: la tabla se monta sobre la tarjeta`);
        }
      });
      const g = document.querySelector('.gantt');
      const gantt = g
        ? {
            fin: Math.round(g.getBoundingClientRect().bottom - g.closest('.slide').getBoundingClientRect().top),
            letra: parseFloat(getComputedStyle(g.querySelector('.gantt-etiqueta b')).fontSize),
          }
        : null;
      return { laminas: slides.length, fuera, gantt };
    });
  } finally {
    await browser.close();
    await fs.rm(dir, { recursive: true, force: true });
  }
}

test('la carta Gantt larga (14 semanas, 16 barras) y todas las láminas del plan largo caben en 1920×1080', async () => {
  const m = await medir(planLargo());
  assert.equal(m.laminas, 11);
  assert.deepEqual(m.fuera, []);
  assert.ok(m.gantt, 'falta la carta Gantt');
  assert.ok(m.gantt.fin <= 1050, `la carta termina a ${m.gantt.fin} px`);
  assert.ok(m.gantt.letra >= 16, `letra de la carta ${m.gantt.letra} px`);
});

test('con 14 bloques en 27 semanas y textos enormes nada se sale de su lámina y la carta sigue legible', async () => {
  const m = await medir(planExtremo());
  assert.ok(m.laminas > 11, `${m.laminas} láminas`);
  assert.deepEqual(m.fuera, []);
  assert.ok(m.gantt, 'falta la carta Gantt');
  assert.ok(m.gantt.fin <= 1050, `la carta termina a ${m.gantt.fin} px`);
  assert.ok(m.gantt.letra >= 14, `letra de la carta ${m.gantt.letra} px`);
});

test('el peor caso de la Task 3 (13 entregas de dos líneas) reparte «Qué aprobamos juntos» sin salirse', async () => {
  const m = await medir(planPeorCaso());
  // Portada, método, Gantt, la fase en tres láminas (5 + 5 + 3 entregas), necesitamos, reuniones, portal, cierre.
  assert.equal(m.laminas, 10);
  assert.deepEqual(m.fuera, []);
});

test('las listas en los topes de at_pt_validar_plan caben en sus láminas', async () => {
  const m = await medir(planListasAlTope());
  assert.equal(m.laminas, 11);
  assert.deepEqual(m.fuera, []);
});

test('el PDF del plan largo tiene una página por lámina', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-plan-pdf-'));
  try {
    const { pdfPath } = await renderToFiles(renderPlanHtml(planLargo(), {}), dir);
    const pdf = (await fs.readFile(pdfPath)).toString('latin1');
    assert.equal((pdf.match(/\/Type\s*\/Page(?!s)/g) || []).length, 11);
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});
