const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const render = require('../src/render');
const { promptHash } = require('../src/photo-manifest');
const { retomarEjemplo } = require('./fixtures/retomar-ejemplo');

// Abrir Chromium tarda varios segundos por pedido. Salvo en la prueba de punta a punta (la primera), el render
// se reemplaza por uno que solo escribe index.html: lo que se verifica aquí es la lógica de fotos y el HTML.
// Se cambia antes de cargar server.js, que toma renderToFiles al cargarse.
const renderReal = render.renderToFiles;
let conChromium = false;
render.renderToFiles = async (html, outputDir) => {
  if (conChromium) return renderReal(html, outputDir);
  await fs.mkdir(outputDir, { recursive: true });
  await fs.writeFile(path.join(outputDir, 'index.html'), html, 'utf8');
  return { htmlPath: path.join(outputDir, 'index.html'), pdfPath: null };
};
const { createApp } = require('../src/server');

const UID = 'ejemplo123-retomar';
const BASE = 'http://x';
const PROMPT_PORTADA = 'Escena fotográfica 16:9 de un equipo pequeño atendiendo un local luminoso, sin texto, letreros ni etiquetas.';
const PROMPT_CIERRE = 'Escena fotográfica 16:9 de dos personas conversando frente a una pantalla, sin texto ni logos.';

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

// Higgsfield y las descargas de fotos se simulan: nada sale a la red ni gasta créditos.
// `prompts` guarda lo que se le pidió a Higgsfield; `descargas`, las fotos que el renderer bajó.
function simularRed({ fallaHiggsfield = false } = {}) {
  const original = global.fetch;
  const registro = { prompts: [], descargas: [] };
  let n = 0;
  global.fetch = async (url, opts) => {
    const u = String(url);
    if (u.endsWith('/soul/v2/standard')) {
      if (fallaHiggsfield) return { ok: false, status: 500, text: async () => 'sin servicio' };
      registro.prompts.push(JSON.parse(opts.body).prompt);
      n += 1;
      return { ok: true, json: async () => ({ status: 'queued', status_url: `https://higgsfield.example/status/${n}` }) };
    }
    if (u.startsWith('https://higgsfield.example/status/')) {
      return { ok: true, json: async () => ({ status: 'completed', images: [{ url: `https://cdn.example.com/foto-${u.split('/').pop()}.png` }] }) };
    }
    if (u.startsWith('https://cdn.example.com/') || u.startsWith(`${BASE}/`)) {
      registro.descargas.push(u);
      return { ok: true, status: 200, headers: { get: () => 'image/png' }, arrayBuffer: async () => Buffer.from('PNG') };
    }
    return original(url, opts);
  };
  return { registro, restaurar: () => { global.fetch = original; } };
}

async function conCarpeta(fn, opciones) {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-retomar-'));
  const red = simularRed(opciones);
  try {
    const app = createApp({ publicDir: dir, baseUrl: BASE, higgsfieldCredentials: {} });
    await fn({ dir, app, registro: red.registro });
  } finally {
    red.restaurar();
    await fs.rm(dir, { recursive: true, force: true });
  }
}

const leer = (dir, archivo) => fs.readFile(path.join(dir, UID, archivo), 'utf8');
const existe = (dir, archivo) => fs.access(path.join(dir, UID, archivo)).then(() => true, () => false);

test('retomar con dos encargos pide dos fotos, las guarda y devuelve sus URL; un reintento no las vuelve a pagar', async () => {
  await conCarpeta(async ({ dir, app, registro }) => {
    const payload = retomarEjemplo({
      recomendacion: 'Partamos por la agenda y sumemos lo demás después.',
      image_briefs: [{ slide: 'portada', prompt: PROMPT_PORTADA }, { slide: 'cierre', prompt: PROMPT_CIERRE }],
    });
    conChromium = true;
    let res;
    try {
      res = await post(app, payload);
    } finally {
      conChromium = false;
    }
    assert.equal(res.status, 200);
    assert.deepEqual(registro.prompts, [PROMPT_PORTADA, PROMPT_CIERRE], 'dos encargos, dos fotos pedidas');
    assert.equal(res.body.view_url, `${BASE}/p/${UID}/index.html`);
    assert.equal(res.body.pdf_url, `${BASE}/p/${UID}/presentation.pdf`);
    assert.deepEqual(res.body.images, {
      requested: 2, stored_local: 2, kept_remote: [], missing: [], reused: 0,
      urls: { portada: `${BASE}/p/${UID}/img/portada.png`, cierre: `${BASE}/p/${UID}/img/cierre.png` },
    });
    assert.ok(res.body.images.urls.portada.endsWith('/img/portada.png'));
    assert.equal(res.body.images.urls.demo, undefined, 'solo las láminas con foto');

    assert.ok(await existe(dir, 'img/portada.png') && await existe(dir, 'img/cierre.png') && await existe(dir, 'presentation.pdf'));
    const html = await leer(dir, 'index.html');
    assert.ok(html.includes("url('img/portada.png')") && html.includes("url('img/cierre.png')"), 'el HTML usa las fotos guardadas');
    assert.ok(!html.includes('https://example.com/portada.jpg'), 'images gana sobre fotos del payload');
    assert.ok(html.includes('Partamos por la agenda y sumemos lo demás después.'), 'la recomendación se dibuja');
    const manifiesto = JSON.parse(await leer(dir, 'img/manifest.json'));
    assert.equal(manifiesto.portada.hash, promptHash(PROMPT_PORTADA));
    assert.equal(manifiesto.cierre.hash, promptHash(PROMPT_CIERRE));

    // Mismo encargo otra vez (n8n reintenta mientras falte algo): se reusa lo guardado, sin pedir nada.
    const otra = await post(app, payload);
    assert.equal(otra.status, 200);
    assert.equal(registro.prompts.length, 2, 'no se vuelve a pedir');
    assert.equal(otra.body.images.reused, 2);
    assert.deepEqual(otra.body.images.urls, res.body.images.urls);
  });
});

test('un encargo cuya foto llega en images no se pide a Higgsfield', async () => {
  await conCarpeta(async ({ dir, app, registro }) => {
    const res = await post(app, retomarEjemplo({
      image_briefs: [{ slide: 'portada', prompt: PROMPT_PORTADA }, { slide: 'cierre', prompt: PROMPT_CIERRE }],
      images: { portada: 'https://cdn.example.com/ya-la-tengo.png' },
    }));
    assert.equal(res.status, 200);
    assert.deepEqual(registro.prompts, [PROMPT_CIERRE], 'solo la que falta');
    assert.ok(registro.descargas.includes('https://cdn.example.com/ya-la-tengo.png'), 'la provista se guarda en local');
    assert.deepEqual(Object.keys(res.body.images.urls).sort(), ['cierre', 'portada']);
    assert.deepEqual(res.body.images.missing, []);
    assert.ok((await leer(dir, 'index.html')).includes("url('img/portada.png')"));
  });
});

test('una foto que llega con la URL del propio renderer se toma del disco, sin descargarla', async () => {
  await conCarpeta(async ({ dir, app, registro }) => {
    // Foto de una corrida anterior; WordPress la manda de vuelta con su URL pública.
    await fs.mkdir(path.join(dir, UID, 'img'), { recursive: true });
    await fs.writeFile(path.join(dir, UID, 'img', 'portada.png'), 'PNG');
    const propia = `${BASE}/p/${UID}/img/portada.png`;
    const res = await post(app, retomarEjemplo({ images: { portada: propia } }));
    assert.equal(res.status, 200);
    assert.deepEqual(registro.prompts, []);
    assert.deepEqual(registro.descargas, [], 'ni siquiera se baja de sí mismo');
    assert.deepEqual(res.body.images.urls, { portada: propia }, 'sigue contando como guardada en local');
    assert.equal(res.body.images.stored_local, 0);
    assert.ok((await leer(dir, 'index.html')).includes("url('img/portada.png')"));
  });
});

test('si la foto con URL propia ya no está en el disco, se intenta bajar como cualquier otra', async () => {
  await conCarpeta(async ({ dir, app, registro }) => {
    const propia = `${BASE}/p/${UID}/img/portada.png`;
    const res = await post(app, retomarEjemplo({ images: { portada: propia } }));
    assert.equal(res.status, 200);
    assert.deepEqual(registro.descargas, [propia]);
    assert.deepEqual(res.body.images.urls, { portada: propia });
    assert.ok(await existe(dir, 'img/portada.png'));
  });
});

test('sin image_briefs ni images no hay ninguna llamada a Higgsfield ni descarga (fase 1)', async () => {
  await conCarpeta(async ({ dir, app, registro }) => {
    for (const extra of [{}, { image_briefs: [], images: {} }]) {
      const res = await post(app, retomarEjemplo(extra));
      assert.equal(res.status, 200);
      assert.deepEqual(registro.prompts, []);
      assert.deepEqual(registro.descargas, []);
      assert.deepEqual(res.body.images, { requested: 0, stored_local: 0, kept_remote: [], missing: [], reused: 0, urls: {} });
    }
    const html = await leer(dir, 'index.html');
    assert.ok(html.includes("url('https://example.com/portada.jpg')"), 'siguen las fotos del payload');
    assert.equal(await existe(dir, 'img'), false, 'no se creó la carpeta de fotos');
  });
});

test('un encargo que Higgsfield no entrega no tumba el render: queda la foto del payload', async () => {
  await conCarpeta(async ({ dir, app, registro }) => {
    const res = await post(app, retomarEjemplo({ image_briefs: [{ slide: 'portada', prompt: PROMPT_PORTADA }] }));
    assert.equal(res.status, 200);
    assert.deepEqual(res.body.images.missing, ['portada']);
    assert.deepEqual(res.body.images.urls, {}, 'no se anuncia una foto que no existe');
    assert.ok((await leer(dir, 'index.html')).includes("url('https://example.com/portada.jpg')"));
  }, { fallaHiggsfield: true });
});

test('pedidos inválidos devuelven 400 antes de tocar Higgsfield o el disco', async () => {
  await conCarpeta(async ({ dir, app, registro }) => {
    const malos = [
      retomarEjemplo({ image_briefs: [{ slide: 'otra', prompt: PROMPT_PORTADA }] }),
      retomarEjemplo({ image_briefs: [{ slide: 'portada', prompt: 'x'.repeat(1201) }] }),
      retomarEjemplo({ images: { otra: 'https://cdn.example.com/a.png' } }),
      retomarEjemplo({ recomendacion: 'a'.repeat(301) }),
    ];
    for (const malo of malos) {
      const res = await post(app, malo);
      assert.equal(res.status, 400);
      assert.equal(res.body.error, 'invalid payload');
      assert.ok(res.body.details.length > 0);
    }
    assert.deepEqual(registro.prompts, []);
    assert.equal(await existe(dir, ''), false, 'no se creó la carpeta del documento');
  });
});
