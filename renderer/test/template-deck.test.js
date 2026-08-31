const test = require('node:test');
const assert = require('node:assert/strict');
const { renderProposalHtml, renderClosingSlide } = require('../src/template');

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

test('the cover keeps its bottom-heavy scrim', () => {
  const img = 'https://example.com/photo.jpg';
  const html = renderProposalHtml(DATA, { cover: img });
  assert.ok(html.includes('linear-gradient(180deg, rgba(13,27,42,.15)'));
});
