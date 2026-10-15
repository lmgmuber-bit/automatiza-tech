const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { createApp } = require('../src/server');
const { estadoCorto } = require('./fixtures/estado-ejemplo');

async function conServidor(fn) {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-estado-'));
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

const renderizar = (base, cuerpo) =>
  fetch(`${base}/render`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(cuerpo) });

test('POST /render con document_type estado dibuja «Estado del proyecto» en /p/<código>-estado/', async () => {
  await conServidor(async (base, publicDir) => {
    const r = await renderizar(base, estadoCorto());
    assert.equal(r.status, 200);
    const cuerpo = await r.json();
    assert.equal(cuerpo.view_url, 'http://localhost:3000/p/AbCdEf123456-estado/index.html');
    assert.equal(cuerpo.pdf_url, 'http://localhost:3000/p/AbCdEf123456-estado/presentation.pdf');
    const html = await fs.readFile(path.join(publicDir, 'AbCdEf123456-estado', 'index.html'), 'utf8');
    assert.ok(html.includes('Estado del proyecto — Sitio de la panadería'));
    assert.ok(!html.includes('Plan de trabajo —') && !html.includes('Propuesta de transformación digital'));
  });
});

test('con document_type estado nunca se piden fotos, aunque lleguen descripciones (D9)', async () => {
  await conServidor(async (base) => {
    const r = await renderizar(base, estadoCorto({ image_briefs: [{ slide: 'cover', prompt: 'una panadería de día' }] }));
    assert.equal(r.status, 200);
    const cuerpo = await r.json();
    assert.equal(cuerpo.images.requested, 0);
    assert.deepEqual(cuerpo.images.missing, []);
  });
});

test('un estado inválido responde 400 con motivos legibles, sin escribir nada', async () => {
  await conServidor(async (base, publicDir) => {
    const r = await renderizar(base, estadoCorto({ cierres: 'no' }));
    assert.equal(r.status, 400);
    const cuerpo = await r.json();
    assert.ok(cuerpo.details.includes('cierres debe ser un arreglo'));
    assert.deepEqual(await fs.readdir(publicDir), []);
  });
});
