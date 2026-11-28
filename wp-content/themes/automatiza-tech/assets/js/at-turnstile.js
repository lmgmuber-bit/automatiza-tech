/**
 * Casilla anti-robot de Cloudflare Turnstile para los formularios de agenda (16-nov-2026).
 *
 * n8n («Agente Tech Web», acción saveLead) revisa el token con Cloudflare antes de guardar la cita, así que cada
 * formulario manda `turnstileToken` en el pedido. Uso:
 *   atTurnstile.enForm(form, botonEnviar)  // pone la casilla antes del botón (una sola vez) y la dibuja al verse
 *   atTurnstile.token(form)                // token vigente o '' si la persona todavía no la pasa
 *   atTurnstile.reiniciar(form)            // después de un envío (el token sirve una sola vez) o de un error
 * La clave del sitio (pública) llega en window.AT_TURNSTILE.sitekey (inc/at-turnstile.php).
 */
(function () {
  'use strict';
  var API = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=atTurnstileListo';
  var pendientes = [];
  var cargando = false;

  function sitekey() {
    return (window.AT_TURNSTILE && window.AT_TURNSTILE.sitekey) || '';
  }

  function cargarApi() {
    if (window.turnstile || cargando) return;
    cargando = true;
    var s = document.createElement('script');
    s.src = API;
    s.async = true;
    s.defer = true;
    document.head.appendChild(s);
  }

  window.atTurnstileListo = function () {
    var cola = pendientes;
    pendientes = [];
    cola.forEach(dibujar);
  };

  function dibujar(caja) {
    if (caja.__atTsId !== undefined || !sitekey()) return;
    if (!window.turnstile) {
      if (pendientes.indexOf(caja) === -1) pendientes.push(caja);
      cargarApi();
      return;
    }
    caja.__atTsId = window.turnstile.render(caja, {
      sitekey: sitekey(),
      language: 'es',
      theme: caja.getAttribute('data-tema') || 'auto',
      size: 'flexible',
      callback: function () { caja.removeAttribute('data-sin-pasar'); },
      'expired-callback': function () { window.turnstile.reset(caja.__atTsId); },
      'error-callback': function () { return true; }
    });
  }

  function caja(form) {
    return form ? form.querySelector('[data-at-turnstile]') : null;
  }

  function enForm(form, antesDe) {
    if (!form || !sitekey()) return null;
    var c = caja(form);
    if (!c) {
      c = document.createElement('div');
      c.setAttribute('data-at-turnstile', '');
      c.className = 'at-turnstile';
      c.style.margin = '10px 0';
      c.style.minHeight = '65px';
      if (antesDe && antesDe.parentNode) antesDe.parentNode.insertBefore(c, antesDe);
      else form.appendChild(c);
    }
    // Se dibuja cuando el formulario se ve (los formularios en ventanas emergentes no piden el desafío al cargar).
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entradas) {
        entradas.forEach(function (e) {
          if (e.isIntersecting) { io.disconnect(); dibujar(c); }
        });
      });
      io.observe(c);
    } else {
      dibujar(c);
    }
    // Si la persona empieza a escribir antes de llegar a la casilla, se dibuja igual.
    form.addEventListener('focusin', function () { dibujar(c); }, { once: true });
    return c;
  }

  function token(form) {
    var c = caja(form);
    if (!c) return '';
    if (window.turnstile && c.__atTsId !== undefined) {
      return window.turnstile.getResponse(c.__atTsId) || '';
    }
    var oculto = c.querySelector('input[name="cf-turnstile-response"]');
    return oculto ? oculto.value : '';
  }

  function reiniciar(form) {
    var c = caja(form);
    if (c && window.turnstile && c.__atTsId !== undefined) window.turnstile.reset(c.__atTsId);
  }

  /** ¿El sitio pide la casilla? (sin clave, los formularios siguen como antes). */
  function activo() {
    return !!sitekey();
  }

  window.atTurnstile = {
    enForm: enForm,
    token: token,
    reiniciar: reiniciar,
    activo: activo,
    // Textos comunes: casilla sin pasar (antes de enviar) y token rechazado por n8n (HTTP 403).
    FALTA: 'Antes de agendar, confirma la casilla «No soy un robot» que está sobre el botón.',
    RECHAZO: 'No pudimos confirmar que no eres un robot. Vuelve a marcar la casilla e intenta de nuevo.'
  };
})();
