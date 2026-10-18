const { escapeHtml } = require('./escape');
const { STYLE, SCRIPT, backgroundStyle, logoMark, renderDeckControls } = require('./template');
const { urlSegura, ESTILO_PORTADA } = require('./template-plan');

const PARTIR_DEFAULT = 'Si algo no calza con lo que necesitas o con tu presupuesto, lo ajustamos y partimos por lo más importante.';
const texto = (v) => escapeHtml(typeof v === 'string' ? v : '');
const enlace = (url, label, clase = '') => {
  const safe = urlSegura(url);
  return safe ? `<a class="retomar-link ${clase}" href="${escapeHtml(safe)}" target="_top" rel="noopener">${texto(label)}</a>` : '';
};

const ESTILO_RETOMAR = `
  .retomar-head { position: absolute; left: 300px; top: 66px; right: 100px; z-index: 2; }
  .retomar-head h2 { color: #fff; font-size: 52px; line-height: 1.18; margin-top: 12px; overflow-wrap: anywhere; }
  .retomar-content { position: absolute; left: 100px; right: 100px; top: 320px; bottom: 110px; z-index: 2; }
  .retomar-copy { color: #e1eaf2; font-size: 32px; line-height: 1.5; overflow-wrap: anywhere; }
  .retomar-link { display: inline-block; color: #00d9c0; font-size: 28px; line-height: 1.3; padding: 18px 26px; border: 1px solid #00d9c0; border-radius: 12px; text-decoration: none; }
  .retomar-links { display: flex; flex-wrap: wrap; gap: 18px; margin-top: 30px; }
  .retomar-demo .retomar-content { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 44px; }
  .retomar-demo img { width: 100%; height: 600px; object-fit: contain; object-position: top center; border-radius: 18px; background: rgba(0,0,0,.2); }
  .retomar-demo .retomar-links { margin: 0; align-content: start; flex-direction: column; }
  .retomar-demo .retomar-link { font-size: 26px; }
  .retomar-cover .slide-body { left: 100px; right: 100px; bottom: 130px; }
  .retomar-cover .lede { font-size: 32px; line-height: 1.45; overflow-wrap: anywhere; }
  .retomar-cover h1 { overflow-wrap: anywhere; }
`;

// Reutiliza marca y controles del renderer. Solo fotos existentes del payload; nunca genera imágenes.
function renderRetomarHtml(datos, opciones = {}) {
  const d = datos && typeof datos === 'object' ? datos : {};
  const fotos = d.fotos && typeof d.fotos === 'object' ? d.fotos : {};
  const fondo = (url, index) => `<div class="slide-bg" style="${backgroundStyle(urlSegura(url), index, 'cover')}"></div>`;
  const panel = (n, title, content, clase = '', photo = '') => `<section class="slide retomar-panel ${clase}">${fondo(photo, n)}${logoMark()}
    <div class="retomar-head"><p class="eyebrow">${n} · Retomemos la conversación</p><h2>${texto(title)}</h2></div>
    <div class="retomar-content">${content}</div></section>`;
  const demos = (Array.isArray(d.demo_urls) ? d.demo_urls : []).filter((u) => urlSegura(u));
  const imagen = urlSegura(d.demo_imagen);
  const slides = [
    `<section class="slide slide-cover plan-cover retomar-cover">${fondo(fotos.portada, 0)}${logoMark()}
      <div class="slide-body"><p class="eyebrow">Retomemos la conversación</p><h1>${texto(d.company_name)}</h1>
      <p class="lede">${texto(d.proyecto)}</p><p class="lede">${texto(d.client_name)}</p><div class="accent-bar"></div></div></section>`,
    panel(2, 'En qué quedamos', `<p class="retomar-copy">${texto(d.quedamos)}</p>`),
    panel(3, 'Tu demo', `${imagen ? `<img src="${escapeHtml(imagen)}" alt="Vista previa de tu demo" />` : '<p class="retomar-copy">Revisemos juntos la demo.</p>'}
      <div class="retomar-links">${demos.map((u, i) => enlace(u, `Ver demo${demos.length > 1 ? ` ${i + 1}` : ''}`)).join('')}</div>`, 'retomar-demo'),
    panel(4, 'Lo que proponemos', `<p class="retomar-copy">${texto(d.proponemos)}</p><div class="retomar-links">${enlace(d.propuesta_url, 'Ver propuesta')}</div>`),
    panel(5, 'Podemos partir por lo prioritario y ajustarnos a tu presupuesto', `<p class="retomar-copy">${texto(typeof d.partir === 'string' && d.partir.trim() ? d.partir : PARTIR_DEFAULT)}</p>`),
    panel(6, 'Agendemos 20 min', `<p class="retomar-copy">Conversemos en equipo y definamos el próximo paso.</p><div class="retomar-links">${enlace(d.agenda && d.agenda.web_url, 'Agendar 20 min')}</div>`, 'retomar-cierre', fotos.cierre),
  ];
  return `<!doctype html><html lang="es-CL"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex, nofollow" /><title>Retomemos · ${texto(d.company_name)}</title>
    <style>${STYLE}${ESTILO_PORTADA}${ESTILO_RETOMAR}</style></head><body><div class="deck">${slides.join('\n')}</div>${renderDeckControls()}<script>${SCRIPT}</script></body></html>`;
}

module.exports = { renderRetomarHtml, PARTIR_DEFAULT };
