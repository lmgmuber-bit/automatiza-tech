const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { createApp } = require('../src/server');
const { validatePayload } = require('../src/schema');

const VALID_PAYLOAD = {
  unique_id: 'test-proposal-1',
  client_name: 'Jeffer Garcia',
  company_name: 'Academia de Béisbol X',
  challenge_title: 'Gestión manual',
  challenge_text: 'Texto',
  solution_title: 'Solución',
  solution_text: 'Texto',
  benefits: [{ title: 'A', text: 'B' }],
  how_it_works: [{ step_title: 'A', step_text: 'B' }],
  pricing_rows: [{ service: 'Sitio web', price_usd: 500, price_clp: 460000 }],
  next_steps: ['Aprobación'],
  image_briefs: [],
};

test('GET /health returns status ok', async () => {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-public-'));
  const app = createApp({ publicDir, baseUrl: 'http://localhost:3000', higgsfieldCredentials: {} });
  const server = app.listen(0);
  const { port } = server.address();
  try {
    const response = await fetch(`http://localhost:${port}/health`);
    assert.equal(response.status, 200);
    assert.deepEqual(await response.json(), { status: 'ok' });
  } finally {
    server.close();
  }
});

test('POST /render returns view_url and pdf_url for a valid payload', async () => {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-public-'));
  const app = createApp({
    publicDir,
    baseUrl: 'http://localhost:3000',
    higgsfieldCredentials: { keyId: 'id', keySecret: 'secret' },
  });
  const server = app.listen(0);
  const { port } = server.address();

  try {
    const response = await fetch(`http://localhost:${port}/render`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(VALID_PAYLOAD),
    });
    assert.equal(response.status, 200);
    const body = await response.json();
    assert.equal(body.view_url, 'http://localhost:3000/p/test-proposal-1/index.html');
    assert.equal(body.pdf_url, 'http://localhost:3000/p/test-proposal-1/presentation.pdf');
  } finally {
    server.close();
  }
});

test('POST /render returns 400 for an invalid payload', async () => {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-public-'));
  const app = createApp({
    publicDir,
    baseUrl: 'http://localhost:3000',
    higgsfieldCredentials: { keyId: 'id', keySecret: 'secret' },
  });
  const server = app.listen(0);
  const { port } = server.address();

  try {
    const response = await fetch(`http://localhost:${port}/render`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({}),
    });
    assert.equal(response.status, 400);
  } finally {
    server.close();
  }
});

test('GET /p/{id}/index.html does not send X-Frame-Options (must stay iframe-embeddable)', async () => {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-public-'));
  await fs.mkdir(path.join(publicDir, 'existing'), { recursive: true });
  await fs.writeFile(path.join(publicDir, 'existing', 'index.html'), '<html></html>', 'utf8');

  const app = createApp({
    publicDir,
    baseUrl: 'http://localhost:3000',
    higgsfieldCredentials: { keyId: 'id', keySecret: 'secret' },
  });
  const server = app.listen(0);
  const { port } = server.address();

  try {
    const response = await fetch(`http://localhost:${port}/p/existing/index.html`);
    assert.equal(response.status, 200);
    assert.equal(response.headers.get('x-frame-options'), null);
  } finally {
    server.close();
  }
});
