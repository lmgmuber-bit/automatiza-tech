/* Chat de la portada de CumpleClick. Burbuja abajo a la derecha; en el celular el panel ocupa la pantalla.
   Habla con api/chat.php (mismo dominio), que a su vez consulta al asistente en n8n. Sin librerías.
   El texto del asistente se pinta con textContent: los enlaces se arman a mano y el de WhatsApp sale como botón. */
(function () {
  'use strict';

  var API = 'api/chat.php';
  var WHATSAPP = 'https://wa.me/56974940070';
  var CLAVE = 'cc-chat-v1';
  var SALUDO = '¡Hola! 👋 Soy el asistente de CumpleClick. Te cuento de los planes, las temáticas o cómo es la fiesta. ¿Qué te gustaría saber?';
  var SUGERENCIAS = ['¿Cuánto cuesta?', '¿Qué incluye cada plan?', '¿Qué temáticas tienen?', 'Quiero agendar una fecha'];
  /* Mensajes que aparecen junto al ícono, uno cada 20 s, y se van solos a los 8 s. Si la persona cierra tres,
     o abre el chat, dejan de salir en esa visita. */
  var AVISOS = ['¿Dudas? Te ayudamos a aclararlas 💬', '¿Cuánto cuesta tu cumple? Pregúntame 🎈',
    '¿Qué temáticas hay? Te cuento 🎉', '¿Un baby shower? También lo hacemos 🍼', '¿Cómo funciona la cabina? Te explico 📸'];

  function guardado() {
    try { return JSON.parse(sessionStorage.getItem(CLAVE) || 'null'); } catch (e) { return null; }
  }
  function guardar(estado) {
    try { sessionStorage.setItem(CLAVE, JSON.stringify(estado)); } catch (e) { /* sin almacenamiento: sigue igual */ }
  }
  function idNuevo() {
    var a = new Uint8Array(16);
    (window.crypto || window.msCrypto).getRandomValues(a);
    return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
  }

  var estado = guardado() || { sesion: idNuevo(), mensajes: [] };
  var enviando = false;

  function el(tag, clase, texto) {
    var n = document.createElement(tag);
    if (clase) n.className = clase;
    if (texto) n.textContent = texto;
    return n;
  }

  /* Texto con enlaces: https://... se vuelve <a>; el de WhatsApp, un botón aparte al final del globo. */
  function pintarTexto(globo, texto) {
    /* El enlace de WhatsApp se saca del texto (va como botón) junto con lo que lo presenta: "al siguiente
       enlace:", "aquí:"... Sin esto quedaba "por WhatsApp: ." colgando. */
    var hayWhatsapp = /https:\/\/wa\.me\//.test(texto);
    texto = texto
      .replace(/(?:\s+(?:al siguiente enlace|en el siguiente enlace|en este enlace|al enlace|aquí|al))?\s*:?\s*https:\/\/wa\.me\/[^\s)]*\s*[.,;:]?\s*/gi, '. ')
      .replace(/\.\s*\.\s/g, '. ')
      .replace(/\s+$/, '');
    var partes = texto.split(/(https?:\/\/[^\s)]+)/g);
    partes.forEach(function (p) {
      if (/^https?:\/\//.test(p)) {
        var limpio = p.replace(/[.,;:!?]+$/, '');
        var cola = p.slice(limpio.length);
        var a = el('a', null, limpio.replace(/^https?:\/\//, ''));
        a.href = limpio; a.target = '_blank'; a.rel = 'noopener';
        globo.appendChild(a);
        if (cola) globo.appendChild(document.createTextNode(cola));
      } else if (p) {
        globo.appendChild(document.createTextNode(p));
      }
    });
    if (hayWhatsapp) {
      var boton = el('a', 'ccchat__wa', 'Escribir por WhatsApp');
      boton.href = WHATSAPP + '?text=' + encodeURIComponent('Hola CumpleClick, vengo del chat de la página 🎈');
      boton.target = '_blank'; boton.rel = 'noopener';
      globo.appendChild(boton);
    }
  }

  function crear() {
    var raiz = el('div', 'ccchat');
    raiz.innerHTML =
      '<div class="ccchat__aviso" hidden>' +
        '<button type="button" class="ccchat__aviso-texto">¿Dudas? Te ayudamos a aclararlas 💬</button>' +
        '<button type="button" class="ccchat__aviso-cerrar" aria-label="Ocultar este aviso">×</button>' +
      '</div>' +
      '<button type="button" class="ccchat__abrir" aria-expanded="false" aria-controls="ccchat-panel" aria-label="Abrir el chat de CumpleClick: ¿dudas? te ayudamos">' +
        '<img class="ccchat__avatar" src="assets/cumpleclick-mark.svg" alt="" width="46" height="46">' +
        '<span class="ccchat__insignia" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M12 3C6.5 3 2 6.8 2 11.5c0 2.4 1.2 4.6 3.1 6.1L4.3 21l4-1.9c1.2.4 2.4.6 3.7.6 5.5 0 10-3.8 10-8.5S17.5 3 12 3Z"/></svg></span>' +
      '</button>' +
      '<section class="ccchat__panel" id="ccchat-panel" role="dialog" aria-label="Chat de CumpleClick" hidden>' +
        '<header class="ccchat__cabecera">' +
          '<img src="assets/cumpleclick-mark.svg" alt="" width="36" height="36">' +
          '<div><p class="ccchat__titulo">Asistente CumpleClick</p><p class="ccchat__sub">Planes, temáticas y cómo es la fiesta</p></div>' +
          '<button type="button" class="ccchat__cerrar" aria-label="Cerrar el chat">×</button>' +
        '</header>' +
        '<div class="ccchat__mensajes" aria-live="polite" data-lenis-prevent></div>' +
        '<div class="ccchat__sugerencias"></div>' +
        '<form class="ccchat__form">' +
          '<label class="sr-only" for="ccchat-texto">Escribe tu pregunta</label>' +
          '<input id="ccchat-texto" class="ccchat__texto" type="text" maxlength="500" autocomplete="off" placeholder="Escribe tu pregunta…">' +
          '<button type="submit" class="ccchat__enviar" aria-label="Enviar">' +
            '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3.3 10l12.6 2-12.6 2z"/></svg>' +
          '</button>' +
        '</form>' +
        '<p class="ccchat__pie">Para reservar tu fecha, <a href="' + WHATSAPP + '" target="_blank" rel="noopener">escríbenos por WhatsApp</a>.</p>' +
      '</section>';
    document.body.appendChild(raiz);
    return raiz;
  }

  function iniciar() {
    var raiz = crear();
    var abrir = raiz.querySelector('.ccchat__abrir');
    var panel = raiz.querySelector('.ccchat__panel');
    var cerrar = raiz.querySelector('.ccchat__cerrar');
    var lista = raiz.querySelector('.ccchat__mensajes');
    var sugerencias = raiz.querySelector('.ccchat__sugerencias');
    var form = raiz.querySelector('.ccchat__form');
    var texto = raiz.querySelector('.ccchat__texto');
    var aviso = raiz.querySelector('.ccchat__aviso');

    var avisoTexto = raiz.querySelector('.ccchat__aviso-texto');
    var turno = 0;
    var mostrados = 0;
    var relojAviso = null;

    function avisosApagados() { return estado.avisoVisto || (estado.avisosCerrados || 0) >= 3 || mostrados >= 8; }
    function esconderAviso() { aviso.hidden = true; clearTimeout(relojAviso); }
    function ocultarAviso() {           /* al abrir el chat: no vuelven en esta visita */
      esconderAviso();
      estado.avisoVisto = true;
      guardar(estado);
    }
    function mostrarAviso() {
      if (avisosApagados() || !panel.hidden) return;
      avisoTexto.textContent = AVISOS[turno % AVISOS.length];
      turno += 1;
      mostrados += 1;
      aviso.hidden = false;
      raiz.classList.remove('ccchat--saluda');
      void raiz.offsetWidth;            /* reinicia la animación del saludo */
      raiz.classList.add('ccchat--saluda');
      clearTimeout(relojAviso);
      relojAviso = setTimeout(esconderAviso, 8000);
    }
    setTimeout(mostrarAviso, 2500);
    setInterval(mostrarAviso, 20000);
    avisoTexto.addEventListener('click', function () { alternar(true); });
    raiz.querySelector('.ccchat__aviso-cerrar').addEventListener('click', function () {
      esconderAviso();
      estado.avisosCerrados = (estado.avisosCerrados || 0) + 1;
      guardar(estado);
    });

    function agregar(quien, contenido) {
      var globo = el('div', 'ccchat__globo ccchat__globo--' + quien);
      if (quien === 'bot') pintarTexto(globo, contenido); else globo.textContent = contenido;
      lista.appendChild(globo);
      lista.scrollTop = lista.scrollHeight;
      return globo;
    }

    function pintarTodo() {
      lista.textContent = '';
      agregar('bot', SALUDO);
      estado.mensajes.forEach(function (m) { agregar(m.quien, m.texto); });
      sugerencias.hidden = estado.mensajes.length > 0;
    }

    SUGERENCIAS.forEach(function (s) {
      var b = el('button', 'ccchat__chip', s);
      b.type = 'button';
      b.addEventListener('click', function () { enviar(s); });
      sugerencias.appendChild(b);
    });

    function enviar(pregunta) {
      pregunta = (pregunta || '').trim();
      if (!pregunta || enviando) return;
      enviando = true;
      sugerencias.hidden = true;
      estado.mensajes.push({ quien: 'yo', texto: pregunta });
      agregar('yo', pregunta);
      guardar(estado);
      texto.value = '';
      var escribiendo = agregar('bot', '');
      escribiendo.classList.add('ccchat__globo--escribiendo');
      escribiendo.setAttribute('aria-label', 'Escribiendo');
      escribiendo.innerHTML = '<span></span><span></span><span></span>';

      var controlador = window.AbortController ? new AbortController() : null;
      var reloj = setTimeout(function () { if (controlador) controlador.abort(); }, 45000);
      fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: pregunta, sessionId: estado.sesion }),
        signal: controlador ? controlador.signal : undefined
      })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (d) { return d && d.reply ? d.reply : ''; })
        .catch(function () { return ''; })
        .then(function (respuesta) {
          clearTimeout(reloj);
          respuesta = respuesta || 'Uy, ahora no alcanzo a responderte por aquí. Escríbenos por WhatsApp y te ayudamos: ' + WHATSAPP;
          lista.removeChild(escribiendo);
          estado.mensajes.push({ quien: 'bot', texto: respuesta });
          if (estado.mensajes.length > 40) estado.mensajes = estado.mensajes.slice(-40);
          guardar(estado);
          agregar('bot', respuesta);
          enviando = false;
          texto.focus();
        });
    }

    function alternar(mostrar) {
      panel.hidden = !mostrar;
      abrir.setAttribute('aria-expanded', mostrar ? 'true' : 'false');
      document.documentElement.classList.toggle('ccchat-abierto', mostrar);
      if (mostrar) {
        ocultarAviso();
        pintarTodo();
        setTimeout(function () { texto.focus(); }, 50);
      } else {
        abrir.focus();
      }
    }

    abrir.addEventListener('click', function () { alternar(panel.hidden); });
    cerrar.addEventListener('click', function () { alternar(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) alternar(false); });
    form.addEventListener('submit', function (e) { e.preventDefault(); enviar(texto.value); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
  else iniciar();
})();
