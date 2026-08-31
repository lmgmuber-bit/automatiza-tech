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
    /* Carga el archivo, pero NO decide si suena: eso lo manda el carrusel.
       El video vive en una de las cuatro diapositivas, y antes se reproducia
       apenas entraba en pantalla aunque estuviera oculto detras de otra etapa:
       gastaba bateria y CPU de un telefono en algo que nadie estaba viendo.
       Si algun dia el video queda fuera del carrusel, el `if` de abajo no
       encuentra `.etapa` y se reproduce como siempre. */
    var load = function () {
      if (!source || source.src) return;
      source.src = source.getAttribute('data-src');
      video.load();
      var diapositiva = video.closest ? video.closest('.etapa') : null;
      if (diapositiva && !diapositiva.classList.contains('is-activa')) { return; }
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

  /* ---------- Menú de secciones ----------

     Un "disclosure": un botón que muestra y esconde un panel. No lleva trampa
     de foco como un diálogo modal —no bloquea el resto de la página— pero sí
     las tres cosas sin las cuales no se puede usar con teclado: Escape cierra,
     el foco entra al panel al abrir y vuelve al botón al cerrar.

     Lo de mover el foco a mano no es un adorno: en el HTML el `<nav>` va ANTES
     del botón, así que al abrir, un Tab llevaría FUERA del panel en vez de
     adentro. Se podría haber reordenado el marcado, pero el orden actual es el
     que deja los enlaces en su sitio en escritorio. */
  function initMenu() {
    var topbar = document.getElementById('topbar');
    var boton = document.getElementById('menu-boton');
    var menu = document.getElementById('menu-principal');
    if (!topbar || !boton || !menu) return;

    var etiqueta = boton.querySelector('[data-etiqueta-menu]');
    var enlaces = Array.prototype.slice.call(menu.querySelectorAll('.nav__enlace'));

    function abierto() { return topbar.getAttribute('data-menu') === 'abierto'; }

    function abrir(moverFoco) {
      topbar.setAttribute('data-menu', 'abierto');
      boton.setAttribute('aria-expanded', 'true');
      if (etiqueta) { etiqueta.textContent = 'Cerrar menú'; }
      if (!moverFoco) { return; }
      /* En el cuadro siguiente, no ahora: recién ahí el panel dejó de estar
         `visibility: hidden` y el enlace puede recibir el foco. Llamando a
         `focus()` en la misma vuelta el foco se quedaba en el botón y quien
         navega con teclado abría un menú al que no podía entrar. */
      window.requestAnimationFrame(function () {
        if (abierto() && enlaces[0]) { enlaces[0].focus(); }
      });
    }

    function cerrar(devolverFoco) {
      if (!abierto()) return;
      topbar.removeAttribute('data-menu');
      boton.setAttribute('aria-expanded', 'false');
      if (etiqueta) { etiqueta.textContent = 'Abrir menú'; }
      if (devolverFoco) { boton.focus(); }
    }

    boton.addEventListener('click', function (ev) {
      if (abierto()) { cerrar(porTeclado(ev)); return; }
      /* El foco salta al primer enlace SÓLO si se abrió con el teclado.

         Moviéndolo siempre, quien toca la hamburguesa con el dedo veía el
         primer ítem con el anillo de foco puesto, que parece un error de la
         página. `detail` vale 0 cuando el clic lo generó Enter o Espacio sobre
         el botón, y el número de clics cuando vino de un puntero: es la forma
         de distinguirlos sin escuchar el teclado por separado. */
      abrir(porTeclado(ev));
    });

    function porTeclado(ev) { return !ev || ev.detail === 0; }

    /* Al elegir una sección, el panel se va: quien tocó "Precios" quiere ver
       precios, no el menú tapando la mitad de la pantalla. El desplazamiento en
       sí lo hace el manejador de anclas de Lenis, o el navegador si no está. */
    menu.addEventListener('click', function (ev) {
      if (ev.target.closest('a')) { cerrar(false); }
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && abierto()) { cerrar(true); }
    });

    /* Tocar fuera cierra. Se escucha en captura para que el panel se cierre
       aunque el clic caiga sobre algo que detiene la propagación. */
    document.addEventListener('click', function (ev) {
      if (abierto() && !topbar.contains(ev.target)) { cerrar(false); }
    }, true);

    /* Si el foco se va del header con el teclado, el panel sobra. */
    topbar.addEventListener('focusout', function (ev) {
      if (abierto() && !topbar.contains(ev.relatedTarget)) { cerrar(false); }
    });

    /* Al pasar a ancho de escritorio el panel deja de existir como panel: si
       quedó marcado como abierto, el atributo se arrastra y la hamburguesa
       reaparece "abierta" al volver a angostar. */
    var anchoGrande = window.matchMedia('(min-width: 1121px)');
    var alCambiar = function (e) { if (e.matches) { cerrar(false); } };
    if (anchoGrande.addEventListener) { anchoGrande.addEventListener('change', alCambiar); }
    else if (anchoGrande.addListener) { anchoGrande.addListener(alCambiar); }

    /* ---------- Sección activa ----------
       Un solo IntersectionObserver, con una franja estrecha en el medio de la
       pantalla: se marca la sección que está cruzando el centro, no la que
       asoma por abajo. Sin la franja, con dos secciones a la vista se marcarían
       las dos y el menú parpadearía al hacer scroll. */
    var porId = {};
    var objetivos = [];
    enlaces.forEach(function (a) {
      var id = a.getAttribute('href');
      if (!id || id.length < 2) return;
      var sec = document.querySelector(id);
      if (!sec) return;
      porId[id] = a;
      objetivos.push(sec);
    });
    if (!objetivos.length || !('IntersectionObserver' in window)) return;

    var observador = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (e) {
        var a = porId['#' + e.target.id];
        if (!a) return;
        if (e.isIntersecting) { a.setAttribute('aria-current', 'true'); }
        else { a.removeAttribute('aria-current'); }
      });
    }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });

    objetivos.forEach(function (sec) { observador.observe(sec); });
  }

  /* ---------- Revelaciones al entrar en pantalla ----------

     Con IntersectionObserver, NO con ScrollTrigger, y el motivo es medido.

     Cada `gsap.from({scrollTrigger})` crea un disparador por elemento: 59 en
     esta página. Cada disparador mide la posición de su elemento al nacer y la
     vuelve a medir en cada `refresh()`, y eso es layout sincrónico sobre un DOM
     grande. Con la CPU a 1/4 eso costaba tareas largas de 420 ms y 218 ms.

     Se probó `ScrollTrigger.batch()` creyendo que agruparía los disparadores, y
     no: `ScrollTrigger.getAll()` seguía devolviendo 60. `batch()` coordina los
     callbacks, no reduce los triggers, así que el bloqueo no bajó. Queda escrito
     para no volver a intentarlo.

     El observador, en cambio, no mide layout en el hilo principal: el navegador
     calcula las intersecciones por su cuenta y avisa. Son 3 observadores en vez
     de 59 disparadores. GSAP se sigue usando para animar —misma duración, mismo
     ease, mismo desplazamiento— sólo cambia quién avisa que llegó el momento.

     El margen inferior es POSITIVO: agranda la zona de deteccion hacia abajo,
     asi el elemento empieza a aparecer justo ANTES de entrar en pantalla y para
     cuando se lo ve ya esta a la vista.

     Estuvo en negativo (-8%, -10%, -12%), traduciendo el `start: 'top 88%'` de
     ScrollTrigger, y fue un error: un margen negativo EXIGE que el elemento
     entre un 8% dentro de la ventana antes de contar. Sumado a Lenis, que llega
     frenando y tarda en asentarse, el observador de "Elige tu mundo" llego a
     dispararse 12 segundos despues de que la seccion ya se veia. Mientras
     tanto lo unico que mostraba algo era la red de seguridad, y solo lo que ya
     estaba en pantalla. Se noto como "las secciones se demoran en aparecer".

     ScrollTrigger no tenia el problema porque se actualiza en cada evento de
     scroll de Lenis, no cuando el navegador decide entregar la interseccion. */

  /* ---------- Carrusel de etapas ----------

     Cuatro diapositivas que cuentan el recorrido: invitacion, cabina, juegos y
     album. Antes aca habia solo el video del kiosco y la seccion se leia como
     "esto es una cabina", aunque el texto de al lado ya contara las cuatro.

     Tres cuidados que no se ven pero se notan:

     - Se detiene cuando el carrusel no esta en pantalla. Un temporizador que
       gira solo el resto de la sesion es trabajo que nadie mira.
     - Se detiene con el puntero encima o con el foco dentro. Nada peor que
       estar leyendo un pie y que la imagen cambie sola.
     - Con `prefers-reduced-motion` NO rota: quedan los puntos para elegir. Se
       respeta el pedido de no animar sin dejar al visitante sin las otras tres
       imagenes, que es lo que pasaria escondiendolas. */
  function initEtapas() {
    var caja = document.getElementById('etapas');
    if (!caja) { return; }
    var slides = Array.prototype.slice.call(caja.querySelectorAll('.etapa'));
    var puntos = Array.prototype.slice.call(caja.querySelectorAll('.etapas__punto'));
    if (slides.length < 2) { return; }

    var actual = 0;
    var timer = null;
    var enPantalla = false;
    var detenido = false;
    var video = document.getElementById('kiosco-video');

    function mostrar(i) {
      actual = (i + slides.length) % slides.length;
      for (var k = 0; k < slides.length; k++) {
        var activa = k === actual;
        slides[k].classList.toggle('is-activa', activa);
        if (puntos[k]) {
          puntos[k].classList.toggle('is-activo', activa);
          puntos[k].setAttribute('aria-selected', activa ? 'true' : 'false');
        }
      }
      /* El video solo corre en su propia diapositiva: reproducir algo que nadie
         ve gasta bateria y CPU en un telefono. */
      if (video) {
        if (slides[actual].contains(video)) {
          var play = video.play();
          if (play && play.catch) { play.catch(function () {}); }
        } else if (!video.paused) {
          video.pause();
        }
      }
    }

    function arrancar() {
      if (timer || detenido || !enPantalla || reduceMotion) { return; }
      timer = window.setInterval(function () { mostrar(actual + 1); }, 5000);
    }
    function parar() {
      if (timer) { window.clearInterval(timer); timer = null; }
    }

    puntos.forEach(function (b, i) {
      b.addEventListener('click', function () {
        detenido = true;      /* si eligio a mano, deja de girar solo */
        parar();
        mostrar(i);
      });
    });

    caja.addEventListener('mouseenter', parar);
    caja.addEventListener('mouseleave', arrancar);
    caja.addEventListener('focusin', parar);
    caja.addEventListener('focusout', function (ev) {
      if (!caja.contains(ev.relatedTarget)) { arrancar(); }
    });

    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entradas) {
        enPantalla = entradas[0].isIntersecting;
        if (enPantalla) { arrancar(); } else { parar(); }
      }, { rootMargin: '120px' }).observe(caja);
    } else {
      enPantalla = true;
      arrancar();
    }

    mostrar(0);
  }

  /* ---------- Cola de revelaciones, movida por el scroll ----------

     Se probaron dos mecanismos antes y los dos fallaron, cada uno a su manera:

     1. Un ScrollTrigger por elemento. Funcionaba, pero eran 60 disparadores
        midiendo layout y costaban ~310 ms de hilo bloqueado en el arranque.
     2. Un IntersectionObserver. Barato, pero el navegador entrega la
        interseccion cuando quiere: medido en produccion, el aviso de "Elige tu
        mundo" llego 11 SEGUNDOS despues de que la seccion ya se veia en
        pantalla. Para el visitante eso es "la seccion no aparece". Cambiar el
        margen no lo arreglo, porque el problema no era el umbral sino cuando
        llegaba el aviso.

     Esto es lo mismo que hacia ScrollTrigger pero sin su costo: una sola cola,
     revisada como mucho una vez por cuadro y solo cuando hay scroll. Cada
     elemento sale de la cola al revelarse, y cuando la cola queda vacia los
     listeners se desenganchan solos: no queda nada corriendo el resto de la
     sesion. */
  var colaRevelado = [];
  var revisionPedida = false;

  function pedirRevision() {
    if (revisionPedida) { return; }
    revisionPedida = true;
    window.requestAnimationFrame(revisarCola);
  }

  function revisarCola() {
    revisionPedida = false;
    if (!colaRevelado.length) { return; }
    var alto = window.innerHeight || 0;
    var porGrupo = [];

    for (var i = colaRevelado.length - 1; i >= 0; i--) {
      var item = colaRevelado[i];
      var r = item.disparador.getBoundingClientRect();
      /* Se revela un poco ANTES de entrar, para que al llegar ya este a la
         vista en vez de aparecer sobre el ojo del visitante. */
      if (r.top > alto + item.margen || r.bottom < -item.margen) { continue; }
      colaRevelado.splice(i, 1);
      var slot = null;
      for (var g = 0; g < porGrupo.length; g++) {
        if (porGrupo[g].grupo === item.grupo) { slot = porGrupo[g]; break; }
      }
      if (!slot) { slot = { grupo: item.grupo, nodos: [] }; porGrupo.push(slot); }
      slot.nodos = slot.nodos.concat(item.nodos);
    }

    for (var k = 0; k < porGrupo.length; k++) {
      porGrupo[k].grupo.animar(porGrupo[k].nodos);
    }

    if (!colaRevelado.length) {
      window.removeEventListener('scroll', pedirRevision);
      window.removeEventListener('resize', pedirRevision);
    }
  }

  function revelarAlEntrar(selector, estadoInicial, entrada, margen, totalEscalonado, porContenedor) {
    var nodos = Array.prototype.slice.call(document.querySelectorAll(selector));
    if (!nodos.length) { return; }
    window.gsap.set(nodos, estadoInicial);

    var duracionTotal = ((entrada.duration || 0.8) + totalEscalonado) * 1000 + 400;

    var grupo = {
      animar: function (lote) {
        if (!lote.length) { return; }
        var opciones = {};
        for (var k in entrada) { opciones[k] = entrada[k]; }
        opciones.overwrite = true;
        /* `amount` reparte un total fijo: el escalonado no se estira aunque el
           lote sea grande. El codigo anterior topaba el retraso con `(i % 3)`. */
        opciones.stagger = { amount: totalEscalonado };
        window.gsap.to(lote, opciones);

        /* Garantia por lote: cuando la animacion YA deberia haber terminado, lo
           que siga invisible se muestra sin mas.

           Se agrega porque en "Elige tu mundo" quedaban dos tarjetas en opacidad
           0 de forma intermitente y no logre explicar por que —la animacion se
           lanzaba sobre las seis—. Sin entender la causa, la conducta correcta
           es no dejar que el visitante pague el precio: una tarjeta invisible es
           un error que se ve, y comprobar un lote una sola vez, tras su propia
           duracion, no cuesta nada. Si algun dia aparece la causa real, esto
           deja de dispararse solo. */
        var propios = lote.slice();
        window.setTimeout(function () {
          for (var i = 0; i < propios.length; i++) {
            if (window.getComputedStyle(propios[i]).opacity !== '0') { continue; }
            window.gsap.set(propios[i], { opacity: 1, y: 0, rotationX: 0, clearProps: 'transform' });
          }
        }, duracionTotal);
      }
    };

    /* `porContenedor` existe por el carrusel de tematicas.

       Las tarjetas `.mundo` viven en una lista que en pantalla angosta se
       desplaza en HORIZONTAL, asi que varias quedan fuera de la ventana por el
       costado aunque su seccion ya se vea entera. Midiendo cada tarjeta, esas
       no se revelaban hasta que alguien deslizara el carrusel —y el carrusel se
       mueve solo, asi que aparecian de golpe a mitad de camino—. Se mide el
       CONTENEDOR y se revelan todos sus hijos juntos: vuelve a ser "la seccion
       entro, se revelan sus tarjetas". Se agrupa por padre y no por un selector
       fijo para que siga andando si hay mas de una lista. */
    if (porContenedor) {
      var hijosDe = new Map();
      for (var n = 0; n < nodos.length; n++) {
        var padre = nodos[n].parentElement;
        if (!padre) { continue; }
        if (!hijosDe.has(padre)) { hijosDe.set(padre, []); }
        hijosDe.get(padre).push(nodos[n]);
      }
      hijosDe.forEach(function (hijos, contenedor) {
        colaRevelado.push({ disparador: contenedor, nodos: hijos, margen: margen, grupo: grupo });
      });
    } else {
      for (var m = 0; m < nodos.length; m++) {
        colaRevelado.push({ disparador: nodos[m], nodos: [nodos[m]], margen: margen, grupo: grupo });
      }
    }

    window.addEventListener('scroll', pedirRevision, { passive: true });
    window.addEventListener('resize', pedirRevision, { passive: true });
    pedirRevision();   // lo que ya se ve al cargar se revela de una
  }


  function initReveals() {
    if (reduceMotion) return;
    if (!window.gsap || !window.ScrollTrigger) return;
    window.gsap.registerPlugin(window.ScrollTrigger);

    /* .mundo va aparte (entrada con perspectiva); excluirlo del reveal genérico
       o el segundo gsap.from captura opacity:0 como estado final */
    revelarAlEntrar('[data-reveal]:not(.mundo)',
      { opacity: 0, y: 36 },
      { opacity: 1, y: 0, duration: 0.7, ease: 'power3.out' },
      120, 0.18);

    revelarAlEntrar('.mundo',
      { opacity: 0, y: 44, rotationX: 8, transformPerspective: 800 },
      { opacity: 1, y: 0, rotationX: 0, duration: 0.8, ease: 'power3.out' },
      160, 0.24, true);   /* por contenedor: el carrusel corre en horizontal */

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
    revelarAlEntrar('[data-depth]',
      { opacity: 0, y: 52, rotationX: 14, transformPerspective: 1000, transformOrigin: 'center top' },
      { opacity: 1, y: 0, rotationX: 0, duration: 0.85, ease: 'power3.out' },
      120, 0.24);
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
    /* Primero `load`, DESPUÉS el hueco libre.

       `three.module.min.js` + `three.core.min.js` son ~730 KB que hay que
       parsear y compilar; medido con la CPU a 1/4 eso es una tarea de ~330 ms.
       Con sólo `requestIdleCallback({timeout: 2500})` el timeout vencía a los
       2,5 s —o sea en plena ventana del LCP— y esos 330 ms competían con el
       primer pintado. Encadenarlo a `load` lo saca del camino crítico sin tocar
       el efecto: mientras tanto se ve `globo-render.webp`, que es el mismo globo,
       y el 3D lo reemplaza cuando está listo. */
    var agendar = function () {
      if ('requestIdleCallback' in window) {
        window.requestIdleCallback(boot, { timeout: 2000 });
      } else {
        window.setTimeout(boot, 300);
      }
    };
    if (document.readyState === 'complete') { agendar(); }
    else { window.addEventListener('load', agendar); }
  }

  function init() {
    initLazyVideo();
    initModalVideo();
    initContactForm();
    initAnalyticsHooks();
    initReveals();
    initMenu();
    initEtapas();
    initDepth();
    initHeroParallax();
    initTilt();
    initCarruselAuto();
    initLenis();
    initHero3d();
    /* UN solo `refresh()`, cuando YA pasaron las dos cosas que mueven el layout.

       El intento anterior fue un debounce de 200 ms, y no servía: `load` y
       `fonts.ready` no caen juntos —medidos acá, a 2,0 s y 5,7 s— así que el
       debounce no fusionaba nada, sólo retrasaba cada uno por separado. Seguían
       siendo dos tareas largas (668 ms y 294 ms), que es exactamente lo que se
       quería evitar.

       Ahora se espera a que las DOS señales hayan ocurrido y recién ahí se mide
       una vez. `refresh()` recalcula la posición de todos los disparadores de la
       página, o sea layout sincrónico sobre un DOM grande: es la operación más
       cara del arranque y no hay motivo para pagarla dos veces. */
    if (window.ScrollTrigger) {
      var faltan = 1;
      var listo = function () {
        faltan -= 1;
        if (faltan > 0) { return; }
        // En rAF para no medir en medio del trabajo de carga del navegador.
        requestAnimationFrame(function () { window.ScrollTrigger.refresh(); });
      };
      if (document.fonts && document.fonts.ready) {
        faltan = 2;
        document.fonts.ready.then(listo);
      }
      if (document.readyState === 'complete') { listo(); }
      else { window.addEventListener('load', listo); }
    }
    initRedDeSeguridad();
  }

  /* Red de seguridad de las animaciones de entrada.
   *
   * `gsap.from()` pone el elemento en opacidad 0 apenas corre el script y lo
   * muestra recién cuando su disparador se activa. Si ese disparador no llega a
   * correr, el elemento se queda invisible PARA SIEMPRE. Y pasa de verdad:
   * entrando directo a `cumpleclick.com/#demos` la sección quedaba en blanco
   * —la tarjeta estaba en pantalla, con opacidad 0, y a los 8 segundos seguía
   * igual—. Las imágenes con carga diferida cambian el alto de la página
   * después de que se calcularon los disparadores, y los de más abajo quedan
   * apuntando a una posición que ya no existe.
   *
   * Esto NO arregla la causa: deja el contenido visible igual. Es a propósito.
   * Una animación que no corre es un detalle que casi nadie nota; una sección
   * invisible es la página rota, y el visitante no tiene forma de saber que
   * había algo ahí.
   */
  function initRedDeSeguridad() {
    function rescatar() {
      var alto = window.innerHeight || 0;
      // `[data-depth]` va incluido: esos bloques tambien nacen en opacidad 0
      // y, si su observador no dispara, quedan invisibles para siempre. Antes
      // la red solo miraba `[data-reveal]` y los dejaba afuera.
      var nodos = document.querySelectorAll('[data-reveal], [data-depth]');
      for (var i = 0; i < nodos.length; i++) {
        var el = nodos[i];
        var r = el.getBoundingClientRect();
        // Solo lo que ya debería verse: lo de más abajo tiene que poder animarse.
        if (r.bottom < 0 || r.top > alto) { continue; }
        if (window.getComputedStyle(el).opacity !== '0') { continue; }
        if (window.gsap) {
          window.gsap.set(el, { opacity: 1, y: 0, rotationX: 0, clearProps: 'transform' });
        } else {
          el.style.opacity = '1';
          el.style.transform = 'none';
        }
      }
    }

    // Un rescate al asentarse la página, y otro cada vez que se deja de hacer
    // scroll. Con `once` en los disparadores esto no pelea con la animación:
    // lo que ya se animó tiene opacidad 1 y no se toca.
    var pendiente = null;
    function programar() {
      if (pendiente) { clearTimeout(pendiente); }
      pendiente = setTimeout(rescatar, 350);
    }
    window.addEventListener('load', function () { setTimeout(rescatar, 900); });
    window.addEventListener('scroll', programar, { passive: true });
    window.addEventListener('resize', programar, { passive: true });
    window.addEventListener('hashchange', function () { setTimeout(rescatar, 700); });
    setTimeout(rescatar, 1500);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
