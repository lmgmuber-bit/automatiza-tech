const test = require('node:test');
const assert = require('node:assert/strict');
const { renderRetomarHtml } = require('../src/template-retomar');
const { validateRetomarPayload } = require('../src/schema');
const { retomarEjemplo } = require('./fixtures/retomar-ejemplo');

test('retomar valida y dibuja exactamente seis láminas en orden', () => {
  const d = retomarEjemplo();
  assert.deepEqual(validateRetomarPayload(d), { ok: true, valid: true, errors: [] });
  const html = renderRetomarHtml(d);
  assert.equal((html.match(/<section class="slide/g) || []).length, 6);
  let pos = 0;
  for (const titulo of ['[PRUEBA] Negocio de ejemplo', 'En qué quedamos', 'Tu demo', 'Lo que proponemos', 'Podemos partir por lo prioritario y ajustarnos a tu presupuesto', 'Agendemos 20 min']) {
    const next = html.indexOf(titulo, pos); assert.ok(next >= pos, titulo); pos = next + titulo.length;
  }
  assert.ok(html.includes('Si algo no calza con lo que necesitas o con tu presupuesto, lo ajustamos y partimos por lo más importante.'));
  assert.ok(html.includes('href="https://example.com/agenda"'));
  assert.ok(html.includes('src="https://example.com/demo.png"'));
});

test('solo https se vuelve enlace, imagen o fondo y todos los textos se escapan', () => {
  const html = renderRetomarHtml(retomarEjemplo({ company_name: '<script>ataque</script>',
    demo_urls: ['http://example.com/inseguro', 'javascript:alert(1)', 'https://example.com/demo'],
    demo_imagen: 'http://example.com/demo.png', propuesta_url: 'javascript:alert(1)',
    agenda: { web_url: 'http://example.com/agenda' }, fotos: { portada: 'javascript:alert(1)', cierre: 'http://example.com/cierre' } }));
  assert.ok(html.includes('&lt;script&gt;ataque&lt;/script&gt;'));
  assert.ok(!html.includes('<script>ataque</script>'));
  assert.ok(!html.includes('javascript:') && !html.includes('http://example.com'));
  assert.ok(html.includes('href="https://example.com/demo"'));
  assert.ok(renderRetomarHtml(retomarEjemplo({ partir: 'Partamos por la agenda.' })).includes('Partamos por la agenda.'));
});

test('validación rechaza cuerpo inválido y unique_id inseguro antes de tocar disco', () => {
  for (const bad of [null, [], {}, retomarEjemplo({ unique_id: '../escape' }), retomarEjemplo({ demo_urls: {} })]) {
    assert.equal(validateRetomarPayload(bad).ok, false);
  }
  const largo = validateRetomarPayload(retomarEjemplo({ quedamos: 'X'.repeat(601) }));
  assert.equal(largo.ok, false); assert.match(largo.errors.join(' '), /quedamos admite hasta 600/);
});

test('archivos HTML/PDF de UID retomar llevan noindex y no filtran referencia sin afectar otros documentos', async () => {
  const { createApp } = require('../src/server');
  const server = createApp({ publicDir: __dirname, baseUrl: 'https://example.com', higgsfieldCredentials: {} }).listen(0, '127.0.0.1');
  await new Promise((resolve) => server.once('listening', resolve));
  try {
    for (const file of ['ejemplo123-retomar/index.html', 'ejemplo123-retomar/presentation.pdf', 'x-retomar/', 'x-retomar/presentation.pdf',
      'ejemplo123-retomar/%70resentation.pdf', 'ejemplo123%2Dretomar/presentation.pdf', 'ejemplo123%2Dretomar/%69ndex.html',
      'ejemplo123-retomar/otra/%2e%2e%2fpresentation.pdf', 'ejemplo123-retomar%5Cpresentation.pdf',
      'ejemplo123-RETOMAR/PRESENTATION.PDF']) {
      const r = await fetch(`http://127.0.0.1:${server.address().port}/p/${file}`);
      assert.equal(r.status, 404, 'el middleware se verifica sin crear archivos');
      assert.equal(r.headers.get('x-robots-tag'), 'noindex, nofollow');
      assert.equal(r.headers.get('referrer-policy'), 'no-referrer');
    }
    const other = await fetch(`http://127.0.0.1:${server.address().port}/p/ejemplo123-estado/presentation.pdf`);
    assert.equal(other.headers.get('x-robots-tag'), null);
    assert.equal(other.headers.get('referrer-policy'), null);
    const malformed = await fetch(`http://127.0.0.1:${server.address().port}/p/ejemplo123-retomar/%zz`);
    assert.equal(malformed.status, 404);
  } finally { await new Promise((resolve) => server.close(resolve)); }
});

test('POST /render despacha retomar sin llamar imágenes aunque llegue image_briefs', async () => {
  const modules = ['higgsfield', 'images-store', 'photo-manifest', 'render'];
  const originals = modules.map((m) => { const p = require.resolve(`../src/${m}`); require(p); return [p, require.cache[p].exports]; });
  let html = ''; let calls = 0;
  require.cache[require.resolve('../src/higgsfield')].exports = { generateProposalImages: async () => { calls++; throw new Error('no debe generar'); } };
  require.cache[require.resolve('../src/images-store')].exports = { persistImages: async () => { throw new Error('no debe persistir imágenes'); } };
  require.cache[require.resolve('../src/photo-manifest')].exports = { readManifest: async () => { throw new Error('no debe consultar manifiesto'); } };
  require.cache[require.resolve('../src/render')].exports = { renderToFiles: async (value) => { html = value; } };
  const serverPath = require.resolve('../src/server'); delete require.cache[serverPath];
  const { createApp } = require('../src/server');
  const server = createApp({ publicDir: __dirname, baseUrl: 'https://example.com', higgsfieldCredentials: {} }).listen(0, '127.0.0.1');
  await new Promise((resolve) => server.once('listening', resolve));
  try {
    const post = (d) => fetch(`http://127.0.0.1:${server.address().port}/render`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(d) });
    const r = await post(retomarEjemplo({ image_briefs: [{ slide: 'cover', prompt: 'no generar' }] }));
    assert.equal(r.status, 200); assert.equal(calls, 0); assert.match(html, /En qué quedamos/);
    assert.equal((await r.json()).images.requested, 0);
    assert.equal((await post(retomarEjemplo({ unique_id: '../escape' }))).status, 400);
  } finally {
    await new Promise((resolve) => server.close(resolve));
    for (const [p, exports] of originals) require.cache[p].exports = exports;
    delete require.cache[serverPath];
  }
});
