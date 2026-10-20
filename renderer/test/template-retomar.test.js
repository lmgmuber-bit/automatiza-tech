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

const seccion = (html, n) => html.split('<section')[n];

test('images de la fase 2 ganan sobre fotos y demo_imagen; sin ellas rige lo de siempre', () => {
  const html = renderRetomarHtml(retomarEjemplo(), { portada: 'img/p.jpg', cierre: 'img/c.png', demo: 'img/d.webp' });
  const portada = seccion(html, 1);
  assert.ok(portada.includes("url('img/p.jpg')"), 'la portada usa la foto generada');
  assert.ok(!portada.includes('https://example.com/portada.jpg'), 'y no la de fotos.portada');
  assert.ok(seccion(html, 6).includes("url('img/c.png')") && !seccion(html, 6).includes('https://example.com/cierre.jpg'));
  const demo = seccion(html, 3);
  assert.ok(demo.includes('src="img/d.webp"') && !demo.includes('https://example.com/demo.png'), 'images.demo reemplaza demo_imagen');
  // Sin images (o con valores vacíos) se conservan las fotos del payload, como en la fase 1.
  for (const vacio of [undefined, {}, { portada: '', cierre: null, demo: undefined }]) {
    const igual = renderRetomarHtml(retomarEjemplo(), vacio);
    assert.ok(seccion(igual, 1).includes("url('https://example.com/portada.jpg')"));
    assert.ok(seccion(igual, 6).includes("url('https://example.com/cierre.jpg')"));
    assert.ok(seccion(igual, 3).includes('src="https://example.com/demo.png"'));
  }
});

test('images solo acepta img/<archivo> o https: el resto cae a las fotos del payload', () => {
  const html = renderRetomarHtml(retomarEjemplo(), { portada: 'img/../../secreto.png', cierre: 'http://example.com/c.jpg', demo: 'javascript:alert(1)' });
  assert.ok(seccion(html, 1).includes("url('https://example.com/portada.jpg')"));
  assert.ok(seccion(html, 6).includes("url('https://example.com/cierre.jpg')"));
  assert.ok(seccion(html, 3).includes('src="https://example.com/demo.png"'));
  assert.ok(!html.includes('secreto.png') && !html.includes('javascript:') && !html.includes('http://example.com'));
  // Una foto que quedó remota por https también se acepta.
  assert.ok(renderRetomarHtml(retomarEjemplo(), { portada: 'https://cdn.example.com/p.png' }).includes("url('https://cdn.example.com/p.png')"));
});

test('recomendacion aparece en la lámina «Para partir», escapada, y solo ahí', () => {
  const sin = renderRetomarHtml(retomarEjemplo());
  assert.ok(!sin.includes('class="retomar-reco"'));
  const html = renderRetomarHtml(retomarEjemplo({ recomendacion: 'Partamos por la agenda <b>y</b> sumemos lo demás después.' }));
  const partir = seccion(html, 5);
  assert.ok(partir.includes('Partamos por la agenda &lt;b&gt;y&lt;/b&gt; sumemos lo demás después.'));
  assert.ok(!html.includes('<b>y</b>'));
  assert.equal((html.match(/Partamos por la agenda/g) || []).length, 1, 'una sola vez, en la lámina 5');
  assert.ok(partir.includes('class="retomar-reco"'));
  // Una recomendación vacía o solo espacios no dibuja el recuadro.
  assert.ok(!renderRetomarHtml(retomarEjemplo({ recomendacion: '   ' })).includes('class="retomar-reco"'));
});

test('validación de image_briefs, images y recomendacion', () => {
  const ok = (d) => assert.deepEqual(validateRetomarPayload(d).errors, [], JSON.stringify(d).slice(0, 80));
  const mal = (d, re) => { const r = validateRetomarPayload(d); assert.equal(r.ok, false); assert.match(r.errors.join(' | '), re); };
  ok(retomarEjemplo());
  ok(retomarEjemplo({ image_briefs: [], images: {}, recomendacion: '' }));
  ok(retomarEjemplo({ image_briefs: ['portada', 'quedamos', 'cierre', 'demo'].map((slide) => ({ slide, prompt: 'x'.repeat(1200) })) }));
  ok(retomarEjemplo({ images: { quedamos: 'https://example.com/q.png' } }));
  ok(retomarEjemplo({ images: { portada: 'https://example.com/p.png', demo: 'http://127.0.0.1:3000/p/x/img/demo.png' } }));
  ok(retomarEjemplo({ recomendacion: 'a'.repeat(300) }));
  ok(retomarEjemplo({ recomendacion: 'uno\ndos\ntres' }));
  mal(retomarEjemplo({ image_briefs: [{ slide: 'otra', prompt: 'foto' }] }), /image_briefs\[0\]\.slide/);
  mal(retomarEjemplo({ image_briefs: [{ slide: 'cover', prompt: 'foto' }] }), /image_briefs\[0\]\.slide/);
  mal(retomarEjemplo({ image_briefs: [{ slide: 'portada', prompt: 'x'.repeat(1201) }] }), /image_briefs\[0\]\.prompt/);
  mal(retomarEjemplo({ image_briefs: [{ slide: 'portada', prompt: '   ' }] }), /image_briefs\[0\]\.prompt/);
  mal(retomarEjemplo({ image_briefs: [{ slide: 'portada' }] }), /image_briefs\[0\]\.prompt/);
  mal(retomarEjemplo({ image_briefs: [null] }), /image_briefs\[0\]/);
  mal(retomarEjemplo({ image_briefs: {} }), /image_briefs debe ser un arreglo/);
  mal(retomarEjemplo({ image_briefs: [{ slide: 'portada', prompt: 'a' }, { slide: 'portada', prompt: 'b' }] }), /portada.*repet|repet.*portada/);
  mal(retomarEjemplo({ image_briefs: [1, 2, 3, 4, 5].map((n) => ({ slide: 'portada', prompt: `p${n}` })) }), /hasta 4/);
  mal(retomarEjemplo({ images: { otra: 'https://example.com/p.png' } }), /images\.otra/);
  mal(retomarEjemplo({ images: { portada: 'ftp://example.com/p.png' } }), /images\.portada/);
  mal(retomarEjemplo({ images: { portada: 5 } }), /images\.portada/);
  mal(retomarEjemplo({ images: [] }), /images debe ser un objeto/);
  mal(retomarEjemplo({ recomendacion: 'a'.repeat(301) }), /recomendacion admite hasta 300 caracteres y 3 líneas/);
  mal(retomarEjemplo({ recomendacion: 'a\nb\nc\nd' }), /recomendacion admite hasta 300 caracteres y 3 líneas/);
  mal(retomarEjemplo({ recomendacion: 7 }), /recomendacion debe ser texto/);
});

test('POST /render despacha retomar sin llamar imágenes cuando no trae image_briefs ni images', async () => {
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
    for (const extra of [{}, { image_briefs: [], images: {} }]) {
      const r = await post(retomarEjemplo(extra));
      assert.equal(r.status, 200); assert.equal(calls, 0); assert.match(html, /En qué quedamos/);
      const cuerpo = await r.json();
      assert.equal(cuerpo.images.requested, 0);
      assert.deepEqual(cuerpo.images.urls, {});
    }
    // Un encargo de una lámina que no existe ya no se ignora: es un pedido inválido y no toca nada.
    assert.equal((await post(retomarEjemplo({ image_briefs: [{ slide: 'cover', prompt: 'no generar' }] }))).status, 400);
    assert.equal(calls, 0);
    assert.equal((await post(retomarEjemplo({ unique_id: '../escape' }))).status, 400);
  } finally {
    await new Promise((resolve) => server.close(resolve));
    for (const [p, exports] of originals) require.cache[p].exports = exports;
    delete require.cache[serverPath];
  }
});

test('la lámina 2 usa la foto «quedamos» de fondo con el mismo degradado; sin ella queda el degradado de siempre', () => {
  const con = renderRetomarHtml(retomarEjemplo(), { quedamos: 'img/q.jpg' });
  assert.ok(seccion(con, 2).includes("url('img/q.jpg')"), 'images.quedamos es el fondo de la lámina 2');
  assert.ok(!seccion(con, 1).includes('img/q.jpg') && !seccion(con, 3).includes('img/q.jpg'), 'y no se cuela en otras láminas');
  const sin = renderRetomarHtml(retomarEjemplo(), {});
  assert.ok(!seccion(sin, 2).includes('url('), 'sin foto, la lámina 2 conserva el degradado');
  const fondoCon = seccion(con, 2).match(/class="slide-bg" style="([^"]*)"/)[1];
  const fondoPortada = seccion(renderRetomarHtml(retomarEjemplo(), { portada: 'img/q.jpg' }), 1).match(/class="slide-bg" style="([^"]*)"/)[1];
  assert.equal(fondoCon.replace(/linear-gradient\([^)]*\)/g, 'G'), fondoPortada.replace(/linear-gradient\([^)]*\)/g, 'G'), 'mismo tratamiento que la portada');
  assert.match(renderRetomarHtml(retomarEjemplo(), { quedamos: 'javascript:alert(1)' }), /<section/, 'una URL insegura no rompe');
  assert.ok(!seccion(renderRetomarHtml(retomarEjemplo(), { quedamos: 'javascript:alert(1)' }), 2).includes('javascript:'), 'ni se usa como fondo');
});

test('la regla de la imagen de la demo no alcanza al logo de la marca de agua', () => {
  const html = renderRetomarHtml(retomarEjemplo({ demo_imagen: 'https://example.com/demo.png' }));
  assert.ok(!/\.retomar-demo img\s*\{/.test(html), 'ya no hay una regla genérica sobre img de la lámina de la demo');
  assert.match(html, /\.retomar-demo \.retomar-demo-img\s*\{/, 'la regla apunta solo a la imagen de la demo');
  const demo = seccion(html, 3);
  assert.equal((demo.match(/<img /g) || []).length, 2, 'la lámina trae la marca de agua y la imagen de la demo');
  assert.ok(/<img class="retomar-demo-img" src="https:\/\/example\.com\/demo\.png"/.test(demo), 'la imagen de la demo lleva la clase propia');
  assert.ok(/<div class="at-watermark"><img (?!class="retomar-demo-img")/.test(demo), 'y el logo no la lleva');
});

test('las fotos de Retomar se encuadran desde arriba (no cortan cabezas) y propuesta, plan y estado siguen centrados', () => {
  const html = renderRetomarHtml(retomarEjemplo(), { portada: 'img/p.jpg', quedamos: 'img/q.jpg', cierre: 'img/c.jpg' });
  for (const n of [1, 2, 6]) {
    const estilo = seccion(html, n).match(/class="slide-bg" style="([^"]*)"/)[1];
    assert.match(estilo, /url\('img\/[pqc]\.jpg'\)/, `lámina ${n} con foto`);
    assert.match(estilo, /background-position: center;.*background-position: center top;$/, `lámina ${n}: la última declaración ancla la foto arriba`);
  }
  assert.match(html, /\.retomar-panel > \.slide-bg, \.retomar-cover > \.slide-bg \{ transform-origin: center top; \}/, 'el acercamiento lento crece hacia abajo');
  const sinFoto = seccion(renderRetomarHtml(retomarEjemplo(), {}), 2).match(/class="slide-bg" style="([^"]*)"/)[1];
  assert.ok(!sinFoto.includes('background-position'), 'sin foto queda solo el degradado');
  const { backgroundStyle } = require('../src/template');
  assert.ok(!backgroundStyle('https://example.com/f.jpg', 0, 'cover').includes('top'), 'el estilo compartido (propuesta, plan, estado) no cambia');
});
