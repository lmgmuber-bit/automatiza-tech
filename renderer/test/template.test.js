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
