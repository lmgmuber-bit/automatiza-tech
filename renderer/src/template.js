const { escapeHtml } = require('./escape');

const LOGO_URL =
  'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech%20+%20slogan.png';

const CONTACTS = [
  { label: 'contacto@automatizatech.cl', href: 'mailto:contacto@automatizatech.cl', icon: 'mail' },
  { label: 'automatizatech.cl', href: 'https://automatizatech.cl', icon: 'web' },
  { label: '+56 9 2700 2984', href: 'https://wa.me/56927002984', icon: 'whatsapp' },
  { label: '@automatizatech.cl', href: 'https://instagram.com/automatizatech.cl', icon: 'instagram' },
];

// Inline so the closing slide needs no icon font or extra request. Mail and
// web are plain stroke drawings; WhatsApp and Instagram are the Simple Icons
// glyphs (CC0-1.0, simple-icons 16.32.0), which is why they fill instead of stroke.
const CONTACT_ICONS = {
  mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/></svg>',
  web: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18"/></svg>',
  whatsapp: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>',
  instagram: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7.0301.084c-1.2768.0602-2.1487.264-2.911.5634-.7888.3075-1.4575.72-2.1228 1.3877-.6652.6677-1.075 1.3368-1.3802 2.127-.2954.7638-.4956 1.6365-.552 2.914-.0564 1.2775-.0689 1.6882-.0626 4.947.0062 3.2586.0206 3.6671.0825 4.9473.061 1.2765.264 2.1482.5635 2.9107.308.7889.72 1.4573 1.388 2.1228.6679.6655 1.3365 1.0743 2.1285 1.38.7632.295 1.6361.4961 2.9134.552 1.2773.056 1.6884.069 4.9462.0627 3.2578-.0062 3.668-.0207 4.9478-.0814 1.28-.0607 2.147-.2652 2.9098-.5633.7889-.3086 1.4578-.72 2.1228-1.3881.665-.6682 1.0745-1.3378 1.3795-2.1284.2957-.7632.4966-1.636.552-2.9124.056-1.2809.0692-1.6898.063-4.948-.0063-3.2583-.021-3.6668-.0817-4.9465-.0607-1.2797-.264-2.1487-.5633-2.9117-.3084-.7889-.72-1.4568-1.3876-2.1228C21.2982 1.33 20.628.9208 19.8378.6165 19.074.321 18.2017.1197 16.9244.0645 15.6471.0093 15.236-.005 11.977.0014 8.718.0076 8.31.0215 7.0301.0839m.1402 21.6932c-1.17-.0509-1.8053-.2453-2.2287-.408-.5606-.216-.96-.4771-1.3819-.895-.422-.4178-.6811-.8186-.9-1.378-.1644-.4234-.3624-1.058-.4171-2.228-.0595-1.2645-.072-1.6442-.079-4.848-.007-3.2037.0053-3.583.0607-4.848.05-1.169.2456-1.805.408-2.2282.216-.5613.4762-.96.895-1.3816.4188-.4217.8184-.6814 1.3783-.9003.423-.1651 1.0575-.3614 2.227-.4171 1.2655-.06 1.6447-.072 4.848-.079 3.2033-.007 3.5835.005 4.8495.0608 1.169.0508 1.8053.2445 2.228.408.5608.216.96.4754 1.3816.895.4217.4194.6816.8176.9005 1.3787.1653.4217.3617 1.056.4169 2.2263.0602 1.2655.0739 1.645.0796 4.848.0058 3.203-.0055 3.5834-.061 4.848-.051 1.17-.245 1.8055-.408 2.2294-.216.5604-.4763.96-.8954 1.3814-.419.4215-.8181.6811-1.3783.9-.4224.1649-1.0577.3617-2.2262.4174-1.2656.0595-1.6448.072-4.8493.079-3.2045.007-3.5825-.006-4.848-.0608M16.953 5.5864A1.44 1.44 0 1 0 18.39 4.144a1.44 1.44 0 0 0-1.437 1.4424M5.8385 12.012c.0067 3.4032 2.7706 6.1557 6.173 6.1493 3.4026-.0065 6.157-2.7701 6.1506-6.1733-.0065-3.4032-2.771-6.1565-6.174-6.1498-3.403.0067-6.156 2.771-6.1496 6.1738M8 12.0077a4 4 0 1 1 4.008 3.9921A3.9996 3.9996 0 0 1 8 12.0077"/></svg>',
};

const BRAND_GRADIENTS = [
  'linear-gradient(135deg, #0d1b2a 0%, #12314a 100%)',
  'linear-gradient(135deg, #0a1622 0%, #0f2233 100%)',
  'linear-gradient(135deg, #0d1b2a 0%, #163a52 100%)',
];

// The scrim has to darken the half of the photo the text actually sits on.
// The cover puts its copy at the bottom; content slides put it at the top
// left. A single bottom-heavy gradient (the original) left every content
// slide's text over the brightest part of the image, unreadable whenever
// the photo happened to be busy up top.
const SCRIMS = {
  cover:
    'linear-gradient(180deg, rgba(13,27,42,.15) 0%, rgba(13,27,42,.55) 55%, rgba(13,27,42,.94) 100%)',
  content:
    'linear-gradient(100deg, rgba(13,27,42,.97) 0%, rgba(13,27,42,.93) 38%, rgba(13,27,42,.62) 62%, rgba(13,27,42,.28) 100%)',
};

function pickFallbackGradient(index) {
  return BRAND_GRADIENTS[index % BRAND_GRADIENTS.length];
}

function backgroundStyle(imageUrl, index, variant = 'content') {
  if (imageUrl) {
    return `background-image: ${SCRIMS[variant] || SCRIMS.content}, url('${escapeHtml(
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
    <section class="slide slide-cover">
      <div class="slide-bg" style="${backgroundStyle(imageUrl, index, 'cover')}"></div>
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
    <section class="slide slide-content">
      <div class="slide-bg" style="${backgroundStyle(imageUrl, index, 'content')}"></div>
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

function renderPriceCell(row) {
  // An explicit label wins: a discount line reads "−$1.000 USD", which is
  // not a price the numeric fields can express.
  if (row.price_label) return escapeHtml(row.price_label);
  const usd = `$${escapeHtml(row.price_usd)} USD`;
  // The peso conversion only means something to a client billed in Chile.
  // For anyone abroad it is noise, so a row with no price_clp shows dollars
  // and nothing else.
  if (!row.price_clp) return usd;
  return `${usd} ($${escapeHtml(row.price_clp)} CLP aprox)`;
}

function renderPricingBody(rows, note) {
  const trs = rows
    .map(
      (row) => `
      <tr class="${row.strike ? 'is-struck' : ''}${row.emphasis ? ' is-total' : ''}">
        <td>${escapeHtml(row.service)}</td>
        <td>${renderPriceCell(row)}</td>
      </tr>`
    )
    .join('');
  const noteHtml = note ? `<p class="pricing-note">${escapeHtml(note)}</p>` : '';
  return `<table class="pricing-table"><tbody>${trs}</tbody></table>${noteHtml}`;
}

function renderClosingSlide() {
  const rows = CONTACTS.map(
    (c) =>
      `<a class="contact-row" href="${c.href}" target="_blank" rel="noopener noreferrer"><span class="contact-icon" aria-hidden="true">${
        CONTACT_ICONS[c.icon] || ''
      }</span><span class="contact-label">${escapeHtml(c.label)}</span></a>`
  ).join('');
  return `
    <section class="slide slide-closing">
      <div class="closing-left">
        <a href="https://automatizatech.cl" target="_blank" rel="noopener noreferrer"><img class="closing-logo" src="${LOGO_URL}" alt="AutomatizaTech" /></a>
      </div>
      <div class="closing-right">
        <h2>Hablemos</h2>
        <div class="contact-list">${rows}</div>
        <a class="pdf-button" href="presentation.pdf" download>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 11l5 5 5-5M4 19h16" /></svg>
          Descargar la propuesta en PDF
        </a>
      </div>
    </section>`;
}

function renderDeckControls() {
  return `
  <div class="at-bar" role="toolbar" aria-label="Controles de la presentación">
    <button class="at-nav" id="at-prev" type="button" aria-label="Lámina anterior">&#8249;</button>
    <span class="at-nav" id="at-counter">1 / 8</span>
    <button class="at-nav" id="at-next" type="button" aria-label="Lámina siguiente">&#8250;</button>
    <button id="at-full" type="button" aria-label="Pantalla completa" hidden>
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9V3h6M21 9V3h-6M3 15v6h6M21 15v6h-6" /></svg>
    </button>
    <button id="at-toggle" type="button">Ver todo</button>
  </div>
  <button class="at-chip" id="at-chip" type="button" hidden>Toca para ver en pantalla completa</button>
  <div class="at-progress" aria-hidden="true"><span id="at-progress-fill"></span></div>
  <div class="at-rotate" id="at-rotate" hidden>
    <div class="at-rotate-card">
      <svg class="at-rotate-icon" viewBox="0 0 64 64" aria-hidden="true">
        <rect x="23" y="5" width="18" height="38" rx="3" />
        <line x1="28" y1="10" x2="36" y2="10" />
        <path d="M14 50a20 20 0 0 0 36 0" />
        <polyline points="8,44 14,50 20,45" />
      </svg>
      <p class="at-rotate-title">Gira tu teléfono</p>
      <p class="at-rotate-text">La presentación se ve en pantalla completa en horizontal.</p>
      <button type="button" id="at-rotate-full" hidden>Ver en pantalla completa</button>
      <button type="button" id="at-rotate-skip">Continuar así</button>
    </div>
  </div>`;
}

const STYLE = `
  :root { --zoom: 1; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, "Segoe UI", Roboto, sans-serif; background: #060d15; }
  .slide { position: relative; width: 1920px; height: 1080px; overflow: hidden; break-after: page; }
  /* The photo lives on its own layer so the slow zoom can move it without
     dragging the copy along with it. */
  .slide-bg { position: absolute; inset: 0; z-index: 0; background-size: cover; background-position: center; }
  /* The 4%-wide, 40%-opacity watermark is the rule for AT *video*, where it
     must never compete with the footage. A proposal is a document the client
     reads: here the logo should be legible, slogan and all. */
  .at-watermark { position: absolute; top: 40px; left: 44px; z-index: 3; opacity: .92; }
  .at-watermark img { width: 190px; display: block; }
  .accent-bar { width: 64px; height: 5px; background: #00d9c0; border-radius: 3px; margin: 20px 0; }
  .eyebrow { color: #00d9c0; font-size: 20px; letter-spacing: .15em; text-transform: uppercase; font-weight: 700; }
  .slide-cover .slide-body { position: absolute; left: 64px; right: 64px; bottom: 72px; z-index: 2; }
  .slide-cover h1 { color: #fff; font-size: 72px; font-weight: 800; max-width: 80%; }
  .slide-cover .lede { color: #c9d4e0; font-size: 28px; margin-top: 12px; }
  /* Starts below the watermark: at 190px wide the logo reaches ~180px down,
     and the old 120px offset ran the eyebrow straight through it. */
  .slide-content .slide-text { position: absolute; left: 72px; top: 240px; width: 46%; z-index: 2; }
  .slide-content h2 { color: #fff; font-size: 46px; font-weight: 800; margin-bottom: 24px; text-shadow: 0 2px 18px rgba(6,13,21,.7); }
  .body-text { color: #dbe4ee; font-size: 26px; line-height: 1.6; text-shadow: 0 1px 12px rgba(6,13,21,.65); }
  .body-list { color: #dbe4ee; font-size: 24px; line-height: 2; padding-left: 28px; text-shadow: 0 1px 12px rgba(6,13,21,.65); }
  .pricing-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  .pricing-table td { color: #dbe4ee; font-size: 24px; padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,.12); }
  /* Anchoring a launch price: the list price is struck through and dimmed,
     the amount the client actually pays is the loudest thing on the slide. */
  .pricing-table tr.is-struck td { color: #8ea3ba; }
  .pricing-table tr.is-struck td:last-child { text-decoration: line-through; }
  .pricing-table tr.is-total td { color: #fff; font-weight: 800; font-size: 30px; padding-top: 20px; border-bottom: 0; }
  .pricing-table tr.is-total td:last-child { color: #00d9c0; }
  .pricing-note { color: #9fb3c8; font-size: 18px; margin-top: 16px; }
  .slide-closing { display: flex; }
  .closing-left { width: 42%; background: #0a1420; display: flex; align-items: center; justify-content: center; }
  .closing-logo { width: 340px; max-width: 72%; display: block; }
  .closing-right { width: 58%; background: linear-gradient(160deg, #0f2233, #0a1622); display: flex; flex-direction: column; justify-content: center; padding: 0 80px; }
  .closing-right h2 { color: #fff; font-size: 48px; margin-bottom: 24px; }
  .contact-list { display: flex; flex-direction: column; align-items: flex-start; gap: 14px; }
  .contact-row { display: inline-flex; align-items: center; gap: 16px; color: #dbe4ee; font-size: 24px; text-decoration: none; }
  .contact-icon { flex: none; width: 44px; height: 44px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
    color: #00d9c0; background: rgba(0,217,192,.12); border: 1px solid rgba(0,217,192,.35); }
  .contact-icon svg { width: 22px; height: 22px; }
  .contact-label { border-bottom: 1px solid rgba(0,217,192,.35); padding-bottom: 2px; }
  .contact-row:hover { color: #00d9c0; }
  .contact-row:hover .contact-label { border-bottom-color: #00d9c0; }
  .contact-row:hover .contact-icon { background: rgba(0,217,192,.22); }
  /* Relative href on purpose: presentation.pdf is the renderer's own sibling
     of index.html, so this keeps working whatever the base URL ends up being. */
  .pdf-button { display: inline-flex; align-items: center; gap: 12px; margin-top: 40px; align-self: flex-start;
    font-size: 22px; font-weight: 700; color: #06222a; background: #00d9c0; text-decoration: none;
    border-radius: 999px; padding: 18px 32px; }
  .pdf-button svg { width: 24px; height: 24px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
  .pdf-button:hover { background: #33e3ce; }

  @media screen {
    .deck { display: flex; flex-direction: column; align-items: center; gap: 28px; padding: 28px 0 110px; }
    .slide { zoom: var(--zoom); box-shadow: 0 24px 60px rgba(0,0,0,.55); }
    body.mode-deck { overflow: hidden; }
    body.mode-deck .deck { position: fixed; inset: 0; justify-content: center; padding: 0; gap: 0; }
    /* Hide the inactive slides rather than forcing a display value on the
       active one: .slide-closing is a two-column flex slide, and any
       "display: block" here outranks it and collapses its layout. */
    body.mode-deck .slide:not(.is-active) { display: none; }
    .at-bar { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 50; display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(10,20,32,.88); border: 1px solid rgba(255,255,255,.14); backdrop-filter: blur(10px); }
    .at-bar button, .at-bar span { font-family: inherit; font-size: 15px; color: #dbe4ee; background: none; border: 0; }
    .at-bar button { cursor: pointer; padding: 6px 14px; border-radius: 999px; }
    .at-bar button:hover { background: rgba(0,217,192,.16); color: #00d9c0; }
    #at-prev, #at-next { font-size: 22px; line-height: 1; padding: 2px 12px 6px; }
    #at-counter { min-width: 58px; text-align: center; color: #9fb3c8; letter-spacing: .05em; }
    #at-toggle { border: 1px solid rgba(255,255,255,.18); margin-left: 4px; }
    #at-full { padding: 6px 10px; line-height: 0; }
    #at-full svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    #at-full[hidden] { display: none; }
    body.mode-list .at-nav { display: none; }

    /* Fullscreen can only be requested from a real tap — browsers reject it
       on an orientation change alone — so the moment someone turns the
       phone we put a one-tap shortcut right where the thumb already is. */
    .at-chip { position: fixed; bottom: 78px; left: 50%; transform: translateX(-50%); z-index: 65;
      font-family: inherit; font-size: 14px; color: #06222a; background: #00d9c0; border: 0; cursor: pointer;
      border-radius: 999px; padding: 10px 18px; box-shadow: 0 8px 24px rgba(0,0,0,.45); animation: at-rise .4s ease-out both; }
    .at-chip[hidden] { display: none; }

    /* Fingers need a bigger target than a mouse pointer does. */
    @media (pointer: coarse) {
      .at-bar { gap: 4px; padding: 6px 8px; }
      .at-bar button { padding: 10px 18px; font-size: 16px; }
      #at-prev, #at-next { font-size: 26px; padding: 4px 16px 9px; }
    }

    .at-rotate { position: fixed; inset: 0; z-index: 80; display: flex; align-items: center; justify-content: center;
      background: #060d15; padding: 32px; text-align: center; }
    /* display:flex above outranks the browser's own [hidden] rule, so the
       hidden state has to be spelled out or the overlay never goes away. */
    .at-rotate[hidden] { display: none; }
    .at-rotate-card { max-width: 320px; }
    .at-rotate-icon { width: 92px; height: 92px; fill: none; stroke: #00d9c0; stroke-width: 2.5;
      stroke-linecap: round; stroke-linejoin: round; animation: at-tilt 2.4s ease-in-out infinite; }
    .at-rotate-title { color: #fff; font-size: 22px; font-weight: 700; margin-top: 18px; }
    .at-rotate-text { color: #9fb3c8; font-size: 15px; line-height: 1.5; margin-top: 8px; }
    #at-rotate-full { display: block; margin: 22px auto 0; font-family: inherit; font-size: 16px; font-weight: 600;
      color: #06222a; background: #00d9c0; border: 0; border-radius: 999px; padding: 12px 26px; cursor: pointer; }
    #at-rotate-full[hidden] { display: none; }
    #at-rotate-skip { margin-top: 14px; font-family: inherit; font-size: 15px; color: #dbe4ee; cursor: pointer;
      background: none; border: 1px solid rgba(255,255,255,.22); border-radius: 999px; padding: 10px 22px; }
    @keyframes at-tilt { 0%, 45% { transform: rotate(0); } 70%, 100% { transform: rotate(-90deg); } }

    .at-progress { position: fixed; top: 0; left: 0; right: 0; height: 3px; z-index: 60; background: rgba(255,255,255,.08); }
    .at-progress span { display: block; height: 100%; width: 0; background: linear-gradient(90deg, #00d9c0, #38bdf8); transition: width .5s cubic-bezier(.22,.9,.3,1); }
    body.mode-list .at-progress { display: none; }

    /* Motion is deliberately confined to deck mode on screen. The list view
       stays still (you are scrolling it yourself) and the PDF pass never
       sees any of this, so no element can be captured mid-fade. */
    body.mode-deck .slide.is-active { animation: at-slide-in .55s cubic-bezier(.22,.9,.3,1) both; }
    body.mode-deck[data-dir="prev"] .slide.is-active { animation-name: at-slide-in-back; }
    body.mode-deck .slide.is-active .slide-bg { animation: at-kenburns 22s ease-out both; }
    body.mode-deck .slide.is-active .slide-text > *,
    body.mode-deck .slide.is-active .slide-body > *,
    body.mode-deck .slide.is-active .closing-right > *,
    body.mode-deck .slide.is-active .closing-left { animation: at-rise .65s cubic-bezier(.22,.9,.3,1) both; }
    body.mode-deck .slide.is-active .slide-text > *:nth-child(1),
    body.mode-deck .slide.is-active .slide-body > *:nth-child(1),
    body.mode-deck .slide.is-active .closing-right > *:nth-child(1) { animation-delay: .18s; }
    body.mode-deck .slide.is-active .slide-text > *:nth-child(2),
    body.mode-deck .slide.is-active .slide-body > *:nth-child(2),
    body.mode-deck .slide.is-active .closing-right > *:nth-child(2) { animation-delay: .26s; }
    body.mode-deck .slide.is-active .slide-text > *:nth-child(3),
    body.mode-deck .slide.is-active .slide-body > *:nth-child(3) { animation-delay: .34s; }
    body.mode-deck .slide.is-active .slide-text > *:nth-child(4),
    body.mode-deck .slide.is-active .slide-body > *:nth-child(4) { animation-delay: .42s; }
    body.mode-deck .slide.is-active .closing-left { animation-delay: .12s; }
    body.mode-deck .slide.is-active .body-list li,
    body.mode-deck .slide.is-active .pricing-table tr,
    body.mode-deck .slide.is-active .contact-list a { animation: at-rise .6s cubic-bezier(.22,.9,.3,1) both; }
    body.mode-deck .slide.is-active .body-list li:nth-child(1),
    body.mode-deck .slide.is-active .pricing-table tr:nth-child(1),
    body.mode-deck .slide.is-active .contact-list a:nth-child(1) { animation-delay: .46s; }
    body.mode-deck .slide.is-active .body-list li:nth-child(2),
    body.mode-deck .slide.is-active .pricing-table tr:nth-child(2),
    body.mode-deck .slide.is-active .contact-list a:nth-child(2) { animation-delay: .56s; }
    body.mode-deck .slide.is-active .body-list li:nth-child(3),
    body.mode-deck .slide.is-active .pricing-table tr:nth-child(3),
    body.mode-deck .slide.is-active .contact-list a:nth-child(3) { animation-delay: .66s; }
    body.mode-deck .slide.is-active .body-list li:nth-child(4),
    body.mode-deck .slide.is-active .pricing-table tr:nth-child(4),
    body.mode-deck .slide.is-active .contact-list a:nth-child(4) { animation-delay: .76s; }
    body.mode-deck .slide.is-active .body-list li:nth-child(n+5),
    body.mode-deck .slide.is-active .pricing-table tr:nth-child(n+5) { animation-delay: .86s; }

    @keyframes at-slide-in { from { opacity: 0; transform: translateX(46px) scale(.985); } to { opacity: 1; transform: none; } }
    @keyframes at-slide-in-back { from { opacity: 0; transform: translateX(-46px) scale(.985); } to { opacity: 1; transform: none; } }
    @keyframes at-rise { from { opacity: 0; transform: translateY(22px); } to { opacity: 1; transform: none; } }
    @keyframes at-kenburns { from { transform: scale(1); } to { transform: scale(1.09); } }

    @media (prefers-reduced-motion: reduce) {
      body.mode-deck .slide.is-active,
      body.mode-deck .slide.is-active *,
      .at-rotate-icon,
      .at-chip,
      .at-progress span { animation: none !important; transition: none !important; }
    }
  }

  @media print {
    /* Both chrome elements must be gone from the PDF, or the empty progress
       bar tacks a stray blank page onto the end. */
    /* A "download this as PDF" button printed inside the PDF itself is
       nonsense, so it goes with the rest of the on-screen chrome. */
    .at-bar, .at-progress, .at-rotate, .at-chip, .pdf-button { display: none !important; }
    .deck { position: static !important; display: block !important; padding: 0 !important; }
    /* No display override here either — the deck's hiding rule is scoped to
       @media screen, so every slide is already visible in the PDF pass and
       keeps whatever display its own class defines. */
    .slide { zoom: 1 !important; box-shadow: none !important; }
  }
`;

const SCRIPT = `
(function () {
  var slides = Array.prototype.slice.call(document.querySelectorAll('.slide'));
  if (!slides.length) return;
  var body = document.body;
  var i = 0;
  var counter = document.getElementById('at-counter');
  var toggle = document.getElementById('at-toggle');
  var fill = document.getElementById('at-progress-fill');
  function isDeck() { return body.classList.contains('mode-deck'); }
  function fit() {
    var z = isDeck()
      ? Math.min(window.innerWidth / 1920, window.innerHeight / 1080)
      : Math.min(window.innerWidth / 1920, 1);
    document.documentElement.style.setProperty('--zoom', z);
  }
  function show(n) {
    var next = Math.max(0, Math.min(slides.length - 1, n));
    // Direction drives which way the incoming slide travels, so going back
    // feels like going back instead of always sliding forward.
    body.setAttribute('data-dir', next < i ? 'prev' : 'next');
    i = next;
    for (var k = 0; k < slides.length; k++) {
      slides[k].classList.toggle('is-active', k === i);
    }
    if (counter) counter.textContent = (i + 1) + ' / ' + slides.length;
    if (fill) fill.style.width = ((i + 1) / slides.length) * 100 + '%';
  }
  function setMode(deck) {
    body.classList.toggle('mode-deck', deck);
    body.classList.toggle('mode-list', !deck);
    if (toggle) toggle.textContent = deck ? 'Ver todo' : 'Ver presentación';
    fit();
    if (deck) { show(i); } else { slides[i].scrollIntoView({ block: 'start' }); }
    updateRotateHint();
  }
  document.getElementById('at-prev').addEventListener('click', function () { show(i - 1); });
  document.getElementById('at-next').addEventListener('click', function () { show(i + 1); });
  toggle.addEventListener('click', function () { setMode(!isDeck()); });
  document.addEventListener('keydown', function (e) {
    if (!isDeck()) return;
    if (e.key === 'ArrowRight' || e.key === 'PageDown' || e.key === ' ') { e.preventDefault(); show(i + 1); }
    else if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); show(i - 1); }
    else if (e.key === 'Home') { show(0); }
    else if (e.key === 'End') { show(slides.length - 1); }
  });
  // --- Pantalla completa ---------------------------------------------------
  // Nunca se puede pedir sola: el navegador exige que la peticion nazca de un
  // toque del usuario, asi que lo mas cerca del automatico es dejarla a un
  // toque en los tres momentos en que alguien la querria.
  var fullBtn = document.getElementById('at-full');
  var chip = document.getElementById('at-chip');
  var rotateFull = document.getElementById('at-rotate-full');
  var chipTimer = null;

  function fsElement() {
    return document.fullscreenElement || document.webkitFullscreenElement || null;
  }
  function fsSupported() {
    var el = document.documentElement;
    return !!((document.fullscreenEnabled || document.webkitFullscreenEnabled) &&
      (el.requestFullscreen || el.webkitRequestFullscreen));
  }
  function enterFullscreen() {
    var el = document.documentElement;
    var req = el.requestFullscreen || el.webkitRequestFullscreen;
    if (!req) return;
    var p = req.call(el);
    // Safari returns undefined; Chrome rejects if the gesture expired.
    if (p && p.catch) p.catch(function () {});
  }
  function exitFullscreen() {
    var ex = document.exitFullscreen || document.webkitExitFullscreen;
    if (ex) ex.call(document);
  }
  function hideChip() {
    if (chipTimer) { clearTimeout(chipTimer); chipTimer = null; }
    if (chip) chip.hidden = true;
  }
  function offerFullscreen() {
    if (!chip || !fsSupported() || fsElement() || !isTouch()) return;
    chip.hidden = false;
    if (chipTimer) clearTimeout(chipTimer);
    chipTimer = setTimeout(hideChip, 7000);
  }
  if (fullBtn && fsSupported()) {
    fullBtn.hidden = false;
    fullBtn.addEventListener('click', function () {
      if (fsElement()) exitFullscreen(); else enterFullscreen();
    });
  }
  if (chip) {
    chip.addEventListener('click', function () { enterFullscreen(); hideChip(); });
  }
  document.addEventListener('fullscreenchange', hideChip);
  document.addEventListener('webkitfullscreenchange', hideChip);

  // --- Teléfono: sugerir el giro y navegar con el dedo ---------------------
  var rotate = document.getElementById('at-rotate');
  var skip = document.getElementById('at-rotate-skip');
  var rotateDismissed = false;

  function isTouch() {
    try { return window.matchMedia('(pointer: coarse)').matches; } catch (e) { return false; }
  }
  function screenIsPortrait() {
    // The screen, not the window: this page is embedded in an iframe inside
    // ver-presentacion.php, and the frame's own box can be wider than tall
    // while the phone itself is upright. Asking the device settles it.
    try {
      var o = window.screen && window.screen.orientation;
      if (o && typeof o.type === 'string') return o.type.indexOf('portrait') === 0;
      if (window.screen && window.screen.width && window.screen.height) {
        return window.screen.height > window.screen.width;
      }
    } catch (e) {}
    return window.innerHeight > window.innerWidth;
  }
  function updateRotateHint() {
    if (!rotate) return;
    rotate.hidden = !(isDeck() && isTouch() && screenIsPortrait() && !rotateDismissed);
  }
  if (skip) {
    // Never a dead end: with rotation locked, the viewer would otherwise be
    // stuck staring at the hint with no way into the proposal.
    skip.addEventListener('click', function () {
      rotateDismissed = true;
      updateRotateHint();
    });
  }
  if (rotateFull && fsSupported()) {
    rotateFull.hidden = false;
    // Tapping here is the gesture the browser needs, and fullscreen survives
    // the rotation that follows.
    rotateFull.addEventListener('click', function () {
      enterFullscreen();
      rotateDismissed = true;
      updateRotateHint();
    });
  }

  function onOrientationChange() {
    updateRotateHint();
    // Turned the phone sideways: this is exactly when someone wants the
    // slide to fill the screen, so put the shortcut in front of them.
    if (!screenIsPortrait()) offerFullscreen(); else hideChip();
  }
  window.addEventListener('orientationchange', onOrientationChange);
  if (window.screen && window.screen.orientation && window.screen.orientation.addEventListener) {
    window.screen.orientation.addEventListener('change', onOrientationChange);
  }

  var tStartX = 0, tStartY = 0, tStartAt = 0;
  document.addEventListener('touchstart', function (e) {
    if (!isDeck() || e.touches.length !== 1) { tStartAt = 0; return; }
    tStartX = e.touches[0].clientX;
    tStartY = e.touches[0].clientY;
    tStartAt = Date.now();
  }, { passive: true });
  document.addEventListener('touchend', function (e) {
    if (!isDeck() || !tStartAt) return;
    var t = e.changedTouches[0];
    var dx = t.clientX - tStartX;
    var dy = t.clientY - tStartY;
    var elapsed = Date.now() - tStartAt;
    tStartAt = 0;
    if (elapsed > 800) return;
    // Must be a deliberate, mostly horizontal swipe: a short drag or a
    // vertical one is the viewer reading, not asking for the next slide.
    if (Math.abs(dx) < 45 || Math.abs(dx) < Math.abs(dy) * 1.4) return;
    show(dx < 0 ? i + 1 : i - 1);
  }, { passive: true });

  document.addEventListener('click', function (e) {
    if (!isDeck() || !isTouch()) return;
    if (e.target.closest && e.target.closest('a, button')) return;
    var x = e.clientX / window.innerWidth;
    if (x > 0.82) show(i + 1);
    else if (x < 0.18) show(i - 1);
  });

  window.addEventListener('resize', function () { fit(); updateRotateHint(); });
  // Deck is the default view, but it is switched on from JS on purpose:
  // with scripting unavailable the document stays a plain stack of slides
  // instead of collapsing to a single visible one.
  setMode(true);
})();
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

  // Optional extra sections, so a rich meeting gets the slides it deserves
  // and a thin one is not padded out: 0, 1 or 2 of these sit between the
  // process and the price, giving a deck of 8, 9 or 10 slides. More than two
  // is capped on purpose — past that it stops being a proposal.
  const extraSlides = (Array.isArray(data.extra_slides) ? data.extra_slides : [])
    .filter((s) => s && s.title)
    .slice(0, 2)
    .map((s, i) => ({
      eyebrow: s.eyebrow || 'En detalle',
      title: s.title,
      bodyHtml:
        Array.isArray(s.bullets) && s.bullets.length
          ? renderBulletListBody(s.bullets, (b) =>
              b && b.text
                ? `<strong>${escapeHtml(b.title)}:</strong> ${escapeHtml(b.text)}`
                : escapeHtml(b && b.title ? b.title : b)
            )
          : renderParagraphBody(s.text || ''),
      imageKey: `extra_${i + 1}`,
    }));

  const contentSlides = [
    {
      eyebrow: 'El desafío actual',
      title: data.challenge_title,
      bodyHtml: renderParagraphBody(data.challenge_text),
      imageKey: 'challenge',
    },
    {
      eyebrow: 'Nuestra solución',
      title: data.solution_title,
      bodyHtml: renderParagraphBody(data.solution_text),
      imageKey: 'solution',
    },
    {
      eyebrow: 'Beneficios clave',
      title: 'Lo que gana ' + data.company_name,
      bodyHtml: benefitsBody,
      imageKey: 'benefits',
    },
    {
      eyebrow: '¿Cómo funciona?',
      title: 'Proceso de implementación',
      bodyHtml: stepsBody,
      imageKey: 'how_it_works',
    },
    ...extraSlides,
    {
      eyebrow: 'Inversión',
      title: 'Precio de la propuesta',
      bodyHtml: pricingBody,
      imageKey: 'pricing',
    },
    {
      eyebrow: 'Próximos pasos',
      title: 'Cómo seguimos',
      bodyHtml: nextStepsBody,
      imageKey: 'next_steps',
    },
  ];

  // In deck mode every slide but the active one is display:none, and browsers
  // do not fetch the background image of an element that is not rendered: each
  // photo used to start downloading only when its slide appeared (measured on
  // 2026-09-23: only cover.jpg on load, challenge.jpg after the first arrow),
  // so the client saw a bare gradient for a moment on every slide. Preloading
  // them all from <head> puts them in the cache before anyone presses "next".
  const preloadLinks = [...new Set([images.cover, ...contentSlides.map((s) => images[s.imageKey])].filter(Boolean))]
    .map((url) => `<link rel="preload" as="image" href="${escapeHtml(url)}" />`)
    .join('\n');

  const slides = [
    renderCoverSlide({
      company_name: data.company_name,
      client_name: data.client_name,
      index: 0,
      imageUrl: images.cover,
    }),
    // The badge number is computed, not hardcoded: inserting a section used
    // to mean renumbering every slide after it by hand.
    ...contentSlides.map((s, i) =>
      renderContentSlide({
        index: i + 2,
        eyebrow: s.eyebrow,
        title: s.title,
        bodyHtml: s.bodyHtml,
        imageUrl: images[s.imageKey],
      })
    ),
    renderClosingSlide(),
  ];

  return `<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Propuesta AutomatizaTech · ${escapeHtml(data.company_name)}</title>
${preloadLinks}
<style>${STYLE}</style>
</head>
<body>
<div class="deck">
${slides.join('\n')}
</div>
${renderDeckControls()}
<script>${SCRIPT}</script>
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
