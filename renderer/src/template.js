const { escapeHtml } = require('./escape');

const LOGO_URL =
  'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech%20+%20slogan.png';

const BRAND_GRADIENTS = [
  'linear-gradient(135deg, #0d1b2a 0%, #12314a 100%)',
  'linear-gradient(135deg, #0a1622 0%, #0f2233 100%)',
  'linear-gradient(135deg, #0d1b2a 0%, #163a52 100%)',
];

function pickFallbackGradient(index) {
  return BRAND_GRADIENTS[index % BRAND_GRADIENTS.length];
}

function backgroundStyle(imageUrl, index) {
  if (imageUrl) {
    return `background-image: linear-gradient(180deg, rgba(13,27,42,.15) 0%, rgba(13,27,42,.55) 55%, rgba(13,27,42,.94) 100%), url('${escapeHtml(
      imageUrl
    )}'); background-size: cover; background-position: center;`;
  }
  return `background: ${pickFallbackGradient(index)};`;
}

function logoMark() {
  return `<div class="at-watermark"><img src="${LOGO_URL}" alt="AutomatizaTech" /></div>`;
}

function renderCoverSlide({ company_name, client_name, index, imageUrl }) {
  return `
    <section class="slide slide-cover" style="${backgroundStyle(imageUrl, index)}">
      ${logoMark()}
      <div class="slide-body">
        <p class="eyebrow">Propuesta de transformación digital</p>
        <h1>${escapeHtml(company_name)}</h1>
        <p class="lede">Preparado para ${escapeHtml(client_name)}</p>
        <div class="accent-bar"></div>
      </div>
    </section>`;
}

function renderContentSlide({ index, eyebrow, title, bodyHtml, imageUrl }) {
  return `
    <section class="slide slide-content" style="${backgroundStyle(imageUrl, index)}">
      ${logoMark()}
      <div class="slide-text">
        <p class="eyebrow">${String(index).padStart(2, '0')} · ${escapeHtml(eyebrow)}</p>
        <div class="accent-bar"></div>
        <h2>${escapeHtml(title)}</h2>
        ${bodyHtml}
      </div>
    </section>`;
}

function renderParagraphBody(text) {
  return `<p class="body-text">${escapeHtml(text)}</p>`;
}

function renderBulletListBody(items, formatter) {
  const lis = items.map((item) => `<li>${formatter(item)}</li>`).join('');
  return `<ul class="body-list">${lis}</ul>`;
}

function renderPricingBody(rows, note) {
  const trs = rows
    .map(
      (row) => `
      <tr>
        <td>${escapeHtml(row.service)}</td>
        <td>$${escapeHtml(row.price_usd)} USD ($${escapeHtml(row.price_clp)} CLP aprox)</td>
      </tr>`
    )
    .join('');
  const noteHtml = note ? `<p class="pricing-note">${escapeHtml(note)}</p>` : '';
  return `<table class="pricing-table"><tbody>${trs}</tbody></table>${noteHtml}`;
}

function renderClosingSlide() {
  return `
    <section class="slide slide-closing">
      <div class="closing-left"><div class="closing-mark">AT</div></div>
      <div class="closing-right">
        <h2>Hablemos</h2>
        <p class="contact-row">contacto@automatizatech.cl</p>
        <p class="contact-row">automatizatech.cl</p>
        <p class="contact-row">+56 9 2700 2984</p>
        <p class="contact-row">@automatizatech.cl</p>
      </div>
    </section>`;
}

const STYLE = `
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, "Segoe UI", Roboto, sans-serif; background: #0d1b2a; }
  .slide { position: relative; width: 1920px; height: 1080px; overflow: hidden; break-after: page; }
  .at-watermark { position: absolute; top: 32px; left: 32px; z-index: 3; opacity: .4; }
  .at-watermark img { width: 76px; display: block; }
  .accent-bar { width: 64px; height: 5px; background: #00d9c0; border-radius: 3px; margin: 20px 0; }
  .eyebrow { color: #00d9c0; font-size: 20px; letter-spacing: .15em; text-transform: uppercase; font-weight: 700; }
  .slide-cover .slide-body { position: absolute; left: 64px; right: 64px; bottom: 72px; z-index: 2; }
  .slide-cover h1 { color: #fff; font-size: 72px; font-weight: 800; max-width: 80%; }
  .slide-cover .lede { color: #c9d4e0; font-size: 28px; margin-top: 12px; }
  .slide-content .slide-text { position: absolute; left: 72px; top: 120px; width: 46%; z-index: 2; }
  .slide-content h2 { color: #fff; font-size: 46px; font-weight: 800; margin-bottom: 24px; }
  .body-text { color: #c9d4e0; font-size: 26px; line-height: 1.6; }
  .body-list { color: #c9d4e0; font-size: 24px; line-height: 2; padding-left: 28px; }
  .pricing-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  .pricing-table td { color: #c9d4e0; font-size: 24px; padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,.12); }
  .pricing-note { color: #8ea3ba; font-size: 18px; margin-top: 16px; }
  .slide-closing { display: flex; }
  .closing-left { width: 42%; background: #0a1420; display: flex; align-items: center; justify-content: center; }
  .closing-mark { width: 160px; height: 160px; border-radius: 50%; background: #0d2b4e; color: #fff; font-weight: 800; font-size: 56px; display: flex; align-items: center; justify-content: center; border: 2px solid #1b3454; }
  .closing-right { width: 58%; background: linear-gradient(160deg, #0f2233, #0a1622); display: flex; flex-direction: column; justify-content: center; padding: 0 80px; }
  .closing-right h2 { color: #fff; font-size: 48px; margin-bottom: 24px; }
  .contact-row { color: #c9d4e0; font-size: 24px; margin-bottom: 10px; }
`;

function renderProposalHtml(data, images = {}) {
  const benefitsBody = renderBulletListBody(
    data.benefits,
    (b) => `<strong>${escapeHtml(b.title)}:</strong> ${escapeHtml(b.text)}`
  );
  const stepsBody = renderBulletListBody(
    data.how_it_works,
    (s) => `<strong>${escapeHtml(s.step_title)}:</strong> ${escapeHtml(s.step_text)}`
  );
  const nextStepsBody = renderBulletListBody(data.next_steps, (s) => escapeHtml(s));
  const pricingBody = renderPricingBody(data.pricing_rows, data.pricing_note);

  const slides = [
    renderCoverSlide({
      company_name: data.company_name,
      client_name: data.client_name,
      index: 0,
      imageUrl: images.cover,
    }),
    renderContentSlide({
      index: 2,
      eyebrow: 'El desafío actual',
      title: data.challenge_title,
      bodyHtml: renderParagraphBody(data.challenge_text),
      imageUrl: images.challenge,
    }),
    renderContentSlide({
      index: 3,
      eyebrow: 'Nuestra solución',
      title: data.solution_title,
      bodyHtml: renderParagraphBody(data.solution_text),
      imageUrl: images.solution,
    }),
    renderContentSlide({
      index: 4,
      eyebrow: 'Beneficios clave',
      title: 'Lo que gana ' + data.company_name,
      bodyHtml: benefitsBody,
      imageUrl: images.benefits,
    }),
    renderContentSlide({
      index: 5,
      eyebrow: '¿Cómo funciona?',
      title: 'Proceso de implementación',
      bodyHtml: stepsBody,
      imageUrl: images.how_it_works,
    }),
    renderContentSlide({
      index: 6,
      eyebrow: 'Inversión',
      title: 'Precio de la propuesta',
      bodyHtml: pricingBody,
      imageUrl: images.pricing,
    }),
    renderContentSlide({
      index: 7,
      eyebrow: 'Próximos pasos',
      title: 'Cómo seguimos',
      bodyHtml: nextStepsBody,
      imageUrl: null,
    }),
    renderClosingSlide(),
  ];

  return `<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Propuesta AutomatizaTech · ${escapeHtml(data.company_name)}</title>
<style>${STYLE}</style>
</head>
<body>
${slides.join('\n')}
</body>
</html>`;
}

module.exports = {
  renderProposalHtml,
  renderCoverSlide,
  renderContentSlide,
  renderClosingSlide,
  renderParagraphBody,
  renderBulletListBody,
  renderPricingBody,
};
