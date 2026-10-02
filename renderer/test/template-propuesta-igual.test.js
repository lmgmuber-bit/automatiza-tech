const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const template = require('../src/template');

// Guardia del plan de trabajo: template.js solo AGREGA exportaciones para template-plan.js, así que el
// HTML de una propuesta tiene que salir byte a byte igual que antes. Las huellas se sacaron del
// template.js de origin/main (commit 86021c1) antes de tocarlo. Se quitan los \r por si el archivo se
// sacó con CRLF (core.autocrlf en Windows); los template literals de JS ya los normalizan a \n.
const BASE = {
  company_name: 'Cliente Prueba SpA',
  client_name: 'Cliente Prueba',
  challenge_title: 'Desafío de prueba',
  challenge_text: 'Texto con <b>marcas</b> y https://automatizatech.cl/demo (enlace).',
  solution_title: 'Solución de prueba',
  solution_text: 'Texto de solución',
  benefits: [{ title: 'Ahorro', text: 'Menos trabajo manual' }],
  how_it_works: [{ step_title: 'Inicio', step_text: 'Reunión inicial' }],
  pricing_rows: [
    { service: 'Sitio web', price_usd: 500, price_clp: 460000 },
    { service: 'Total', price_usd: 500, emphasis: true },
  ],
  pricing_note: 'Precio de prueba',
  next_steps: ['Aprobación', 'Inicio'],
};

function huella(html) {
  return crypto.createHash('sha256').update(html.replace(/\r\n/g, '\n'), 'utf8').digest('hex');
}

test('la propuesta final sale byte a byte igual que antes del plan de trabajo', () => {
  assert.equal(huella(template.renderProposalHtml(BASE, {})), 'd6eeb3845919cb90867fe38f195a03d2336dab6db29953d04ca689faac51a4e5');
});

test('el borrador con lámina extra y fotos también sale igual', () => {
  const data = { ...BASE, draft: true, extra_slides: [{ eyebrow: 'Alcance', title: 'Qué incluye', text: 'Detalle' }] };
  const images = { cover: 'https://example.com/portada.jpg', next_steps: 'img/next_steps.jpg' };
  assert.equal(huella(template.renderProposalHtml(data, images)), '38a644cf40fc0f07f570e22664f6d2a71450fddd3a506c50e16acb35476bb168');
});

test('template.js comparte con el plan su estilo, script, logo y controles', () => {
  assert.equal(typeof template.STYLE, 'string');
  assert.ok(template.STYLE.includes('.at-watermark'));
  assert.equal(typeof template.SCRIPT, 'string');
  assert.ok(template.SCRIPT.includes("type: 'at-deck-lamina'"));
  assert.ok(template.LOGO_URL.includes('logo-automatiza-tech'));
  assert.equal(template.CONTACTS.length, 4);
  assert.ok(template.CONTACT_ICONS.whatsapp.startsWith('<svg'));
  assert.ok(template.CONTACT_ICONS.web.startsWith('<svg'));
  assert.ok(template.backgroundStyle('', 0).startsWith('background: linear-gradient(135deg, #0d1b2a'));
  assert.ok(template.logoMark().includes('class="at-watermark"'));
  assert.ok(template.renderDeckControls().includes('id="at-prev"'));
});
