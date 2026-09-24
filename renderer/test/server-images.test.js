const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { createApp } = require('../src/server');
const { promptHash } = require('../src/photo-manifest');

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

test('a slide satisfied by data.images this call is never recorded in the manifest, so a later retry without that override still asks Higgsfield', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-render-'));
  const uniqueId = 'no-reusar-lo-provisto-por-data-images';
  const imgDir = path.join(dir, uniqueId, 'img');
  await fs.mkdir(imgDir, { recursive: true });
  // "an existing local img file" the caller points data.images.cover at —
  // already on disk, not something this call needs to download.
  await fs.writeFile(path.join(imgDir, 'cover.png'), 'PNGDATA');

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
    const basePayload = { ...PAYLOAD, unique_id: uniqueId, image_briefs: [{ slide: 'cover', prompt: 'P1' }] };

    // Call 1: cover is satisfied by data.images, so Higgsfield is never asked.
    const first = await post(app, { ...basePayload, images: { cover: 'img/cover.png' } });
    assert.equal(first.status, 200);
    assert.deepEqual(submittedPrompts, [], 'data.images ya cubre cover; Higgsfield no debe llamarse');
    assert.equal(first.body.images.reused, 0);

    // Call 2: same unique_id, same prompt, but WITHOUT the data.images
    // override this time. Because call 1 must not have recorded "cover" in
    // the manifest (it never verified the prompt produced that file), this
    // retry cannot silently reuse it — it has to ask Higgsfield again.
    submittedPrompts.length = 0;
    const second = await post(app, basePayload);
    assert.equal(second.status, 200);
    assert.deepEqual(submittedPrompts, ['P1'], 'sin el data.images, cover debe pedirse de nuevo: nunca quedo en el manifiesto');
    assert.equal(second.body.images.reused, 0, 'lo provisto por data.images no cuenta como reusado ni deja rastro en el manifiesto');
  } finally {
    global.fetch = originalFetch;
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('a manifest.json entry literally named "__proto__" is treated as ordinary data, never as a real prototype', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-render-'));
  const uniqueId = 'proto-pollution-guard';
  const imgDir = path.join(dir, uniqueId, 'img');
  await fs.mkdir(imgDir, { recursive: true });
  await fs.writeFile(path.join(imgDir, 'cover.png'), 'PNGDATA');
  // Written as a raw JSON string (not via an object literal, where
  // `{ __proto__: ... }` sets the literal's own prototype instead of a key)
  // so the file on disk genuinely has an own "__proto__" property once
  // JSON.parse reads it back — exactly what a corrupted or crafted
  // manifest.json could contain. `Object.assign({}, parsed)` onto a plain
  // object would silently reassign that plain object's OWN prototype to
  // this entry's value instead of copying it as a normal key — which is
  // exactly the "hit the prototype" the fix must avoid.
  await fs.writeFile(
    path.join(imgDir, 'manifest.json'),
    '{"__proto__": {"pwned": "si"}, "cover": {"file": "cover.png", "hash": "no-coincide"}}'
  );
  const originalFetch = global.fetch;
  global.fetch = async (url, opts) =>
    String(url).includes('higgsfield') ? { ok: false, status: 500, text: async () => 'caido' } : originalFetch(url, opts);
  try {
    const app = createApp({ publicDir: dir, baseUrl: 'http://x', higgsfieldCredentials: {} });
    const res = await post(app, { ...PAYLOAD, unique_id: uniqueId, image_briefs: [{ slide: 'cover', prompt: 'foto' }] });
    assert.equal(res.status, 200);
    // The differentiating check: if the manifest-merge ever assigns the
    // "__proto__" entry onto a plain object (Object.assign({}, existing)),
    // that entry stops being an enumerable own property — it becomes the
    // object's actual [[Prototype]] instead — so it silently vanishes from
    // the JSON written back to disk. With the null-prototype build it
    // round-trips like any other slide.
    const manifestRaw = await fs.readFile(path.join(imgDir, 'manifest.json'), 'utf8');
    const manifest = JSON.parse(manifestRaw);
    assert.deepEqual(manifest.__proto__, { pwned: 'si' }, 'la entrada "__proto__" debe conservarse como dato, no perderse');
    assert.deepEqual(manifest.cover, { file: 'cover.png', hash: 'no-coincide' }, 'las demas entradas no deben verse afectadas');
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

test('the photo manifest is written as soon as the photos are persisted, and survives a render step that fails afterward', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-render-'));
  const renderPath = require.resolve('../src/render');
  const serverPath = require.resolve('../src/server');
  const originalFetch = global.fetch;

  // Make sure ../src/render is in the require cache so its exports object
  // can be mutated in place, then remember the real renderToFiles to
  // restore it afterward.
  require(renderPath);
  const realRenderToFiles = require.cache[renderPath].exports.renderToFiles;

  const submittedPrompts = [];
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
    const payload = {
      ...PAYLOAD,
      unique_id: 'manifiesto-antes-del-render',
      image_briefs: [{ slide: 'cover', prompt: 'foto de portada' }],
    };

    // Stub renderToFiles to fail (simulating Playwright dying) AFTER the
    // photo has already been generated and persisted, then re-require
    // server.js so it picks up the stub (server.js destructures
    // renderToFiles from ../src/render at require time).
    require.cache[renderPath].exports.renderToFiles = async () => {
      throw new Error('playwright boom');
    };
    delete require.cache[serverPath];
    const { createApp: createAppBroken } = require(serverPath);

    const appBroken = createAppBroken({ publicDir: dir, baseUrl: 'http://x', higgsfieldCredentials: { keyId: 'k', keySecret: 's' } });
    const first = await post(appBroken, payload);
    assert.equal(first.status, 502, 'el render sigue respondiendo 502 cuando falla despues de generar la foto');

    const manifestRaw = await fs.readFile(path.join(dir, payload.unique_id, 'img', 'manifest.json'), 'utf8');
    const manifest = JSON.parse(manifestRaw);
    assert.ok(manifest.cover, 'el manifiesto debe existir con la lamina generada aunque el render haya fallado');
    assert.equal(manifest.cover.hash, promptHash('foto de portada'));

    // Restore the real renderToFiles and re-require server.js so the second
    // call (and any test file that requires it fresh afterward) uses the
    // real render step again.
    require.cache[renderPath].exports.renderToFiles = realRenderToFiles;
    delete require.cache[serverPath];
    const { createApp: createAppFixed } = require(serverPath);

    submittedPrompts.length = 0;
    const appFixed = createAppFixed({ publicDir: dir, baseUrl: 'http://x', higgsfieldCredentials: { keyId: 'k', keySecret: 's' } });
    const second = await post(appFixed, payload);
    assert.equal(second.status, 200, 'con el render funcionando, el segundo intento debe completarse');
    assert.deepEqual(submittedPrompts, [], 'cover ya quedo en el manifiesto: no debe volver a pedirse a Higgsfield');
    assert.equal(second.body.images.reused, 1, 'cover se sirve desde el manifiesto escrito antes del render fallido');
  } finally {
    require.cache[renderPath].exports.renderToFiles = realRenderToFiles;
    delete require.cache[serverPath];
    global.fetch = originalFetch;
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('unique_id with path traversal or slashes is rejected with 400 before any Higgsfield call', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-render-'));
  const asked = [];
  const originalFetch = global.fetch;
  global.fetch = async (url, opts) => {
    // `post()` itself uses fetch to call the local test server; only
    // Higgsfield calls count as "should never have happened".
    if (String(url).includes('higgsfield')) asked.push(String(url));
    return originalFetch(url, opts);
  };
  try {
    const app = createApp({ publicDir: dir, baseUrl: 'http://x', higgsfieldCredentials: {} });

    const traversal = await post(app, { ...PAYLOAD, unique_id: '../evil' });
    assert.equal(traversal.status, 400);
    assert.deepEqual(traversal.body, { error: { message: 'unique_id inválido' } });

    const slash = await post(app, { ...PAYLOAD, unique_id: 'a/b' });
    assert.equal(slash.status, 400);
    assert.deepEqual(slash.body, { error: { message: 'unique_id inválido' } });

    assert.deepEqual(asked, [], 'un unique_id invalido no debe generar ninguna llamada a Higgsfield ni de otro tipo');

    // Existing real ids must keep working.
    const ok = await post(app, { ...PAYLOAD, unique_id: 'verif-v3-fotos' });
    assert.equal(ok.status, 200, 'un unique_id real existente debe seguir funcionando');
    await fs.access(path.join(dir, 'verif-v3-fotos', 'index.html'));
  } finally {
    global.fetch = originalFetch;
    await fs.rm(dir, { recursive: true, force: true });
  }
});
