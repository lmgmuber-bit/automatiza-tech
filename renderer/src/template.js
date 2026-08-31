const { escapeHtml } = require('./escape');

const LOGO_URL =
  'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech%20+%20slogan.png';

const CONTACTS = [
  { label: 'contacto@automatizatech.cl', href: 'mailto:contacto@automatizatech.cl' },
  { label: 'automatizatech.cl', href: 'https://automatizatech.cl' },
  { label: '+56 9 2700 2984', href: 'https://wa.me/56927002984' },
  { label: '@automatizatech.cl', href: 'https://instagram.com/automatizatech.cl' },
];

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
      `<a class="contact-row" href="${c.href}" target="_blank" rel="noopener noreferrer">${escapeHtml(
        c.label
      )}</a>`
  ).join('');
  return `
    <section class="slide slide-closing">
      <div class="closing-left">
        <a href="https://automatizatech.cl" target="_blank" rel="noopener noreferrer"><img class="closing-logo" src="${LOGO_URL}" alt="AutomatizaTech" /></a>
      </div>
      <div class="closing-right">
        <h2>Hablemos</h2>
        <div class="contact-list">${rows}</div>
      </div>
    </section>`;
}

function renderDeckControls() {
  return `
  <div class="at-bar" role="toolbar" aria-label="Controles de la presentación">
    <button class="at-nav" id="at-prev" type="button" aria-label="Lámina anterior">&#8249;</button>
    <span class="at-nav" id="at-counter">1 / 8</span>
    <button class="at-nav" id="at-next" type="button" aria-label="Lámina siguiente">&#8250;</button>
    <button id="at-toggle" type="button">Ver todo</button>
  </div>
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
  .at-watermark { position: absolute; top: 32px; left: 32px; z-index: 3; opacity: .4; }
  .at-watermark img { width: 76px; display: block; }
  .accent-bar { width: 64px; height: 5px; background: #00d9c0; border-radius: 3px; margin: 20px 0; }
  .eyebrow { color: #00d9c0; font-size: 20px; letter-spacing: .15em; text-transform: uppercase; font-weight: 700; }
  .slide-cover .slide-body { position: absolute; left: 64px; right: 64px; bottom: 72px; z-index: 2; }
  .slide-cover h1 { color: #fff; font-size: 72px; font-weight: 800; max-width: 80%; }
  .slide-cover .lede { color: #c9d4e0; font-size: 28px; margin-top: 12px; }
  .slide-content .slide-text { position: absolute; left: 72px; top: 120px; width: 46%; z-index: 2; }
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
  .contact-row { color: #dbe4ee; font-size: 24px; text-decoration: none; border-bottom: 1px solid rgba(0,217,192,.35); padding-bottom: 2px; }
  .contact-row:hover { color: #00d9c0; border-bottom-color: #00d9c0; }

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
    body.mode-list .at-nav { display: none; }

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
    #at-rotate-skip { margin-top: 22px; font-family: inherit; font-size: 15px; color: #dbe4ee; cursor: pointer;
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
      .at-progress span { animation: none !important; transition: none !important; }
    }
  }

  @media print {
    /* Both chrome elements must be gone from the PDF, or the empty progress
       bar tacks a stray blank page onto the end. */
    .at-bar, .at-progress, .at-rotate { display: none !important; }
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
  window.addEventListener('orientationchange', updateRotateHint);
  if (window.screen && window.screen.orientation && window.screen.orientation.addEventListener) {
    window.screen.orientation.addEventListener('change', updateRotateHint);
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
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Propuesta AutomatizaTech · ${escapeHtml(data.company_name)}</title>
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
