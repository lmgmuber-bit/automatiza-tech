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
    if (String(url).startsWith('https://cdn.example.com/')) {
      return { ok: true, status: 200, headers: { get: () => 'image/png' }, arrayBuffer: async () => Buffer.from('PNG') };
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
    assert.ok(html.includes("url('img/cover.png')"));
    assert.ok(html.includes("url('img/pricing.png')"));
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
    if (String(url).startsWith('https://cdn.example.com/')) {
      return { ok: true, status: 200, headers: { get: () => 'image/png' }, arrayBuffer: async () => Buffer.from('PNG') };
    }
    return originalFetch(url, opts);
  };
  try {
    const app = createApp({ publicDir: dir, baseUrl: 'http://x', higgsfieldCredentials: {} });
    const res = await post(app, { ...PAYLOAD, image_briefs: [{ slide: 'cover', prompt: 'foto' }] });
    assert.equal(res.status, 200);
    const html = await fs.readFile(path.join(dir, PAYLOAD.unique_id, 'index.html'), 'utf8');
    assert.ok(html.includes("url('img/cover.png')"));
  } finally {
    global.fetch = originalFetch;
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('a retry for the same unique_id reuses the photo already generated for an unchanged prompt', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-render-'));
  const submittedPrompts = [];
  let failPricing = true;
  const originalFetch = global.fetch;
  global.fetch = async (url, opts) => {
    const u = String(url);
    if (u.endsWith('/soul/v2/standard')) {
      const body = JSON.parse(opts.body);
      submittedPrompts.push(body.prompt);
      if (body.prompt === 'foto de precios' && failPricing) {
        return { ok: false, status: 500, text: async () => 'higgsfield caido' };
      }
      return {
        ok: true,
        json: async () => ({ status: 'queued', status_url: `https://higgsfield/status/${encodeURIComponent(body.prompt)}` }),
      };
    }
    if (u.includes('/status/')) {
      const prompt = decodeURIComponent(u.split('/status/')[1]);
      const fileName = prompt === 'foto de portada' ? 'cover' : 'pricing';
      return { ok: true, json: async () => ({ status: 'completed', images: [{ url: `https://cdn.example.com/${fileName}.png` }] }) };
    }
    if (u.startsWith('https://cdn.example.com/')) {
      return { ok: true, status: 200, headers: { get: () => 'image/png' }, arrayBuffer: async () => Buffer.from('PNG') };
    }
    return originalFetch(url, opts);
  };
  try {
    const app = createApp({ publicDir: dir, baseUrl: 'http://x', higgsfieldCredentials: { keyId: 'k', keySecret: 's' } });
    const payload = {
      ...PAYLOAD,
      unique_id: 'retry-reuso-fotos',
      image_briefs: [
        { slide: 'cover', prompt: 'foto de portada' },
        { slide: 'pricing', prompt: 'foto de precios' },
      ],
    };

    const first = await post(app, payload);
    assert.equal(first.status, 200);
    assert.equal(first.body.images.stored_local, 1, 'solo cover se genera en el primer intento');
    assert.deepEqual(first.body.images.missing, ['pricing']);
    assert.equal(first.body.images.reused, 0, 'nada que reusar en el primer intento');

    // Second attempt (n8n retrying because "pricing" is missing): Higgsfield
    // would now succeed for both, but "cover" must not be asked again.
    failPricing = false;
    submittedPrompts.length = 0;

    const second = await post(app, payload);
    assert.equal(second.status, 200);
    assert.deepEqual(submittedPrompts, ['foto de precios'], 'cover no debe volver a pedirse a Higgsfield');
    assert.equal(second.body.images.reused, 1, 'cover se sirve desde el manifiesto');
    assert.deepEqual(second.body.images.missing, []);

    const html = await fs.readFile(path.join(dir, payload.unique_id, 'index.html'), 'utf8');
    assert.match(html, /url\('img\/cover\.[a-z]+'\)/);
    assert.match(html, /url\('img\/pricing\.[a-z]+'\)/);
  } finally {
    global.fetch = originalFetch;
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('a brief whose prompt changed on retry is regenerated instead of reused', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-render-'));
  const submittedPrompts = [];
  const originalFetch = global.fetch;
  global.fetch = async (url, opts) => {
    const u = String(url);
    if (u.endsWith('/soul/v2/standard')) {
      const body = JSON.parse(opts.body);
      submittedPrompts.push(body.prompt);
      return { ok: true, json: async () => ({ status: 'queued', status_url: 'https://higgsfield/status/cover' }) };
    }
    if (u.includes('/status/')) {
      return { ok: true, json: async () => ({ status: 'completed', images: [{ url: 'https://cdn.example.com/cover.png' }] }) };
    }
    if (u.startsWith('https://cdn.example.com/')) {
      return { ok: true, status: 200, headers: { get: () => 'image/png' }, arrayBuffer: async () => Buffer.from('PNG') };
    }
    return originalFetch(url, opts);
  };
  try {
    const app = createApp({ publicDir: dir, baseUrl: 'http://x', higgsfieldCredentials: { keyId: 'k', keySecret: 's' } });
    const basePayload = { ...PAYLOAD, unique_id: 'retry-prompt-cambiado' };

    const first = await post(app, { ...basePayload, image_briefs: [{ slide: 'cover', prompt: 'prompt viejo' }] });
    assert.equal(first.status, 200);
    assert.equal(first.body.images.reused, 0);

    submittedPrompts.length = 0;
    const second = await post(app, { ...basePayload, image_briefs: [{ slide: 'cover', prompt: 'prompt nuevo' }] });
    assert.equal(second.status, 200);
    assert.deepEqual(submittedPrompts, ['prompt nuevo'], 'el prompt cambiado se pide de nuevo a Higgsfield');
    assert.equal(second.body.images.reused, 0, 'un prompt distinto no cuenta como reusado');
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
