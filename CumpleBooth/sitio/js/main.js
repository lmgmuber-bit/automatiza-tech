/* CumpleClick — main.js
   Motion: Lenis (scroll suave, NO hijack) + GSAP ScrollTrigger (reveals).
   3D: carga diferida vía import() dinámico, con fallback estático.
   Reglas duras:
   - prefers-reduced-motion → sin Lenis, sin reveals, sin 3D.
   - El CTA de WhatsApp nunca se anima ni se retrasa.
   - Sin scroll hijacking: Lenis con defaults (rueda/teclado/barra intactos).
*/
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Video del kiosco: carga perezosa al acercarse ---------- */
  function initLazyVideo() {
    var video = document.getElementById('kiosco-video');
    if (!video) return;
    var source = video.querySelector('source[data-src]');
    var load = function () {
      if (!source || source.src) return;
      source.src = source.getAttribute('data-src');
      video.load();
      var play = video.play();
      if (play && play.catch) play.catch(function () { /* autoplay bloqueado: queda el poster */ });
    };
    if (!('IntersectionObserver' in window)) { load(); return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { load(); io.disconnect(); }
      });
    }, { rootMargin: '400px' });
    io.observe(video);
  }

  /* ---------- GSAP reveals (solo transform/opacity; contenido visible sin JS) ---------- */
  function initReveals() {
    if (reduceMotion) return;
    if (!window.gsap || !window.ScrollTrigger) return;
    window.gsap.registerPlugin(window.ScrollTrigger);

    /* .mundo va aparte (entrada con perspectiva); excluirlo del reveal genérico
       o el segundo gsap.from captura opacity:0 como estado final */
    var items = window.gsap.utils.toArray('[data-reveal]:not(.mundo)');
    items.forEach(function (el, i) {
      window.gsap.from(el, {
        y: 36,
        opacity: 0,
        duration: 0.7,
        ease: 'power3.out',
        delay: (i % 3) * 0.06,
        scrollTrigger: { trigger: el, start: 'top 88%', once: true }
      });
    });

    /* Vitrina: entrada con leve perspectiva (profundidad, sin 3D real) */
    window.gsap.utils.toArray('.mundo').forEach(function (card, i) {
      window.gsap.from(card, {
        y: 44,
        opacity: 0,
        rotationX: 8,
        transformPerspective: 800,
        duration: 0.8,
        ease: 'power3.out',
        delay: (i % 4) * 0.07,
        scrollTrigger: { trigger: card, start: 'top 92%', once: true }
      });
    });
    /* NOTA fusión OpenCode+Claude 2026-07-27: [data-depth] y la salida del hero
       viven en initDepth() e initHeroParallax() (versiones Claude). No repetirlas
       aquí: un segundo gsap.from sobre el mismo elemento captura opacity:0 como
       estado final y el bloque nunca aparece (bug ya visto con .mundo). */
  }

  /* ---------- Modal del video Frozen: opt-in, nunca autoplay ----------
     El <source> arranca con data-src y solo se resuelve al abrir: quien no
     toca play no descarga los 3,6 MB. Al cerrar se pausa (no se descarga de
     nuevo si vuelve a abrir). */
  function initModalVideo() {
    var abrir = document.getElementById('btn-play-frozen');
    var modal = document.getElementById('modal-frozen');
    var cerrar = document.getElementById('btn-cerrar-frozen');
    var video = document.getElementById('video-frozen');
    if (!abrir || !modal || !video) return;

    var ultimoFoco = null;
    var main = document.querySelector('main');
    var fondo = [document.querySelector('.topbar'), document.querySelector('.footer')].filter(Boolean);
    if (main) {
      Array.prototype.forEach.call(main.children, function (child) {
        if (child !== modal) fondo.push(child);
      });
    }

    function elementosEnModal() {
      return Array.prototype.slice.call(modal.querySelectorAll('button:not([disabled]), video[controls], a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
    }

    function aislarFondo(aislar) {
      fondo.forEach(function (el) {
        if (aislar) {
          el.setAttribute('inert', '');
          el.setAttribute('aria-hidden', 'true');
        } else {
          el.removeAttribute('inert');
          el.removeAttribute('aria-hidden');
        }
      });
    }

    function abrirModal() {
      ultimoFoco = document.activeElement;
      var source = video.querySelector('source[data-src]');
      if (source && !source.src) {
        source.src = source.getAttribute('data-src');
        video.load();
      }
      modal.hidden = false;
      document.body.classList.add('modal-abierto');
      /* Sacar el foco del botón disparador antes de volver inerte el fondo;
         de otro modo Chromium lo envía a <body>. */
      if (cerrar) cerrar.focus(); else modal.focus();
      aislarFondo(true);
      var play = video.play();
      if (play && play.catch) play.catch(function () { /* queda el control manual */ });
    }

    function cerrarModal() {
      video.pause();
      modal.hidden = true;
      document.body.classList.remove('modal-abierto');
      aislarFondo(false);
      if (ultimoFoco && ultimoFoco.focus) ultimoFoco.focus();
    }

    abrir.addEventListener('click', abrirModal);
    if (cerrar) cerrar.addEventListener('click', cerrarModal);
    modal.querySelectorAll('[data-close]').forEach(function (el) {
      el.addEventListener('click', cerrarModal);
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !modal.hidden) cerrarModal();
      if (ev.key === 'Tab' && !modal.hidden) {
        var focos = elementosEnModal();
        if (!focos.length) return;
        var primero = focos[0];
        var ultimo = focos[focos.length - 1];
        if (!modal.contains(document.activeElement)) {
          ev.preventDefault();
          primero.focus();
        } else if (ev.shiftKey && document.activeElement === primero) {
          ev.preventDefault();
          ultimo.focus();
        } else if (!ev.shiftKey && document.activeElement === ultimo) {
          ev.preventDefault();
          primero.focus();
        }
      }
    });
  }

  /* ---------- Formulario de cotización ----------
     La landing no almacena datos personales: al enviar se abre WhatsApp con
     el mensaje estructurado. El número oficial se configura antes de publicar. */
  function initContactForm() {
    var form = document.getElementById('form-contacto');
    var estado = document.getElementById('contacto-estado');
    if (!form || !estado) return;
    var submit = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      var data = new FormData(form);
      var payload = {
        nombre: data.get('nombre'),
        organizacion: data.get('organizacion'),
        email: data.get('email'),
        telefono: data.get('telefono'),
        /* El país del teléfono viaja aparte. El servidor arma el número final
           (E.164) con este código y valida el largo contra ESE país; sin él,
           un móvil chileno escrito sin código se guardaba como número de otro
           país sin que nada fallara. */
        pais_telefono: data.get('pais_telefono') || 'cl',
        tipo: data.get('tipo'),
        fecha: data.get('fecha'),
        comuna: data.get('comuna'),
        mensaje: data.get('mensaje'),
        consentimiento: data.get('consentimiento') === 'on',
        website: data.get('website') || ''
      };

      estado.className = 'contacto__estado contacto__campo--completo';
      estado.textContent = 'Registrando tu solicitud…';
      if (submit) submit.disabled = true;
      try {
        var response = await fetch('api/contacto.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(payload)
        });
        var result = await response.json().catch(function () { return {}; });
        if (!response.ok || !result.ok) {
          var fieldErrors = result.fields ? Object.values(result.fields) : [];
          throw new Error(fieldErrors[0] || result.error || 'No pudimos registrar tu solicitud.');
        }
        estado.classList.add('contacto__estado--ok');
        estado.textContent = '¡Solicitud registrada! Tu referencia es ' + result.reference + '. Te contactaremos con la disponibilidad.';
        form.reset();
        window.dispatchEvent(new CustomEvent('cumpleclick:lead-created', { detail: { reference: result.reference } }));
      } catch (error) {
        estado.classList.add('contacto__estado--error');
        estado.textContent = error && error.message ? error.message : 'No pudimos registrar tu solicitud. Intenta nuevamente.';
      } finally {
        if (submit) submit.disabled = false;
      }
    });
  }

  /* ---------- Hooks de medición sin terceros ----------
     Deja eventos internos listos para una futura analítica con consentimiento,
     sin cargar trackers ni enviar datos personales. */
  function initAnalyticsHooks() {
    document.querySelectorAll('a[href*="wa.me"], #form-contacto button[type="submit"], #btn-play-frozen').forEach(function (el) {
      el.addEventListener('click', function () {
        window.dispatchEvent(new CustomEvent('cumpleclick:conversion-intent', {
          detail: { id: el.id || null, label: (el.textContent || '').trim().slice(0, 80) }
        }));
      });
    });
  }

  /* ---------- Entradas con profundidad ----------
     rotationX sobre un eje superior + perspectiva: la sección "cae" hacia el
     lector en vez de solo aparecer. Solo transform/opacity. Nunca se aplica a
     un bloque que contenga el CTA de WhatsApp — ese no se mueve ni se retrasa. */
  function initDepth() {
    if (reduceMotion) return;
    if (!window.gsap || !window.ScrollTrigger) return;
    window.gsap.utils.toArray('[data-depth]').forEach(function (el, i) {
      window.gsap.from(el, {
        y: 52,
        opacity: 0,
        rotationX: 14,
        transformPerspective: 1000,
        transformOrigin: 'center top',
        duration: 0.85,
        ease: 'power3.out',
        delay: (i % 3) * 0.08,
        scrollTrigger: { trigger: el, start: 'top 90%', once: true }
      });
    });
  }

  /* ---------- Salida del hero con profundidad ----------
     Al bajar, el globo se aleja y rota levemente en vez de solo desplazarse.
     `scrub` lo ata al scroll real: no corre solo, sigue el dedo del usuario. */
  function initHeroParallax() {
    if (reduceMotion) return;
    if (!window.gsap || !window.ScrollTrigger) return;
    var visual = document.getElementById('hero-visual');
    var hero = document.getElementById('hero');
    if (!visual || !hero) return;
    window.gsap.to(visual, {
      y: -40,
      scale: 0.9,
      rotationX: 10,
      opacity: 0.35,
      transformPerspective: 1200,
      ease: 'none',
      scrollTrigger: { trigger: hero, start: 'bottom 85%', end: 'bottom 25%', scrub: 0.6 }
    });
  }

  /* ---------- Tilt 3D de la vitrina ----------
     Solo con mouse real (pointer:fine) y pantalla grande: en táctil no hay
     hover y el efecto solo gastaría batería. */
  function initTilt() {
    if (reduceMotion) return;
    if (!window.matchMedia('(pointer: fine)').matches) return;
    if (window.innerWidth < 720) return;

    document.querySelectorAll('.mundo').forEach(function (card) {
      var raf = null;
      card.style.transformStyle = 'preserve-3d';

      card.addEventListener('pointermove', function (ev) {
        if (raf) return;
        raf = requestAnimationFrame(function () {
          raf = null;
          var r = card.getBoundingClientRect();
          var px = (ev.clientX - r.left) / r.width - 0.5;
          var py = (ev.clientY - r.top) / r.height - 0.5;
          card.style.transform =
            'perspective(900px) translateY(-6px) rotateY(' + (px * 7).toFixed(2) +
            'deg) rotateX(' + (-py * 7).toFixed(2) + 'deg)';
        });
      });

      card.addEventListener('pointerleave', function () {
        if (raf) { cancelAnimationFrame(raf); raf = null; }
        card.style.transform = '';
      });
    });
  }

  /* ---------- Vitrina: vaivén automático en móvil ----------
     En pantallas chicas la galería se desplaza suavemente de izquierda a
     derecha y vuelve, para que se descubran todas las temáticas. Si alguien
     toca, arrastra o desliza, el movimiento se pausa unos segundos pero NO
     se bloquea: conserva el control manual y luego retoma con suavidad. */
  function initCarruselAuto() {
    if (reduceMotion) return;
    var grid = document.getElementById('mundos-grid');
    if (!grid) return;
    /* Solo donde hay scroll horizontal real (móvil); en desktop es una grilla */
    if (!window.matchMedia('(max-width: 719px)').matches) return;

    var timer = null;
    var reanudarTimer = null;
    var enVista = false;
    var direccion = 1;

    function paso() {
      var card = grid.querySelector('.mundo');
      if (!card) return;
      var avance = card.getBoundingClientRect().width + 16; /* + gap */
      var finReal = grid.scrollWidth - grid.clientWidth - 8;
      if (finReal <= 0) return;
      if (grid.scrollLeft >= finReal) direccion = -1;
      if (grid.scrollLeft <= 8) direccion = 1;
      grid.scrollBy({ left: direccion * avance, behavior: 'smooth' });
    }

    function arrancar() {
      if (!timer && enVista && !document.hidden) timer = setInterval(paso, 3200);
    }
    function parar() { if (timer) { clearInterval(timer); timer = null; } }
    function pausarPorUsuario() {
      parar();
      if (reanudarTimer) clearTimeout(reanudarTimer);
      reanudarTimer = window.setTimeout(function () {
        reanudarTimer = null;
        arrancar();
      }, 4500);
    }

    /* El gesto manual manda por un momento; después el vaivén continúa. */
    ['pointerdown', 'touchstart', 'wheel'].forEach(function (ev) {
      grid.addEventListener(ev, pausarPorUsuario, { passive: true });
    });

    /* Solo corre mientras la vitrina está a la vista */
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          enVista = e.isIntersecting;
          enVista ? arrancar() : parar();
        });
      }, { threshold: 0.35 }).observe(grid);
    } else {
      enVista = true;
      arrancar();
    }
    /* Y se detiene si la pestaña queda en segundo plano */
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) parar(); else arrancar();
    });
  }

  /* Fusión 2026-07-27: el modal Frozen y el tilt de la vitrina viven en
     initModalVideo() e initTilt() (versiones Claude, arriba). Duplicados de
     OpenCode eliminados: doble binding del mismo botón y doble declaración. */

  /* ---------- Lenis: suaviza, no secuestra ---------- */
  function initLenis() {
    if (reduceMotion) return;
    if (typeof window.Lenis !== 'function') return;
    var lenis = new window.Lenis({
      lerp: 0.12,
      wheelMultiplier: 1,
      touchMultiplier: 1
    });
    function raf(time) {
      lenis.raf(time);
      requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);
    if (window.ScrollTrigger) {
      lenis.on('scroll', window.ScrollTrigger.update);
    }
    /* Anclas con Lenis: conservar navegación por teclado y barra */
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
      a.addEventListener('click', function (ev) {
        var id = a.getAttribute('href');
        if (id.length < 2) return;
        var target = document.querySelector(id);
        if (!target) return;
        ev.preventDefault();
        lenis.scrollTo(target, { offset: -72 });
        target.setAttribute('tabindex', '-1');
        target.focus({ preventScroll: true });
      });
    });
  }

  /* ---------- 3D del hero: diferido, con guardas y fallback ---------- */
  function hero3dEligible() {
    if (reduceMotion) return false;
    var visual = document.getElementById('hero-visual');
    if (!visual) return false;
    /* Equipo modesto → imagen estática */
    if (navigator.deviceMemory && navigator.deviceMemory < 4) return false;
    if (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 2) return false;
    /* Sin WebGL → imagen estática */
    try {
      var c = document.createElement('canvas');
      var gl = c.getContext('webgl2') || c.getContext('webgl');
      if (!gl) return false;
    } catch (e) {
      return false;
    }
    return true;
  }

  function initHero3d() {
    if (!hero3dEligible()) return;
    var boot = function () {
      import('./globo3d.js')
        .then(function (mod) { mod.mountHeroGlobo('hero-visual'); })
        .catch(function () { /* fallback estático ya visible */ });
    };
    if ('requestIdleCallback' in window) {
      window.requestIdleCallback(boot, { timeout: 2500 });
    } else {
      window.setTimeout(boot, 800);
    }
  }

  function init() {
    initLazyVideo();
    initModalVideo();
    initContactForm();
    initAnalyticsHooks();
    initReveals();
    initDepth();
    initHeroParallax();
    initTilt();
    initCarruselAuto();
    initLenis();
    initHero3d();
    /* Recalcular triggers cuando el layout ya está estable (fuentes + imágenes) */
    if (window.ScrollTrigger) {
      window.addEventListener('load', function () { window.ScrollTrigger.refresh(); });
      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () { window.ScrollTrigger.refresh(); });
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
