const test = require('node:test');
const assert = require('node:assert/strict');
const { renderProposalHtml } = require('../src/template');

const DATA = {
  company_name: 'Academia de Béisbol X',
  client_name: 'Jeffer Garcia',
  challenge_title: 'Gestión manual que no escala',
  challenge_text: 'Texto de <b>desafío</b>',
  solution_title: 'Sitio + asistente virtual',
  solution_text: 'Texto de solución',
  benefits: [{ title: 'Ahorro de tiempo', text: 'Menos WhatsApp manual' }],
  how_it_works: [{ step_title: 'Kick-off', step_text: 'Reunión inicial' }],
  pricing_rows: [{ service: 'Sitio web', price_usd: 500, price_clp: 460000 }],
  pricing_note: 'Precio referencial',
  next_steps: ['Aprobación', 'Kick-off'],
};

test('includes company and client name', () => {
  const html = renderProposalHtml(DATA, {});
  assert.ok(html.includes('Academia de Béisbol X'));
  assert.ok(html.includes('Jeffer Garcia'));
});

test('escapes html found inside AI-generated text', () => {
  const html = renderProposalHtml(DATA, {});
  assert.ok(!html.includes('Texto de <b>desafío</b>'));
  assert.ok(html.includes('Texto de &lt;b&gt;desafío&lt;/b&gt;'));
});

test('renders exactly 8 slide sections', () => {
  const html = renderProposalHtml(DATA, {});
  const count = (html.match(/<section class="slide/g) || []).length;
  assert.equal(count, 8);
});

test('uses the real AutomatizaTech logo url as watermark', () => {
  const html = renderProposalHtml(DATA, {});
  assert.ok(html.includes('logo-automatiza-tech'));
  assert.ok(html.includes('at-watermark'));
});

test('falls back to a brand gradient when no image is provided', () => {
  const html = renderProposalHtml(DATA, {});
  assert.ok(html.includes('linear-gradient(135deg, #0d1b2a'));
});

test('uses a provided image url as the slide background', () => {
  const html = renderProposalHtml(DATA, { cover: 'https://example.com/photo.jpg' });
  assert.ok(html.includes("url('https://example.com/photo.jpg')"));
});

test('never sets X-Frame-Options style meta tags in the markup', () => {
  const html = renderProposalHtml(DATA, {});
  assert.ok(!html.toLowerCase().includes('x-frame-options'));
});

// Helper: count non-overlapping occurrences of `needle` in `haystack`.
function countOccurrences(haystack, needle) {
  return haystack.split(needle).length - 1;
}

test('escapes company_name everywhere it is rendered: page title, cover h1, and benefits slide heading', () => {
  const data = { ...DATA, company_name: 'Empresa <b>Increíble</b> & Cía' };
  const html = renderProposalHtml(data, {});
  const raw = 'Empresa <b>Increíble</b> & Cía';
  const escaped = 'Empresa &lt;b&gt;Increíble&lt;/b&gt; &amp; Cía';

  assert.ok(!html.includes(raw), 'raw markup must never appear');
  // Expected 3 occurrences: <title>, cover <h1>, and "Lo que gana {company_name}" heading.
  assert.equal(countOccurrences(html, escaped), 3);
  assert.ok(html.includes(`<title>Propuesta AutomatizaTech · ${escaped}</title>`));
  assert.ok(html.includes(`<h1>${escaped}</h1>`));
  assert.ok(html.includes(`<h2>Lo que gana ${escaped}</h2>`));
});

test('escapes client_name in the cover slide lede', () => {
  const data = { ...DATA, client_name: 'Cliente <img src=x onerror=alert(1)>' };
  const html = renderProposalHtml(data, {});
  assert.ok(!html.includes('Cliente <img src=x onerror=alert(1)>'));
  assert.ok(
    html.includes('Preparado para Cliente &lt;img src=x onerror=alert(1)&gt;')
  );
});

test('escapes challenge_title in the challenge slide heading', () => {
  const data = { ...DATA, challenge_title: 'Título <script>alert(1)</script>' };
  const html = renderProposalHtml(data, {});
  assert.ok(!html.includes('Título <script>alert(1)</script>'));
  assert.ok(
    html.includes('<h2>Título &lt;script&gt;alert(1)&lt;/script&gt;</h2>')
  );
});

test('escapes solution_title and solution_text in the solution slide', () => {
  const data = {
    ...DATA,
    solution_title: 'Solución <b>Total</b>',
    solution_text: 'Detalle <i>de la solución</i>',
  };
  const html = renderProposalHtml(data, {});
  assert.ok(!html.includes('Solución <b>Total</b>'));
  assert.ok(!html.includes('Detalle <i>de la solución</i>'));
  assert.ok(html.includes('<h2>Solución &lt;b&gt;Total&lt;/b&gt;</h2>'));
  assert.ok(
    html.includes(
      '<p class="body-text">Detalle &lt;i&gt;de la solución&lt;/i&gt;</p>'
    )
  );
});

test('escapes benefits[].title and benefits[].text', () => {
  const data = {
    ...DATA,
    benefits: [
      { title: 'Beneficio <b>Uno</b>', text: 'Explicación <i>uno</i>' },
      { title: 'Beneficio <script>x</script>', text: 'Explicación <u>dos</u>' },
    ],
  };
  const html = renderProposalHtml(data, {});
  assert.ok(!html.includes('Beneficio <b>Uno</b>'));
  assert.ok(!html.includes('Explicación <i>uno</i>'));
  assert.ok(!html.includes('Beneficio <script>x</script>'));
  assert.ok(!html.includes('Explicación <u>dos</u>'));
  assert.ok(
    html.includes(
      '<strong>Beneficio &lt;b&gt;Uno&lt;/b&gt;:</strong> Explicación &lt;i&gt;uno&lt;/i&gt;'
    )
  );
  assert.ok(
    html.includes(
      '<strong>Beneficio &lt;script&gt;x&lt;/script&gt;:</strong> Explicación &lt;u&gt;dos&lt;/u&gt;'
    )
  );
});

test('escapes how_it_works[].step_title and how_it_works[].step_text', () => {
  const data = {
    ...DATA,
    how_it_works: [
      { step_title: 'Paso <b>Uno</b>', step_text: 'Hacemos <i>cosas</i>' },
    ],
  };
  const html = renderProposalHtml(data, {});
  assert.ok(!html.includes('Paso <b>Uno</b>'));
  assert.ok(!html.includes('Hacemos <i>cosas</i>'));
  assert.ok(
    html.includes(
      '<strong>Paso &lt;b&gt;Uno&lt;/b&gt;:</strong> Hacemos &lt;i&gt;cosas&lt;/i&gt;'
    )
  );
});

test('escapes pricing_rows[].service and pricing_note; numeric price_usd/price_clp render without throwing', () => {
  const data = {
    ...DATA,
    pricing_rows: [
      { service: 'Servicio <b>Premium</b>', price_usd: 1200, price_clp: 1104000 },
    ],
    pricing_note: 'Nota <script>alert(1)</script>',
  };
  assert.doesNotThrow(() => renderProposalHtml(data, {}));
  const html = renderProposalHtml(data, {});
  assert.ok(!html.includes('Servicio <b>Premium</b>'));
  assert.ok(!html.includes('Nota <script>alert(1)</script>'));
  assert.ok(html.includes('<td>Servicio &lt;b&gt;Premium&lt;/b&gt;</td>'));
  assert.ok(html.includes('$1200 USD ($1104000 CLP aprox)'));
  assert.ok(
    html.includes(
      '<p class="pricing-note">Nota &lt;script&gt;alert(1)&lt;/script&gt;</p>'
    )
  );
});

test('escapes each item in next_steps', () => {
  const data = {
    ...DATA,
    next_steps: ['Paso <b>Uno</b>', 'Paso <i>Dos</i>'],
  };
  const html = renderProposalHtml(data, {});
  assert.ok(!html.includes('Paso <b>Uno</b>'));
  assert.ok(!html.includes('Paso <i>Dos</i>'));
  assert.ok(html.includes('<li>Paso &lt;b&gt;Uno&lt;/b&gt;</li>'));
  assert.ok(html.includes('<li>Paso &lt;i&gt;Dos&lt;/i&gt;</li>'));
});
