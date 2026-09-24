const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { createApp } = require('../src/server');

const PAYLOAD = {
  unique_id: 'test-images-passthrough',
  client_name: 'Jeffer Garcia',
  company_name: 'Academia de Béisbol',
  challenge_title: 'Desafío',
  challenge_text: 'Texto',
  solution_title: 'Solución',
  solution_text: 'Texto',
  benefits: [{ title: 'A', text: 'B' }],
  how_it_works: [{ step_title: 'A', step_text: 'B' }],
  pricing_rows: [{ service: 'Sitio web', price_usd: 500 }],
  next_steps: ['Aprobación'],
};

async function post(app, body) {
  const server = app.listen(0);
  await new Promise((r) => server.once('listening', r));
  const { port } = server.address();
  try {
    const res = await fetch(`http://127.0.0.1:${port}/render`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    return { status: res.status, body: await res.json() };
  } finally {
    server.close();
  }
}

test('a slide whose image is supplied is never requested from Higgsfield', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-render-'));
  const asked = [];
  const originalFetch = global.fetch;
  // Any call to the image endpoint here means the passthrough leaked.
  global.fetch = async (url, opts) => {
    if (String(url).includes('higgsfield')) {
      asked.push(String(url));
      return { ok: false, status: 500, text: async () => 'no debio llamarse' };
    }
    if (String(url) === 'https://cdn.example.com/ya-la-tengo.png') {
      return { ok: true, status: 200, headers: { get: () => 'image/png' }, arrayBuffer: async () => Buffer.from('PNG') };
    }
    return originalFetch(url, opts);
  };
  try {
    const app = createApp({ publicDir: dir, baseUrl: 'http://x', higgsfieldCredentials: {} });
    const res = await post(app, {
      ...PAYLOAD,
      image_briefs: [{ slide: 'cover', prompt: 'foto' }],
      images: { cover: 'https://cdn.example.com/ya-la-tengo.png' },
    });
    assert.equal(res.status, 200);
    assert.deepEqual(asked, [], 'no debe pedirse ninguna imagen ya provista');
    assert.ok(res.body.images, 'respuesta debe tener objeto images');
    assert.equal(res.body.images.stored_local, 1);
    assert.deepEqual(res.body.images.missing, []);
    const html = await fs.readFile(path.join(dir, PAYLOAD.unique_id, 'index.html'), 'utf8');
    assert.ok(html.includes("url('img/cover.png')"), 'la foto provista queda guardada y enlazada en local');
  } finally {
    global.fetch = originalFetch;
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('briefs without a supplied image are still generated', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-render-'));
  const asked = [];
  const originalFetch = global.fetch;
  global.fetch = async (url, opts) => {
    const u = String(url);
    if (u.endsWith('/soul/v2/standard')) {
      asked.push('submit');
      return { ok: true, json: async () => ({ status: 'queued', status_url: 'https://higgsfield/status' }) };
    }
    if (u.includes('higgsfield')) {
      return { ok: true, json: async () => ({ status: 'completed', images: [{ url: 'https://cdn.example.com/nueva.png' }] }) };
    }
    return originalFetch(url, opts);
  };
  try {
    const app = createApp({ publicDir: dir, baseUrl: 'http://x', higgsfieldCredentials: {} });
    const res = await post(app, {
      ...PAYLOAD,
      image_briefs: [
        { slide: 'cover', prompt: 'ya la tengo' },
        { slide: 'pricing', prompt: 'esta falta' },
      ],
      images: { cover: 'https://cdn.example.com/ya-la-tengo.png' },
    });
    assert.equal(res.status, 200);
    assert.equal(asked.length, 1, 'solo la lámina que falta se pide');
    const html = await fs.readFile(path.join(dir, PAYLOAD.unique_id, 'index.html'), 'utf8');
    assert.ok(html.includes("url('https://cdn.example.com/ya-la-tengo.png')"));
    assert.ok(html.includes("url('https://cdn.example.com/nueva.png')"));
  } finally {
    global.fetch = originalFetch;
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('a payload with no images field behaves exactly as before', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-render-'));
  const originalFetch = global.fetch;
  global.fetch = async (url, opts) => {
    const u = String(url);
    if (u.endsWith('/soul/v2/standard')) {
      return { ok: true, json: async () => ({ status: 'queued', status_url: 'https://higgsfield/status' }) };
    }
    if (u.includes('higgsfield')) {
      return { ok: true, json: async () => ({ status: 'completed', images: [{ url: 'https://cdn.example.com/g.png' }] }) };
    }
    return originalFetch(url, opts);
  };
  try {
    const app = createApp({ publicDir: dir, baseUrl: 'http://x', higgsfieldCredentials: {} });
    const res = await post(app, { ...PAYLOAD, image_briefs: [{ slide: 'cover', prompt: 'foto' }] });
    assert.equal(res.status, 200);
    const html = await fs.readFile(path.join(dir, PAYLOAD.unique_id, 'index.html'), 'utf8');
    assert.ok(html.includes("url('https://cdn.example.com/g.png')"));
  } finally {
    global.fetch = originalFetch;
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('a brief whose photo could not be generated is reported as missing', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-render-'));
  const originalFetch = global.fetch;
  global.fetch = async (url, opts) => {
    if (String(url).includes('higgsfield')) return { ok: false, status: 500, text: async () => 'caido' };
    return originalFetch(url, opts);
  };
  try {
    const app = createApp({ publicDir: dir, baseUrl: 'http://x', higgsfieldCredentials: { keyId: 'k', keySecret: 's' } });
    const res = await post(app, { ...PAYLOAD, unique_id: 'faltante', image_briefs: [{ slide: 'cover', prompt: 'foto' }] });
    assert.equal(res.status, 200);
    assert.equal(res.body.images.requested, 1);
    assert.deepEqual(res.body.images.missing, ['cover']);
  } finally {
    global.fetch = originalFetch;
    await fs.rm(dir, { recursive: true, force: true });
  }
});
