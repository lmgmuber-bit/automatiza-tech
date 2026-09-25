const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { createApp } = require('../src/server');

// Payload inválido a propósito: si pasa la clave, /render responde 400 por validación sin
// llamar a Higgsfield ni escribir en disco. Así cada prueba sabe si la clave dejó pasar.
const PAYLOAD_INVALIDO = { unique_id: 'prueba-clave' };

async function conApp(opciones, fn) {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-clave-'));
  const app = createApp({ publicDir, baseUrl: 'http://localhost:3000', higgsfieldCredentials: {}, ...opciones });
  const server = app.listen(0);
  const { port } = server.address();
  try {
    await fn(`http://localhost:${port}`, publicDir);
  } finally {
    server.close();
  }
}

function render(base, cabeceras = {}) {
  return fetch(`${base}/render`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...cabeceras },
    body: JSON.stringify(PAYLOAD_INVALIDO),
  });
}

test('sin renderKey, /render sigue abierto (llega a la validación)', async () => {
  await conApp({}, async (base) => {
    assert.equal((await render(base)).status, 400);
  });
});

test('con renderKey y sin cabecera, /render responde 401 antes de validar', async () => {
  await conApp({ renderKey: 'clave-de-prueba-123' }, async (base, publicDir) => {
    const r = await render(base);
    assert.equal(r.status, 401);
    assert.deepEqual(await r.json(), { error: 'unauthorized' });
    assert.deepEqual(await fs.readdir(publicDir), [], 'no debe escribir nada en disco');
  });
});

test('con renderKey y cabecera equivocada, /render responde 401', async () => {
  await conApp({ renderKey: 'clave-de-prueba-123' }, async (base) => {
    assert.equal((await render(base, { 'X-AT-Render-Key': 'otra-clave' })).status, 401);
    assert.equal((await render(base, { 'X-AT-Render-Key': 'clave-de-prueba-12' })).status, 401, 'un prefijo no sirve');
    assert.equal((await render(base, { 'X-AT-Render-Key': '' })).status, 401);
  });
});

test('con renderKey y la cabecera correcta, /render pasa a la validación', async () => {
  await conApp({ renderKey: 'clave-de-prueba-123' }, async (base) => {
    assert.equal((await render(base, { 'X-AT-Render-Key': 'clave-de-prueba-123' })).status, 400);
    assert.equal((await render(base, { 'x-at-render-key': 'clave-de-prueba-123' })).status, 400, 'el nombre de la cabecera no distingue mayúsculas');
  });
});

test('con renderKey, /health y las presentaciones públicas /p/ siguen sin clave', async () => {
  await conApp({ renderKey: 'clave-de-prueba-123' }, async (base, publicDir) => {
    assert.equal((await fetch(`${base}/health`)).status, 200);
    await fs.mkdir(path.join(publicDir, 'abc123'));
    await fs.writeFile(path.join(publicDir, 'abc123', 'index.html'), '<p>hola</p>');
    const r = await fetch(`${base}/p/abc123/index.html`);
    assert.equal(r.status, 200);
    assert.equal(await r.text(), '<p>hola</p>');
  });
});
