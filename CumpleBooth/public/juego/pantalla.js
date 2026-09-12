/**
 * Dos controles para todos los juegos: volver al menú y pantalla completa.
 *
 * Existe porque los juegos son de repositorios distintos y algunos vienen empaquetados: no
 * se puede editar por dentro el mundo 3D para agregarle un botón. Esto se cuelga por fuera,
 * en cualquier página que lo incluya, sin tocar el juego.
 *
 * 🔴 DOS COSAS QUE NO SON OBVIAS
 *
 * 1. Girar el aparato NO puede activar la pantalla completa. `requestFullscreen()` solo
 *    corre dentro de un gesto de la persona, y al girar el teléfono nadie tocó nada; el
 *    navegador lo rechaza. Por eso entra con el primer toque —en vertical o en horizontal,
 *    que antes solo pasaba estando apaisado— y queda el botón para cuando se quiera. Ya
 *    estando en pantalla completa, girar no la corta: se conserva.
 *
 * 2. Los juegos se sirven con `style-src 'self'`, así que un `<style>` inyectado o un
 *    atributo `style=` los bloquea la CSP en silencio: el botón aparece sin forma, pegado
 *    arriba a la izquierda. Escribir por CSSOM (`el.style.prop = ...`) no lo bloquea, y por
 *    eso acá no hay ni una hoja de estilo.
 */
(function () {
  'use strict';

  var doc = document;
  var raiz = doc.documentElement;
  var pedirPantalla = raiz.requestFullscreen || raiz.webkitRequestFullscreen;

  // ── A dónde vuelve ──────────────────────────────────────────────────────
  // El menú manda `volver` con su propia dirección. Se valida contra el mismo origen: es un
  // parámetro de la URL y mandar a la gente a otro sitio desde acá sería un regalo.
  function destinoVolver() {
    var crudo = new URLSearchParams(location.search).get('volver');
    if (!crudo) { return ''; }
    try {
      var u = new URL(crudo, location.href);
      return u.origin === location.origin && u.pathname.indexOf('/juego') !== -1 ? u.href : '';
    } catch (e) {
      return '';
    }
  }

  function estilar(el, props) {
    for (var k in props) {
      if (Object.prototype.hasOwnProperty.call(props, k)) { el.style[k] = props[k]; }
    }
  }

  var BASE = {
    // box-sizing explicito: sin el, el borde y el relleno se suman a los 44 px y cada juego
    // devuelve un boton de alto distinto segun su propio reset de CSS.
    boxSizing: 'border-box', height: '44px', minWidth: '44px', borderRadius: '999px',
    border: '1px solid rgba(255,255,255,.45)', background: 'rgba(10,12,24,.5)',
    color: '#fff', font: '600 15px/1 system-ui, sans-serif', display: 'grid',
    placeItems: 'center', cursor: 'pointer', padding: '0 12px',
    webkitBackdropFilter: 'blur(4px)', backdropFilter: 'blur(4px)',
    opacity: '.75', transition: 'opacity .2s', webkitTapHighlightColor: 'transparent'
  };

  function iniciar() {
    var volverA = destinoVolver();
    if (!volverA && !pedirPantalla) { return; }

    var caja = doc.createElement('div');
    // Arriba a la izquierda: es la única esquina que ninguno de los juegos usa para tocar.
    // A la derecha está la pausa y abajo están los controles.
    estilar(caja, {
      position: 'fixed', top: '10px', left: '10px', zIndex: '2147483000',
      display: 'flex', gap: '8px', alignItems: 'center'
    });

    // ── Volver al menú ────────────────────────────────────────────────────
    var salir = null;
    if (volverA) {
      salir = doc.createElement('button');
      salir.type = 'button';
      salir.textContent = '←';
      salir.setAttribute('aria-label', 'Volver al menú de juegos');
      estilar(salir, BASE);
      // Dos toques, no uno. Un niño de tres años apoya el dedo donde sea, y un solo toque le
      // borraría la partida. El segundo toque tiene que llegar dentro de tres segundos; si
      // no, el botón vuelve a lo que era y no pasó nada.
      var armado = 0;
      salir.addEventListener('click', function (ev) {
        ev.stopPropagation();
        if (Date.now() - armado < 3000) { location.href = volverA; return; }
        armado = Date.now();
        salir.textContent = '¿Salir?';
        salir.style.background = 'rgba(200,30,50,.75)';
        setTimeout(function () {
          if (Date.now() - armado >= 2900) {
            salir.textContent = '←';
            salir.style.background = BASE.background;
          }
        }, 3000);
      });
      caja.appendChild(salir);
    }

    // ── Pantalla completa ─────────────────────────────────────────────────
    var lupa = null;
    if (pedirPantalla) {
      lupa = doc.createElement('button');
      lupa.type = 'button';
      estilar(lupa, BASE);
      lupa.addEventListener('click', function (ev) {
        ev.stopPropagation();
        if (activa()) {
          var fin = doc.exitFullscreen || doc.webkitExitFullscreen;
          if (fin) { tragar(fin.call(doc)); }
        } else {
          entrar();
        }
      });
      caja.appendChild(lupa);
    }

    doc.body.appendChild(caja);
    pintar();

    function activa() {
      return !!(doc.fullscreenElement || doc.webkitFullscreenElement);
    }

    function tragar(r) {
      if (r && r.catch) { r.catch(function () {}); }
    }

    function entrar() {
      if (activa() || !pedirPantalla) { return; }
      try {
        tragar(pedirPantalla.call(raiz, { navigationUI: 'hide' }));
      } catch (e) { /* el navegador puede negarse; el juego sigue igual */ }
    }

    function pintar() {
      if (!lupa) { return; }
      var on = activa();
      lupa.textContent = on ? '⤡' : '⛶';
      lupa.setAttribute('aria-label', on ? 'Salir de pantalla completa' : 'Pantalla completa');
      lupa.setAttribute('aria-pressed', String(on));
      // Ya en pantalla completa los controles se apagan casi del todo: cumplieron su trabajo
      // y no tienen por qué seguir compitiendo con el juego por la atención del niño.
      caja.style.opacity = on ? '.3' : '1';
    }

    caja.addEventListener('pointerenter', function () { caja.style.opacity = '1'; });
    doc.addEventListener('fullscreenchange', pintar);
    doc.addEventListener('webkitfullscreenchange', pintar);

    /* El primer toque en cualquier parte entra a pantalla completa. Va en `capture` para no
       estorbar lo que el juego haga con ese mismo toque: el niño tocó para jugar, y jugar es
       lo que tiene que pasar. Se desengancha después del primero. */
    function alPrimerToque(ev) {
      if (ev && caja.contains(ev.target)) { return; }
      entrar();
      doc.removeEventListener('pointerdown', alPrimerToque, true);
      doc.removeEventListener('touchstart', alPrimerToque, true);
    }
    doc.addEventListener('pointerdown', alPrimerToque, true);
    doc.addEventListener('touchstart', alPrimerToque, true);
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
})();
