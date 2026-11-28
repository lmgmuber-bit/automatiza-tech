const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { createApp } = require('../src/server');
const { planCorto } = require('./fixtures/plan-ejemplo');

async function conServidor(fn) {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-plan-'));
  const app = createApp({ publicDir, baseUrl: 'http://localhost:3000', higgsfieldCredentials: {} });
  const server = app.listen(0);
  await new Promise((r) => server.once('listening', r));
  const { port } = server.address();
  try {
    await fn(`http://127.0.0.1:${port}`, publicDir);
  } finally {
    server.close();
    await fs.rm(publicDir, { recursive: true, force: true });
  }
}

function renderizar(base, cuerpo) {
  return fetch(`${base}/render`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(cuerpo),
  });
}

test('POST /render con document_type plan dibuja el plan de trabajo, no una propuesta', async () => {
  await conServidor(async (base, publicDir) => {
    const r = await renderizar(base, planCorto());
    assert.equal(r.status, 200);
    const cuerpo = await r.json();
    assert.equal(cuerpo.view_url, 'http://localhost:3000/p/PlanPrueba01/index.html');
    assert.equal(cuerpo.pdf_url, 'http://localhost:3000/p/PlanPrueba01/presentation.pdf');
    const html = await fs.readFile(path.join(publicDir, 'PlanPrueba01', 'index.html'), 'utf8');
    assert.ok(html.includes('Plan de trabajo — Sitio de una página'));
    assert.ok(!html.includes('Propuesta de transformación digital'));
    const pdf = await fs.stat(path.join(publicDir, 'PlanPrueba01', 'presentation.pdf'));
    assert.ok(pdf.size > 0);
  });
});

// Todo 400 de un plan trae `details` con motivos en español: n8n (Task 14) no lo reintenta y copia esos
// motivos a la nota del plan en «error» (decisión D11).
test('un plan inválido responde 400 con motivos legibles en details, sin escribir nada', async () => {
  await conServidor(async (base, publicDir) => {
    const plan = planCorto();
    delete plan.cronograma;
    const r = await renderizar(base, plan);
    assert.equal(r.status, 400);
    const cuerpo = await r.json();
    assert.equal(cuerpo.error, 'invalid payload');
    assert.deepEqual(cuerpo.details, ['falta el objeto obligatorio: cronograma']);

    const raro = await renderizar(base, { ...planCorto(), unique_id: '../fuera' });
    assert.equal(raro.status, 400);
    assert.deepEqual(await raro.json(), {
      error: 'invalid payload',
      details: ['unique_id debe tener de 6 a 64 caracteres, solo letras, números, guion o guion bajo'],
    });
    assert.deepEqual(await fs.readdir(publicDir), []);
  });
});

test('sin document_type el cuerpo se valida como propuesta, igual que hoy', async () => {
  await conServidor(async (base) => {
    const plan = planCorto();
    delete plan.document_type;
    const r = await renderizar(base, plan);
    assert.equal(r.status, 400);
    const cuerpo = await r.json();
    assert.ok(cuerpo.details.includes('missing or empty required field: challenge_title'));
  });
});

// Higgsfield falso: anota el prompt de cada foto que se le pide y responde 500 (en la prueba no hay
// credenciales), así esa lámina queda en «missing» sin esperar la cola. Las fotos de cdn.example.com (las
// que n8n reutiliza de la propuesta) se «descargan» al tiro. Lo demás (el propio servidor) pasa derecho.
async function conHiggsfieldFalso(fn) {
  const originalFetch = global.fetch;
  const prompts = [];
  global.fetch = async (url, opts) => {
    const u = String(url);
    if (u.includes('higgsfield')) {
      prompts.push(JSON.parse(opts.body).prompt);
      return { ok: false, status: 500, text: async () => 'sin credenciales en la prueba' };
    }
    if (u.startsWith('https://cdn.example.com/')) {
      return { ok: true, status: 200, headers: { get: () => 'image/png' }, arrayBuffer: async () => Buffer.from('PNG') };
    }
    return originalFetch(url, opts);
  };
  try {
    await fn(prompts);
  } finally {
    global.fetch = originalFetch;
  }
}

const BRIEFS = [
  { slide: 'cover', prompt: 'foto-portada' },
  { slide: 'metodo', prompt: 'foto-metodo' },
  { slide: 'cierre', prompt: 'foto-cierre' },
];

test('con propuesta: portada y cierre llegan en images y no se piden a Higgsfield; la lámina nueva sí', async () => {
  await conHiggsfieldFalso(async (prompts) => {
    await conServidor(async (base, publicDir) => {
      const plan = {
        ...planCorto(),
        image_briefs: BRIEFS,
        images: { cover: 'https://cdn.example.com/portada.png', cierre: 'https://cdn.example.com/proximos.png' },
      };
      const r = await renderizar(base, plan);
      assert.equal(r.status, 200);
      const cuerpo = await r.json();
      assert.deepEqual([...new Set(prompts)], ['foto-metodo']);
      assert.equal(cuerpo.images.requested, 3);
      assert.equal(cuerpo.images.stored_local, 2);
      assert.deepEqual(cuerpo.images.missing, ['metodo']);
      const html = await fs.readFile(path.join(publicDir, 'PlanPrueba01', 'index.html'), 'utf8');
      assert.ok(html.includes("url('img/cover.png')"));
    });
  });
});

test('contrato sin propuesta: portada y cierre se piden como fotos nuevas', async () => {
  await conHiggsfieldFalso(async (prompts) => {
    await conServidor(async (base) => {
      const r = await renderizar(base, { ...planCorto(), image_briefs: BRIEFS, images: {} });
      assert.equal(r.status, 200);
      const cuerpo = await r.json();
      assert.deepEqual([...new Set(prompts)].sort(), ['foto-cierre', 'foto-metodo', 'foto-portada']);
      assert.deepEqual([...cuerpo.images.missing].sort(), ['cierre', 'cover', 'metodo']);
    });
  });
});
