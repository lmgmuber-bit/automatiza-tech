const test = require('node:test');
const assert = require('node:assert/strict');
const { renderProposalHtml, renderClosingSlide, renderPricingBody } = require('../src/template');

const DATA = {
  company_name: 'Academia de Béisbol',
  client_name: 'Jeffer Garcia',
  challenge_title: 'Desafío',
  challenge_text: 'Texto',
  solution_title: 'Solución',
  solution_text: 'Texto',
  benefits: [{ title: 'A', text: 'B' }],
  how_it_works: [{ step_title: 'A', step_text: 'B' }],
  pricing_rows: [{ service: 'Sitio web', price_usd: 500, price_clp: 460000 }],
  next_steps: ['Aprobación'],
};

test('ships the deck controls and the navigation script', () => {
  const html = renderProposalHtml(DATA);
  assert.ok(html.includes('id="at-prev"'));
  assert.ok(html.includes('id="at-next"'));
  assert.ok(html.includes('id="at-counter"'));
  assert.ok(html.includes('id="at-toggle"'));
  assert.ok(html.includes('mode-deck'), 'deck mode class must be referenced');
});

test('slide-by-slide is the default view and the list view stays reachable', () => {
  const html = renderProposalHtml(DATA);
  // The deck is enabled from the script, not from static markup, so a
  // no-JS viewer (and the PDF pass) still gets the full stack of slides.
  assert.ok(html.includes('setMode(true)'));
  assert.ok(html.includes('Ver todo'));
  assert.ok(html.includes('Ver presentación'));
});

test('hides inactive slides only on screen, never forcing a display value', () => {
  const html = renderProposalHtml(DATA);
  // Regression guard: `.slide.is-active { display: block }` outranks
  // `.slide-closing { display: flex }` and collapses the closing slide's
  // two-column layout — in the browser AND in the PDF.
  assert.ok(html.includes('body.mode-deck .slide:not(.is-active) { display: none; }'));
  assert.ok(!/\.slide\.is-active\s*\{\s*display:\s*block/.test(html));
  assert.ok(!/@media print[\s\S]*?\.slide\s*\{[^}]*display:\s*block/.test(html));
});

test('the print stylesheet neutralises the on-screen zoom', () => {
  const html = renderProposalHtml(DATA);
  assert.ok(/@media print[\s\S]*?zoom:\s*1\s*!important/.test(html));
});

test('the closing slide carries the real AT logo, not a placeholder mark', () => {
  const html = renderClosingSlide();
  assert.ok(html.includes('closing-logo'));
  assert.ok(html.includes('logo-automatiza-tech'));
  assert.ok(!html.includes('closing-mark'), 'the fake "AT" circle must be gone');
});

test('every AutomatizaTech contact detail is a working link to its own channel', () => {
  const html = renderClosingSlide();
  assert.ok(html.includes('href="mailto:contacto@automatizatech.cl"'));
  assert.ok(html.includes('href="https://automatizatech.cl"'));
  assert.ok(html.includes('href="https://wa.me/56927002984"'));
  assert.ok(html.includes('href="https://instagram.com/automatizatech.cl"'));
  // Opened from inside ver-presentacion.php's iframe, so each one needs to
  // break out into its own tab rather than navigate the embedded frame.
  const anchors = html.match(/<a class="contact-row"[^>]*>/g) || [];
  assert.equal(anchors.length, 4);
  for (const a of anchors) {
    assert.ok(a.includes('target="_blank"'), 'contact links must open in a new tab');
    assert.ok(a.includes('rel="noopener noreferrer"'));
  }
});

test('content slides darken the side the copy sits on, not the bottom', () => {
  const img = 'https://example.com/photo.jpg';
  const html = renderProposalHtml(DATA, { how_it_works: img });
  // The original bottom-heavy scrim (.15 at the top) left the top-left
  // text of every content slide over the brightest part of the photo.
  assert.ok(html.includes('linear-gradient(100deg, rgba(13,27,42,.97)'));
});

test('a row without price_clp is priced in dollars only', () => {
  const html = renderPricingBody(
    [{ service: 'Anticipo para iniciar (40%)', price_usd: 200 }],
    'Nota'
  );
  assert.ok(html.includes('$200 USD'));
  assert.ok(!html.includes('CLP'), 'a client billed abroad must not see pesos');
});

test('a row with price_clp still shows the peso conversion', () => {
  const html = renderPricingBody(
    [{ service: 'Sitio web', price_usd: 500, price_clp: 460000 }],
    null
  );
  assert.ok(html.includes('$500 USD ($460000 CLP aprox)'));
});

test('a launch price can strike through the list price and highlight the total', () => {
  const html = renderPricingBody(
    [
      { service: 'Valor normal del proyecto', price_usd: 1500, strike: true },
      { service: 'Descuento por lanzamiento', price_label: '−$1.000 USD' },
      { service: 'Inversión total', price_usd: 500, emphasis: true },
    ],
    null
  );
  assert.ok(html.includes('class="is-struck"'));
  assert.ok(html.includes('is-total'));
  assert.ok(html.includes('−$1.000 USD'), 'an explicit label overrides the numeric price');
  assert.ok(html.includes('$1500 USD'));
  assert.ok(html.includes('$500 USD'));
});

test('an ordinary row carries no styling classes', () => {
  const html = renderPricingBody([{ service: 'Sitio web', price_usd: 500 }], null);
  assert.ok(html.includes('<tr class="">'));
  assert.ok(!html.includes('is-struck'));
  assert.ok(!html.includes('is-total'));
});

test('the next-steps slide takes a background photo like the rest', () => {
  const img = 'https://example.com/next.jpg';
  const html = renderProposalHtml(DATA, { next_steps: img });
  assert.ok(html.includes("url('https://example.com/next.jpg')"));
});

test('offers fullscreen by tap, never claiming to trigger it on its own', () => {
  const html = renderProposalHtml(DATA);
  assert.ok(html.includes('id="at-full"'));
  assert.ok(html.includes('id="at-chip"'));
  assert.ok(html.includes('id="at-rotate-full"'));
  // Browsers only grant fullscreen inside a user gesture, so every entry
  // point has to hang off a click handler.
  assert.ok(html.includes("chip.addEventListener('click'"));
  assert.ok(html.includes("rotateFull.addEventListener('click'"));
  assert.ok(!/orientationchange[\s\S]{0,120}enterFullscreen\(\)/.test(html));
});

test('the closing slide offers the PDF as a download, hidden from the PDF itself', () => {
  const html = renderProposalHtml(DATA);
  assert.ok(html.includes('class="pdf-button" href="presentation.pdf" download'));
  // Relative, so it survives any base URL the renderer is served from.
  assert.ok(!html.includes('href="https://n8n-propuesta-renderer'));
  assert.ok(/@media print[\s\S]*?\.pdf-button[\s\S]*?display: none !important/.test(html));
});

test('the watermark is legible, not the 4% video-grade mark', () => {
  const html = renderProposalHtml(DATA);
  assert.ok(html.includes('.at-watermark img { width: 190px'));
  assert.ok(!html.includes('width: 76px'));
});

test('the cover keeps its bottom-heavy scrim', () => {
  const img = 'https://example.com/photo.jpg';
  const html = renderProposalHtml(DATA, { cover: img });
  assert.ok(html.includes('linear-gradient(180deg, rgba(13,27,42,.15)'));
});
