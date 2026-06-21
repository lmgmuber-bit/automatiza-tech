/* AutomatizaTech — Home Premium runtime
 * Vanilla port of the Claude Design React component (support.js is NOT shipped).
 * Reimplements: sc-if visibility, style-hover, {{handler}} clicks, intro WebGL,
 * scroll-driven 3D point-cloud, anime.js dot-grid, pointer tilt, scroll reveal,
 * parallax, progress bar, theme/menu/modal/chat. All motion guards prefers-reduced-motion.
 */
(function () {
  'use strict';

  var IMG = (window.AT_IMG || 'assets').replace(/\/+$/, '');
  var prefersReduce = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var reduceMotion = prefersReduce; // design default reduceMotion=false; honor OS setting

  var state = { menuOpen: false, chatOpen: false, modalOpen: false, scheduled: false, theme: 'dark', modalMode: 'diagnostico', vw: window.innerWidth };

  // module-scoped effect handles
  var bg3dWrap = null, bg3dMat = null, bg3dRenderer = null, bg3dRAF = 0, bg3dStopped = false, bg3dOpacity = 1;
  var dots = null, dotCols = 22, dotRows = 14, idleT = 0, animeRetry = 0;
  var revealed = new Set();
  var parallaxEls = [], scrollP = 0, progEl = null;
  var tiltBuilt = false, tiltActive = null;

  /* ---------- sc-if / handlers / hover ---------- */
  function vals() {
    var vw = state.vw;
    return {
      isDesktop: vw >= 920, isMobile: vw < 920,
      isDark: state.theme !== 'light', isLight: state.theme === 'light',
      menuOpen: state.menuOpen, chatOpen: state.chatOpen,
      modalOpen: state.modalOpen, scheduled: state.scheduled,
      notScheduled: !state.scheduled, showAssistant: true
    };
  }
  function applyScIf() {
    var v = vals();
    document.querySelectorAll('sc-if[data-when]').forEach(function (el) {
      // explicit 'contents' (not '') so we override the CSS FOUC-default
      // display:none on state-driven sc-if (modal/menu/chat/isLight).
      el.style.display = v[el.getAttribute('data-when')] ? 'contents' : 'none';
    });
  }
  function setState(patch) { Object.assign(state, patch); applyScIf(); applyTheme(); }

  // Modal "Diagnóstico | Demo" toggle: swaps title/subtitle, preselects the
  // "interés" option and writes the hidden intent field Codex reads.
  var MODE = {
    diagnostico: {
      title: 'Agenda tu diagnóstico gratis',
      sub: '15 a 30 min, sin costo, te ayuda Tech',
      interes: 'Diagnóstico gratis (recomendado)'
    },
    demo: {
      title: 'Agenda tu demo en vivo',
      sub: 'Te mostramos el portal y los asistentes funcionando',
      interes: 'Demo del portal omnicliente'
    }
  };
  function applyMode() {
    var m = MODE[state.modalMode] || MODE.diagnostico;
    var t = document.getElementById('at-modal-title'); if (t) t.textContent = m.title;
    var s = document.getElementById('at-modal-sub'); if (s) s.textContent = m.sub;
    var intent = document.getElementById('at-intent'); if (intent) intent.value = state.modalMode;
    var sel = document.getElementById('at-modal-interes');
    if (sel) { for (var i = 0; i < sel.options.length; i++) { if (sel.options[i].text === m.interes) { sel.selectedIndex = i; break; } } }
    var diag = document.getElementById('at-mode-diag'), demo = document.getElementById('at-mode-demo');
    function paint(btn, on) {
      if (!btn) return;
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
      btn.style.background = on ? '#2dd4bf' : 'transparent';
      btn.style.color = on ? '#061018' : 'var(--text-2)';
    }
    paint(diag, state.modalMode === 'diagnostico');
    paint(demo, state.modalMode === 'demo');
  }

  // Capture UTM + landing path into the hidden form fields (no PII).
  function fillUtm() {
    try {
      var p = new URLSearchParams(location.search);
      var keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
      keys.forEach(function (k) {
        var incoming = p.get(k);
        if (incoming) window.localStorage && localStorage.setItem('at_' + k, incoming);
      });
      var get = function (k) { return p.get(k) || (window.localStorage && localStorage.getItem('at_' + k)) || ''; };
      var set = function (id, v) { var el = document.getElementById(id); if (el && v) el.value = v; };
      set('at-utm-source', get('utm_source'));
      set('at-utm-medium', get('utm_medium'));
      set('at-utm-campaign', get('utm_campaign'));
      var pg = document.getElementById('at-utm-page'); if (pg) pg.value = location.pathname;
    } catch (e) {}
  }

  function safeTrackingProps(extra) {
    var cfg = window.AT_HOME || {};
    var p = new URLSearchParams(location.search);
    var getStored = function (k) { try { return p.get(k) || localStorage.getItem('at_' + k) || ''; } catch (e) { return p.get(k) || ''; } };
    return Object.assign({
      landing: cfg.landing || 'home_premium',
      path: location.pathname,
      utm_source: getStored('utm_source'),
      utm_medium: getStored('utm_medium'),
      utm_campaign: getStored('utm_campaign'),
      utm_content: getStored('utm_content'),
      utm_term: getStored('utm_term')
    }, extra || {});
  }

  function track(name, props) {
    var payload = safeTrackingProps(props);
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(Object.assign({ event: name }, payload));
    if (window.posthog && typeof window.posthog.capture === 'function') window.posthog.capture(name, payload);
  }

  function setFormMessage(form, type, text) {
    var box = form.querySelector('[data-form-message]');
    if (!box) {
      box = document.createElement('div');
      box.setAttribute('data-form-message', '');
      box.style.cssText = 'display:none;border-radius:10px;padding:11px 13px;font-size:13px;line-height:1.45';
      form.insertBefore(box, form.firstChild);
    }
    box.textContent = text || '';
    box.style.display = text ? 'block' : 'none';
    box.style.border = type === 'error' ? '1px solid rgba(244,139,139,.38)' : '1px solid rgba(52,211,153,.34)';
    box.style.background = type === 'error' ? 'rgba(244,139,139,.10)' : 'rgba(52,211,153,.10)';
    box.style.color = type === 'error' ? '#f7b4b4' : '#86efac';
  }

  function appendTrackingFields(data) {
    var props = safeTrackingProps();
    Object.keys(props).forEach(function (k) {
      if (props[k] && !data.has(k)) data.append(k, props[k]);
    });
    data.append('referrer', document.referrer || '');
  }

  var H = {
    toggleMenu: function () { setState({ menuOpen: !state.menuOpen }); },
    closeMenu: function () { setState({ menuOpen: false }); },
    toggleChat: function () { setState({ chatOpen: !state.chatOpen }); },
    closeChat: function () { setState({ chatOpen: false }); },
    toggleTheme: function () { setState({ theme: state.theme === 'dark' ? 'light' : 'dark' }); },
    openModal: function (e) {
      if (e && e.preventDefault) e.preventDefault();
      var el = e && (e.currentTarget || e.target);
      track('cta_diagnostico_clicked', { intent: state.modalMode, cta_text: el ? (el.textContent || '').trim().slice(0, 80) : '' });
      setState({ modalOpen: true, menuOpen: false, chatOpen: false });
      applyMode();
      fillUtm();
    },
    closeModal: function () { setState({ modalOpen: false, scheduled: false }); },
    setMode: function (e) { var b = e.currentTarget || e.target; setState({ modalMode: b.getAttribute('data-mode') || 'diagnostico' }); applyMode(); },
    submitDemo: function (e) {
      if (e && e.preventDefault) e.preventDefault();
      var form = e && e.target;
      var cfg = window.AT_HOME || {};
      if (!form || !cfg.bookingWebhookUrl || !cfg.availabilityUrl) {
        if (form) setFormMessage(form, 'error', 'No pudimos enviar la solicitud. Recarga la pagina e intenta nuevamente.');
        return;
      }
      var submit = form.querySelector('[type="submit"]');
      var data = new FormData(form);
      appendTrackingFields(data);
      var scheduledDate = String(data.get('fecha') || '').trim();
      var scheduledTime = String(data.get('scheduled_time') || '').trim();
      var intent = String(data.get('intent') || state.modalMode || 'diagnostico').trim();
      var interest = String(data.get('interes') || '').trim();
      if (!scheduledDate || !scheduledTime) {
        setFormMessage(form, 'error', 'Elige fecha y hora disponible para agendar.');
        return;
      }
      var leadTemperature = (intent === 'demo' || scheduledDate) ? 'caliente' : 'tibio';
      var payload = {
        name: String(data.get('nombre_negocio') || '').trim(),
        email: String(data.get('correo') || '').trim(),
        phone: String(data.get('whatsapp') || '').trim(),
        scheduled_date: scheduledDate,
        scheduled_time: scheduledTime,
        source: 'home_premium_web_' + intent,
        session_id: 'home_premium_' + intent + '_' + Date.now(),
        intent: intent,
        interest: interest,
        page: String(data.get('page') || location.pathname || '').trim(),
        referrer: String(data.get('referrer') || document.referrer || '').trim(),
        utm_source: String(data.get('utm_source') || '').trim(),
        utm_medium: String(data.get('utm_medium') || '').trim(),
        utm_campaign: String(data.get('utm_campaign') || '').trim(),
        utm_content: String(data.get('utm_content') || '').trim(),
        utm_term: String(data.get('utm_term') || '').trim()
      };
      track('diagnostico_form_started', { intent: intent, interest: interest });
      if (submit) { submit.disabled = true; submit.style.opacity = '.72'; submit.style.cursor = 'wait'; }
      setFormMessage(form, '', '');
      fetch(cfg.availabilityUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ date: scheduledDate })
      })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
        .then(function (res) {
          if (!res.ok || !res.json || res.json.isFullDay) throw new Error('Ese dia ya no tiene horarios disponibles.');
          var busy = Array.isArray(res.json.busySlots) ? res.json.busySlots : [];
          if (busy.indexOf(scheduledTime.slice(0, 5)) !== -1) throw new Error('Ese horario ya no esta disponible. Elige otro horario.');
          // Opción A: mismo webhook n8n que el chat (action saveLead). n8n crea el
          // lead + el evento Google Calendar + Meet. El bloque `marketing` viaja para
          // que n8n persista la atribución (el hook de /leads ya no corre en este path).
          return fetch(cfg.bookingWebhookUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'saveLead',
              sessionId: payload.session_id,
              leadData: {
                name: payload.name,
                email: payload.email,
                phone: payload.phone,
                scheduled_date: payload.scheduled_date,
                scheduled_time: payload.scheduled_time
              },
              marketing: {
                source: payload.source,
                intent: payload.intent,
                interest: payload.interest,
                lead_temperature: leadTemperature,
                page: payload.page,
                referrer: payload.referrer,
                utm_source: payload.utm_source,
                utm_medium: payload.utm_medium,
                utm_campaign: payload.utm_campaign,
                utm_content: payload.utm_content,
                utm_term: payload.utm_term
              }
            })
          });
        })
        .then(function (r) {
          if (!r.ok) throw new Error('No pudimos registrar la cita. Intenta nuevamente.');
          return r.text();
        })
        .then(function () {
          track('lead_submit', { intent: intent, interest: interest, lead_temperature: leadTemperature });
          // Atribución WP-side por session_id (fire-and-forget; no bloquea el éxito).
          if (cfg.attrAjaxUrl && cfg.attrNonce) {
            try {
              var ad = new URLSearchParams();
              ad.set('action', 'at_home_premium_attr');
              ad.set('nonce', cfg.attrNonce);
              ad.set('session_id', payload.session_id);
              ad.set('scheduled_date', payload.scheduled_date);
              ['source', 'intent', 'interest', 'page', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(function (k) { ad.set(k, payload[k] || ''); });
              fetch(cfg.attrAjaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: ad.toString() }).catch(function () {});
            } catch (e) {}
          }
          setState({ scheduled: true });
          form.reset();
          fillUtm();
          applyMode();
        })
        .catch(function (err) {
          setFormMessage(form, 'error', err && err.message ? err.message : 'No pudimos enviar la solicitud. Intenta nuevamente.');
        })
        .finally(function () {
          if (submit) { submit.disabled = false; submit.style.opacity = ''; submit.style.cursor = ''; }
        });
    },
    stopProp: function (e) { if (e && e.stopPropagation) e.stopPropagation(); }
  };
  function wire() {
    document.querySelectorAll('[data-act]').forEach(function (el) {
      var fn = H[el.getAttribute('data-act')];
      if (fn) el.addEventListener('click', fn);
    });
    document.querySelectorAll('[data-act-submit]').forEach(function (el) {
      var fn = H[el.getAttribute('data-act-submit')];
      if (fn) el.addEventListener('submit', fn);
    });
    document.querySelectorAll('[data-hover]').forEach(function (el) {
      var hov = el.getAttribute('data-hover');
      el.addEventListener('mouseenter', function () { el.__s = el.getAttribute('style') || ''; el.style.cssText = el.__s + ';' + hov; });
      el.addEventListener('mouseleave', function () { if (el.__s != null) el.setAttribute('style', el.__s); });
    });
  }

  /* ---------- theme ---------- */
  function applyTheme() {
    document.documentElement.setAttribute('data-theme', state.theme);
    var light = state.theme === 'light';
    if (bg3dWrap) bg3dWrap.style.display = 'block';
    if (bg3dMat && bg3dMat.color) { bg3dMat.color.setHex(light ? 0x0d9488 : 0x2dd4bf); bg3dMat.opacity = light ? 0.62 : 0.85; }
    if (dots) {
      var c = light ? '#0d9488' : '#2dd4bf', o = light ? '0.30' : '0.16';
      dots.forEach(function (d) { d.style.background = c; if (!d.style.transform || d.style.transform === 'scale(1)') d.style.opacity = o; });
    }
  }

  /* ---------- reveal ---------- */
  function reveal(el) { el.style.opacity = '1'; el.style.transform = 'none'; revealed.add(el); }
  function check() {
    var vh = window.innerHeight;
    document.querySelectorAll('[data-reveal]').forEach(function (n) {
      if (!n.__d) { n.__d = true; var d = n.getAttribute('data-reveal-delay'); if (d) n.style.transitionDelay = d + 'ms'; }
      if (revealed.has(n)) return;
      if (n.getBoundingClientRect().top < vh * 0.92) reveal(n);
    });
  }

  /* ---------- scroll fx (parallax + progress + bg3d ramp) ---------- */
  function scrollFx() {
    var vh = window.innerHeight;
    if (!reduceMotion) {
      var cy = vh / 2;
      parallaxEls.forEach(function (el) {
        var sp = parseFloat(el.getAttribute('data-parallax')) || 0;
        var r = el.getBoundingClientRect();
        var off = (r.top + r.height / 2 - cy) * sp;
        el.style.transform = 'translate3d(0,' + off.toFixed(1) + 'px,0)';
      });
    }
    var h = document.documentElement.scrollHeight - vh;
    var sp2 = h > 0 ? window.scrollY / h : 0;
    scrollP = sp2;
    bg3dOpacity = Math.max(0, Math.min(1, (window.scrollY - vh * 0.35) / (vh * 0.7))) * 0.9 + 0.1;
    if (progEl) progEl.style.width = (sp2 * 100) + '%';
  }

  /* ---------- tilt ---------- */
  function initTilt() {
    if (tiltBuilt) return; tiltBuilt = true;
    if (reduceMotion) return;
    function ensureFx(card) {
      if (card._fx && card._fx.ov.parentNode === card) return card._fx;
      var rad = getComputedStyle(card).borderRadius || '16px';
      var ov = document.createElement('div');
      ov.style.cssText = 'position:absolute;inset:0;border-radius:' + rad + ';pointer-events:none;opacity:0;transition:opacity .35s ease;z-index:6;box-shadow:inset 0 0 0 1.5px var(--accent-2)';
      var glare = document.createElement('div');
      glare.style.cssText = 'position:absolute;inset:0;border-radius:' + rad + ';background:radial-gradient(180px circle at 50% 0%, rgba(45,212,191,.22), transparent 60%)';
      ov.appendChild(glare);
      var defs = [
        'top:11px;left:11px;border-top:1.6px solid;border-left:1.6px solid',
        'top:11px;right:11px;border-top:1.6px solid;border-right:1.6px solid',
        'bottom:11px;left:11px;border-bottom:1.6px solid;border-left:1.6px solid',
        'bottom:11px;right:11px;border-bottom:1.6px solid;border-right:1.6px solid'
      ];
      var brackets = defs.map(function (d) {
        var s = document.createElement('span');
        s.style.cssText = 'position:absolute;width:15px;height:15px;border-color:var(--accent-2);border-radius:4px;opacity:0;transform:scale(.55);transition:opacity .3s ease,transform .35s cubic-bezier(.22,1,.36,1);' + d;
        ov.appendChild(s); return s;
      });
      card.appendChild(ov);
      card._fx = { ov: ov, glare: glare, brackets: brackets };
      return card._fx;
    }
    document.addEventListener('pointermove', function (e) {
      var card = e.target.closest && e.target.closest('[data-tilt]');
      if (!card) return;
      var r = card.getBoundingClientRect();
      var px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
      var rx = (0.5 - py) * 9, ry = (px - 0.5) * 11;
      card.style.transform = 'perspective(950px) rotateX(' + rx.toFixed(2) + 'deg) rotateY(' + ry.toFixed(2) + 'deg) translateZ(10px)';
      var fx = ensureFx(card);
      fx.glare.style.background = 'radial-gradient(200px circle at ' + (px * 100).toFixed(1) + '% ' + (py * 100).toFixed(1) + '%, rgba(94,234,212,.24), transparent 60%)';
    }, { passive: true });
    document.addEventListener('pointerover', function (e) {
      var card = e.target.closest && e.target.closest('[data-tilt]');
      if (!card || card === tiltActive) return;
      tiltActive = card;
      card.style.transition = 'transform .12s ease-out, border-color .25s, box-shadow .25s';
      var fx = ensureFx(card);
      fx.ov.style.opacity = '1';
      fx.brackets.forEach(function (b) { b.style.opacity = '1'; b.style.transform = 'scale(1)'; });
    });
    document.addEventListener('pointerout', function (e) {
      var card = e.target.closest && e.target.closest('[data-tilt]');
      if (!card) return;
      var to = e.relatedTarget;
      if (to && card.contains(to)) return;
      card.style.transition = 'transform .55s cubic-bezier(.22,1,.36,1), border-color .25s, box-shadow .25s';
      card.style.transform = '';
      if (card._fx) { card._fx.ov.style.opacity = '0'; card._fx.brackets.forEach(function (b) { b.style.opacity = '0'; b.style.transform = 'scale(.55)'; }); }
      if (tiltActive === card) tiltActive = null;
    });
  }

  /* ---------- background 3D point-cloud ---------- */
  function initBg3D() {
    var THREE = window.THREE;
    var wrap = document.createElement('div');
    wrap.id = 'at-bg3d';
    document.body.insertBefore(wrap, document.body.firstChild);
    bg3dWrap = wrap;
    wrap.style.display = (state.theme === 'light') ? 'none' : 'block';
    if (!THREE || reduceMotion) return;

    var canvas = document.createElement('canvas'); wrap.appendChild(canvas);
    var renderer;
    try { renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true }); } catch (e) { return; }
    bg3dRenderer = renderer;
    var W = window.innerWidth, H = window.innerHeight;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
    renderer.setSize(W, H);
    var scene = new THREE.Scene();
    var camera = new THREE.PerspectiveCamera(60, W / H, 0.1, 100);
    camera.position.z = 10;

    var N = 2200;
    var mkShape = function (fn) { var a = new Float32Array(N * 3); for (var i = 0; i < N; i++) { var p = fn(i, i / N); a[i * 3] = p[0]; a[i * 3 + 1] = p[1]; a[i * 3 + 2] = p[2]; } return a; };
    var TAU = Math.PI * 2, GA = Math.PI * (1 + Math.sqrt(5));
    var sphere = mkShape(function (i, t) { var phi = Math.acos(1 - 2 * t), th = GA * i, R = 4.0; return [R * Math.sin(phi) * Math.cos(th), R * Math.sin(phi) * Math.sin(th), R * Math.cos(phi)]; });
    var helix = mkShape(function (i, t) { var turns = 5, a = t * TAU * turns, R = 2.6; return [R * Math.cos(a), (t - 0.5) * 9, R * Math.sin(a)]; });
    var wave = mkShape(function (i) { var c = 50, x = (i % c) / c - 0.5, z = Math.floor(i / c) / (N / c) - 0.5; return [x * 11, Math.sin(x * 7) * Math.cos(z * 7) * 1.7, z * 11]; });
    var torus = mkShape(function (i, t) { var u = t * TAU * 7 % TAU, v = GA * i % TAU, Rr = 3.1, r = 1.25; return [(Rr + r * Math.cos(v)) * Math.cos(u), r * Math.sin(v), (Rr + r * Math.cos(v)) * Math.sin(u)]; });
    var cube = mkShape(function (i) { var s = 7.0; var face = i % 6; var x = (Math.random() - 0.5), y = (Math.random() - 0.5), z = (Math.random() - 0.5); var h = 0.5; if (face === 0) x = h; if (face === 1) x = -h; if (face === 2) y = h; if (face === 3) y = -h; if (face === 4) z = h; if (face === 5) z = -h; return [x * s, y * s, z * s]; });
    var shapes = [sphere, helix, wave, torus, cube];

    var pos = new Float32Array(N * 3); pos.set(sphere);
    var geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    var light0 = state.theme === 'light';
    var mat = new THREE.PointsMaterial({ color: light0 ? 0x0d9488 : 0x2dd4bf, size: 0.055, transparent: true, opacity: light0 ? 0.62 : 0.85, blending: light0 ? THREE.NormalBlending : THREE.AdditiveBlending, depthWrite: false });
    bg3dMat = mat;
    var cloud = new THREE.Points(geo, mat); scene.add(cloud);

    var arr = geo.attributes.position.array;
    var tgt = new Float32Array(N * 3);
    var now = function () { return (window.performance && performance.now) ? performance.now() : Date.now(); };
    var last = now();
    var loop = function () {
      if (bg3dStopped) return;
      bg3dRAF = requestAnimationFrame(loop);
      if (document.hidden) return;
      var tNow = now(); var dt = Math.min(0.05, (tNow - last) / 1000); last = tNow;
      var sp = scrollP || 0;
      var seg = sp * (shapes.length - 1);
      var ai = Math.floor(seg); if (ai > shapes.length - 2) ai = shapes.length - 2;
      var f = seg - ai, A = shapes[ai], B = shapes[ai + 1];
      var i;
      for (i = 0; i < N * 3; i++) tgt[i] = A[i] + (B[i] - A[i]) * f;
      var k = 1 - Math.pow(0.0025, dt);
      for (i = 0; i < N * 3; i++) arr[i] += (tgt[i] - arr[i]) * k;
      geo.attributes.position.needsUpdate = true;
      cloud.rotation.y += dt * 0.12;
      cloud.rotation.x = Math.sin(tNow * 0.0001) * 0.25;
      camera.position.z = 10 - sp * 1.6;
      wrap.style.opacity = (bg3dOpacity != null ? bg3dOpacity : 1);
      renderer.render(scene, camera);
    };
    bg3dRAF = requestAnimationFrame(loop);
    window.addEventListener('resize', function () { W = window.innerWidth; H = window.innerHeight; renderer.setSize(W, H); camera.aspect = W / H; camera.updateProjectionMatrix(); });
  }

  /* ---------- intro overlay (chaos -> order) ---------- */
  function initIntro() {
    // Premium UX: play the brand intro only once per browser session, so
    // internal navigation isn't blocked by the 4.4s sequence every time.
    try { if (sessionStorage.getItem('at_intro_seen')) return; sessionStorage.setItem('at_intro_seen', '1'); } catch (e) {}
    var ov = document.createElement('div');
    ov.id = 'at-intro';
    ov.style.cssText = 'position:fixed;inset:0;z-index:100000;background:radial-gradient(900px 700px at 50% 42%,#0c1830,#05080f 78%);display:flex;align-items:center;justify-content:center;overflow:hidden;opacity:1;transition:opacity .9s ease';
    var canvas = document.createElement('canvas');
    canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;display:block';
    var ui = document.createElement('div');
    ui.style.cssText = "position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;text-align:center;padding:24px;opacity:0";
    ui.innerHTML = '<img src="' + IMG + '/at-logo.png" alt="AutomatizaTech" style="width:62px;height:62px;object-fit:contain;filter:drop-shadow(0 8px 24px rgba(45,212,191,.45));margin-bottom:22px"/>' +
      '<div style="font-family:\'Space Grotesk\';font-weight:600;font-size:clamp(24px,4.4vw,40px);letter-spacing:-.02em;color:#f4f7fb">Automatiza<span style="color:#2dd4bf">Tech</span></div>' +
      '<div style="font-family:\'JetBrains Mono\';font-size:clamp(9px,1.4vw,12px);letter-spacing:.34em;color:#5fb8ad;margin-top:14px">INTELIGENCIA PARA TU NEGOCIO</div>';
    var skip = document.createElement('button');
    skip.type = 'button';
    skip.style.cssText = "position:absolute;right:22px;bottom:20px;z-index:3;display:inline-flex;align-items:center;gap:8px;font-family:'JetBrains Mono';font-size:11px;letter-spacing:.1em;color:#8fa1b6;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:100px;padding:9px 16px;cursor:pointer";
    skip.innerHTML = 'SALTAR INTRO <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
    var barWrap = document.createElement('div');
    barWrap.style.cssText = 'position:absolute;left:50%;bottom:26px;transform:translateX(-50%);z-index:3;width:148px;height:2px;background:rgba(255,255,255,.1);border-radius:2px;overflow:hidden';
    var bar = document.createElement('span');
    bar.style.cssText = 'display:block;height:100%;width:0;background:linear-gradient(90deg,#2dd4bf,#34d399)';
    barWrap.appendChild(bar);
    ov.appendChild(canvas); ov.appendChild(ui); ov.appendChild(skip); ov.appendChild(barWrap);
    document.body.appendChild(ov);

    var prevOverflow = document.body.style.overflow;
    try { document.body.style.overflow = 'hidden'; window.scrollTo(0, 0); } catch (e) {}

    var done = false, introRAF = 0, introAuto = 0, introUiT = 0, introRenderer = null, introResize = null;
    var finish = function () {
      if (done) return; done = true;
      try { if (introRAF) cancelAnimationFrame(introRAF); } catch (e) {}
      try { clearTimeout(introAuto); } catch (e) {}
      try { clearTimeout(introUiT); } catch (e) {}
      try { if (introResize) window.removeEventListener('resize', introResize); } catch (e) {}
      ov.style.opacity = '0'; ov.style.pointerEvents = 'none';
      document.body.style.overflow = prevOverflow || '';
      setTimeout(function () {
        try { if (introRenderer) { introRenderer.dispose(); introRenderer.forceContextLoss && introRenderer.forceContextLoss(); } } catch (e) {}
        try { ov.remove(); } catch (e) {}
      }, 1000);
    };
    skip.addEventListener('click', finish);

    var THREE = window.THREE;
    if (reduceMotion) { finish(); return; }
    if (!THREE) { ui.style.opacity = '1'; introAuto = setTimeout(finish, 1500); return; }

    var renderer;
    try { renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true }); }
    catch (e) { ui.style.opacity = '1'; introAuto = setTimeout(finish, 1500); return; }
    introRenderer = renderer;
    var W = window.innerWidth, H = window.innerHeight;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setSize(W, H);
    var scene = new THREE.Scene();
    var camera = new THREE.PerspectiveCamera(62, W / H, 0.1, 100);
    camera.position.z = 12;

    var COUNT = 2600;
    var pos = new Float32Array(COUNT * 3), chaos = new Float32Array(COUNT * 3), order = new Float32Array(COUNT * 3);
    for (var i = 0; i < COUNT; i++) {
      chaos[i * 3] = (Math.random() - 0.5) * 46; chaos[i * 3 + 1] = (Math.random() - 0.5) * 46; chaos[i * 3 + 2] = (Math.random() - 0.5) * 46;
      var t = i / COUNT, phi = Math.acos(1 - 2 * t), theta = Math.PI * (1 + Math.sqrt(5)) * i, R = 3.5;
      order[i * 3] = R * Math.sin(phi) * Math.cos(theta); order[i * 3 + 1] = R * Math.sin(phi) * Math.sin(theta); order[i * 3 + 2] = R * Math.cos(phi);
      pos[i * 3] = chaos[i * 3]; pos[i * 3 + 1] = chaos[i * 3 + 1]; pos[i * 3 + 2] = chaos[i * 3 + 2];
    }
    var geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    var mat = new THREE.PointsMaterial({ color: 0x2dd4bf, size: 0.05, transparent: true, opacity: 0.92, blending: THREE.AdditiveBlending, depthWrite: false });
    var cloud = new THREE.Points(geo, mat); scene.add(cloud);

    var DUR = 4400;
    var start = (window.performance && performance.now) ? performance.now() : Date.now();
    var now = function () { return (window.performance && performance.now) ? performance.now() : Date.now(); };
    var ease = function (x) { return x < 0.5 ? 2 * x * x : 1 - Math.pow(-2 * x + 2, 2) / 2; };
    var arr = geo.attributes.position.array;
    var tick = function () {
      if (done) return;
      var p = Math.min(1, (now() - start) / DUR);
      var m = ease(Math.min(1, p / 0.78));
      for (var i = 0; i < COUNT; i++) {
        arr[i * 3] = chaos[i * 3] + (order[i * 3] - chaos[i * 3]) * m;
        arr[i * 3 + 1] = chaos[i * 3 + 1] + (order[i * 3 + 1] - chaos[i * 3 + 1]) * m;
        arr[i * 3 + 2] = chaos[i * 3 + 2] + (order[i * 3 + 2] - chaos[i * 3 + 2]) * m;
      }
      geo.attributes.position.needsUpdate = true;
      cloud.rotation.y = p * Math.PI * 1.3;
      cloud.rotation.x = Math.sin(p * Math.PI) * 0.32;
      camera.position.z = 12 - 4.2 * ease(Math.min(1, p / 0.82));
      mat.opacity = 0.92 - 0.5 * Math.max(0, (p - 0.82) / 0.18);
      if (bar) bar.style.width = (p * 100).toFixed(1) + '%';
      renderer.render(scene, camera);
      if (p < 1) introRAF = requestAnimationFrame(tick);
    };
    introRAF = requestAnimationFrame(tick);
    introUiT = setTimeout(function () {
      if (window.anime) window.anime({ targets: ui, opacity: [0, 1], translateY: [14, 0], duration: 1000, easing: 'easeOutQuad' });
      else ui.style.opacity = '1';
    }, 2400);
    introResize = function () { W = window.innerWidth; H = window.innerHeight; renderer.setSize(W, H); camera.aspect = W / H; camera.updateProjectionMatrix(); };
    window.addEventListener('resize', introResize);
    introAuto = setTimeout(finish, DUR + 1000);
  }

  /* ---------- hero dot grid (anime.js) ---------- */
  function initDotGrid() {
    var grid = document.getElementById('at-dotgrid');
    if (!grid) return;
    var cols = 22, rows = 14;
    var frag = document.createDocumentFragment();
    var arr = [];
    for (var i = 0; i < cols * rows; i++) {
      var d = document.createElement('span');
      d.style.cssText = 'width:4px;height:4px;border-radius:50%;background:#2dd4bf;opacity:.16;transform:scale(1);will-change:transform,opacity';
      frag.appendChild(d); arr.push(d);
    }
    grid.appendChild(frag);
    dots = arr; dotCols = cols; dotRows = rows;
    if (reduceMotion || typeof window.anime === 'undefined') {
      dots.forEach(function (d) { d.style.opacity = '.16'; });
      if (typeof window.anime === 'undefined' && !reduceMotion) {
        animeRetry = setTimeout(function () { if (typeof window.anime !== 'undefined') runDotGrid(); }, 600);
      }
      return;
    }
    runDotGrid();
  }
  function runDotGrid() {
    var anime = window.anime;
    if (!anime || !dots) return;
    var cols = dotCols, rows = dotRows;
    anime({
      targets: dots,
      opacity: [{ value: 0.55, duration: 480 }, { value: 0.16, duration: 760 }],
      scale: [{ value: 2.1, duration: 480 }, { value: 1, duration: 760 }],
      easing: 'easeOutQuad',
      delay: anime.stagger(34, { grid: [cols, rows], from: 'center' })
    });
    var idle = function () {
      anime({
        targets: dots,
        opacity: [{ value: 0.5, duration: 600 }, { value: 0.16, duration: 900 }],
        scale: [{ value: 1.9, duration: 600 }, { value: 1, duration: 900 }],
        easing: 'easeInOutSine',
        delay: anime.stagger(90, { grid: [cols, rows], from: 'center' }),
        complete: function () { idleT = setTimeout(idle, 2600); }
      });
    };
    idleT = setTimeout(idle, 2000);
    var host = document.getElementById('top');
    if (host) {
      var ptT = 0;
      host.addEventListener('pointermove', function (ev) {
        var n = Date.now(); if (n - ptT < 520) return; ptT = n;
        var r = host.getBoundingClientRect();
        var gx = Math.round(((ev.clientX - r.left) / r.width) * (cols - 1));
        var gy = Math.round(((ev.clientY - r.top) / r.height) * (rows - 1));
        var idx = Math.max(0, Math.min(cols * rows - 1, gy * cols + gx));
        anime({
          targets: dots,
          opacity: [{ value: 0.65, duration: 220 }, { value: 0.14, duration: 700 }],
          scale: [{ value: 2.4, duration: 220 }, { value: 1, duration: 700 }],
          easing: 'easeOutQuad',
          delay: anime.stagger(55, { grid: [cols, rows], from: idx })
        });
      });
    }
  }

  /* ---------- boot ---------- */
  function boot() {
    if (reduceMotion) { var root = document.getElementById('at-root'); if (root) root.setAttribute('data-motion', 'off'); }

    initIntro();

    var nav = document.getElementById('at-nav');
    var onScroll = function () { if (!nav) return; if (window.scrollY > 16) nav.setAttribute('data-scrolled', ''); else nav.removeAttribute('data-scrolled'); };
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();

    window.addEventListener('resize', function () { setState({ vw: window.innerWidth, menuOpen: false }); check(); });

    window.addEventListener('scroll', check, { passive: true });
    requestAnimationFrame(check);
    setTimeout(function () { document.querySelectorAll('[data-reveal]').forEach(reveal); }, 1800);

    applyTheme();

    parallaxEls = Array.prototype.slice.call(document.querySelectorAll('[data-parallax]'));
    progEl = document.getElementById('at-progress');
    window.addEventListener('scroll', scrollFx, { passive: true }); scrollFx();

    initDotGrid();
    initBg3D();
    initTilt();

    wire();
    applyScIf();
    fillUtm();
    applyMode();
    track('landing_p0_view', { template: 'AT Home Premium' });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
