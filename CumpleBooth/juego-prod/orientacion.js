/*
 * Pide girar el dispositivo y entra a pantalla completa. Compartido por los dos juegos 3D.
 *
 * Es un script clásico y no un módulo a propósito: los dos juegos tienen sistemas de
 * módulos distintos (uno usa .mjs con imports, el otro .js) y así cada uno lo incluye con
 * una sola línea sin tocar su arquitectura.
 *
 * SOBRE LA PANTALLA COMPLETA, que es donde está la única limitación real: el navegador
 * **no deja** entrar a pantalla completa por su cuenta. `requestFullscreen()` solo funciona
 * dentro de un gesto de la persona —un toque, un clic—, justamente para que una página no
 * pueda apoderarse de la pantalla sola. Girar el aparato NO cuenta como gesto.
 *
 * Así que en vez de pedir pantalla completa al girar (que fallaría siempre), se engancha al
 * PRIMER TOQUE que dé el niño después de girar. Como para jugar hay que tocar la pantalla
 * igual, en la práctica es automático: gira, toca para empezar, y entra a pantalla completa
 * en ese mismo gesto, sin botones extra ni pasos que explicar.
 *
 * El aspecto del aviso vive en orientacion.css, que hay que cargar junto a este script:
 * el Festival declara `style-src 'self'` y bloquearía un <style> inyectado desde acá.
 *
 * Todo lo demás degrada en silencio: si el aparato no soporta pantalla completa, o no deja
 * fijar la orientación (iPhone y iPad no lo permiten), el juego funciona igual. Nada de esto
 * puede impedir jugar.
 */
(function () {
  'use strict';

  /*
   * Qué aparato es. Decide tanto si se avisa como con qué palabra.
   *
   * No basta con preguntar si hay pantalla táctil: muchos notebooks la tienen y ahí el
   * aviso no corresponde —quien usa un notebook agranda la ventana, no gira el monitor—.
   * Lo que se pregunta es cuál es el puntero PRINCIPAL: `pointer: coarse` significa que
   * se maneja con el dedo. Un notebook táctil con trackpad responde `fine` y queda fuera,
   * que es lo correcto.
   *
   * Entre celular y tablet decide el lado corto de la PANTALLA, no de la ventana: la
   * ventana cambia al girar y dejaría al mismo aparato clasificado de dos maneras.
   * Referencias: un iPhone grande da 430, un iPad mini 744, una tablet Android 800.
   */
  function aparato() {
    var dedo = false;
    try {
      dedo = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
    } catch (e) {
      dedo = (navigator.maxTouchPoints || 0) > 0;
    }
    if (!dedo) { return 'computador'; }
    var corto = Math.min(screen.width || 0, screen.height || 0);
    return corto && corto < 500 ? 'celular' : 'tablet';
  }

  var TEXTOS = {
    celular: {
      titulo: 'Gira el celular',
      bajada: 'El juego se ve mucho mejor apaisado.',
      pista: 'Cuando lo gires, toca la pantalla para empezar.'
    },
    tablet: {
      titulo: 'Gira la tablet',
      bajada: 'El juego se ve mucho mejor apaisada.',
      pista: 'Cuando la gires, toca la pantalla para empezar.'
    }
  };

  /* En un computador el juego se adapta solo a la ventana: no hay nada que girar. */
  function avisaGirar() {
    return aparato() !== 'computador';
  }

  function enVertical() {
    return window.innerHeight > window.innerWidth;
  }

  function enPantallaCompleta() {
    return !!(document.fullscreenElement || document.webkitFullscreenElement);
  }

  function pedirPantallaCompleta() {
    if (enPantallaCompleta()) { return; }
    var el = document.documentElement;
    var pedir = el.requestFullscreen || el.webkitRequestFullscreen;
    if (!pedir) { return; }
    try {
      var r = pedir.call(el, { navigationUI: 'hide' });
      // Con la pantalla ya tomada se puede intentar fijar la orientación. Android lo
      // permite; iOS no lo implementa y rechaza la promesa: se ignora y no pasa nada.
      if (r && r.then) {
        r.then(fijarHorizontal).catch(function () {});
      } else {
        fijarHorizontal();
      }
    } catch (e) { /* el navegador puede negarse; el juego sigue igual */ }
  }

  function fijarHorizontal() {
    try {
      if (screen.orientation && screen.orientation.lock) {
        var p = screen.orientation.lock('landscape');
        if (p && p.catch) { p.catch(function () {}); }
      }
    } catch (e) { /* no soportado: se deja como esté */ }
  }

  // ── El aviso ────────────────────────────────────────────────────────────
  var capa = null;

  function crearCapa() {
    if (capa) { return capa; }
    capa = document.createElement('div');
    capa.id = 'cc-gira';
    capa.setAttribute('role', 'status');
    var t = TEXTOS[aparato()] || TEXTOS.tablet;
    capa.innerHTML =
      '<div class="cc-gira__caja">' +
        '<div class="cc-gira__tablet" aria-hidden="true"><span></span></div>' +
        '<p class="cc-gira__tit">' + t.titulo + '</p>' +
        '<p class="cc-gira__sub">' + t.bajada + '</p>' +
        '<p class="cc-gira__pista">' + t.pista + '</p>' +
      '</div>';

    document.body.appendChild(capa);
    return capa;
  }

  function revisar() {
    var mostrar = avisaGirar() && enVertical();
    crearCapa().classList.toggle('cc-gira--on', mostrar);
    // Con el aviso puesto no debe quedar nada desplazándose por detrás.
    document.documentElement.style.overflow = mostrar ? 'hidden' : '';
  }

  /* El primer toque estando ya apaisado es el que entra a pantalla completa. Va en
     `capture` para no interferir con lo que el juego haga con ese mismo toque: el niño
     tocó para jugar, y jugar es lo que tiene que pasar. */
  function alPrimerToque() {
    if (!avisaGirar() || enVertical()) { return; }
    pedirPantallaCompleta();
    document.removeEventListener('pointerdown', alPrimerToque, true);
    document.removeEventListener('touchstart', alPrimerToque, true);
  }

  function iniciar() {
    revisar();
    window.addEventListener('resize', revisar);
    window.addEventListener('orientationchange', function () { setTimeout(revisar, 120); });
    document.addEventListener('pointerdown', alPrimerToque, true);
    document.addEventListener('touchstart', alPrimerToque, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
})();
