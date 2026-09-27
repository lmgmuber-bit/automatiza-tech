/* Formulario de inscripción GT Baseball Academy (v2, AutomatizaTech).
 * Pasos con nombre, validación al salir de cada campo, borrador en el teléfono, planilla PDF armada aquí,
 * envío con avance real (XHR) y resultado con compartir o descargar. Con ?prueba=1 n8n marca PRUEBA y
 * avisa solo a AutomatizaTech. El número de WhatsApp lo pone el despliegue en data-whatsapp. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var app = $('app');
  var ENDPOINT = app.getAttribute('data-endpoint');
  // Solo en el equipo de desarrollo se puede apuntar a un receptor de prueba; en PROD un enlace armado
  // no puede desviar los datos de los apoderados a otro servidor.
  if (/^(localhost|127\.0\.0\.1)$/.test(location.hostname)) {
    var otro = /[?&]endpoint=([^&]+)/.exec(location.search);
    if (otro) ENDPOINT = decodeURIComponent(otro[1]);
  }
  var PRUEBA = /[?&]prueba=1\b/.test(location.search);
  var TOTAL = 4;
  var NOMBRES = ['Atleta', 'Contacto', 'Béisbol', 'Enviar'];
  var MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  var CLAVE_BORRADOR = 'gt-inscripcion-borrador-v2';
  var CLAVE_TEMA = 'gt-tema';
  var TEXTOS = ['nombre', 'documento', 'nacionalidad', 'direccion', 'telefono', 'rep_nombre', 'rep_telefono', 'correo',
    'liga', 'posicion', 'estatura', 'peso', 'millas', 'anio_firma'];

  var form = $('formulario');
  var vistas = { portada: $('vista-portada'), formulario: $('vista-formulario'), listo: $('vista-listo') };
  var estado = { paso: 1, foto: null, logo: null, id: null, pdf: null, enviado: false, enviando: false };

  // ---------- Almacenamiento del teléfono (puede no existir: incógnito, sin espacio) ----------
  function guardar(k, v) { try { localStorage.setItem(k, v); return true; } catch (e) { return false; } }
  function leer(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function borrar(k) { try { localStorage.removeItem(k); } catch (e) { /* nada */ } }

  // ---------- Tema ----------
  function temaClaro() { return document.documentElement.getAttribute('data-theme') === 'light'; }
  function pintarTema() {
    var claro = temaClaro();
    var b = $('btn-tema');
    b.setAttribute('aria-label', 'Modo claro');
    b.setAttribute('aria-pressed', claro ? 'true' : 'false');
    b.title = claro ? 'Cambiar a modo oscuro' : 'Cambiar a modo claro';
    b.querySelector('use').setAttribute('href', claro ? '#i-moon' : '#i-sun');
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', claro ? '#f2f4f7' : '#0e1216');
  }
  $('btn-tema').addEventListener('click', function () {
    var nuevo = temaClaro() ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', nuevo);
    guardar(CLAVE_TEMA, nuevo);
    pintarTema();
  });
  pintarTema();

  if (PRUEBA) $('etiqueta-prueba').hidden = false;

  // ---------- WhatsApp de la academia ----------
  var WHATSAPP = (app.getAttribute('data-whatsapp') || '').replace(/\D/g, '');
  if (WHATSAPP.length >= 8) {
    var enlaceWa = 'https://wa.me/' + WHATSAPP + '?text=' +
      encodeURIComponent('Hola, quiero información sobre las inscripciones de GT Baseball Academy.');
    document.querySelectorAll('[data-whatsapp-link]').forEach(function (a) { a.href = enlaceWa; a.hidden = false; });
  }

  // ---------- Logo de la portada: inclinación con el mouse, giro al tocar, pausa fuera de pantalla ----------
  (function logoWow() {
    var zona = $('logo-wow');
    var giro = zona && zona.querySelector('.logo3d__giro');
    if (!zona || !giro) return;
    var menosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)');
    var punteroFino = window.matchMedia('(hover: hover) and (pointer: fine)');
    var pendiente = 0, rx = 0, ry = 0, px = 30, py = 25;
    function pintar() {
      pendiente = 0;
      giro.style.setProperty('--rx', rx.toFixed(2) + 'deg');
      giro.style.setProperty('--ry', ry.toFixed(2) + 'deg');
      giro.style.setProperty('--px', px.toFixed(1) + '%');
      giro.style.setProperty('--py', py.toFixed(1) + '%');
    }
    zona.addEventListener('pointermove', function (e) {
      if (menosMovimiento.matches || !punteroFino.matches || e.pointerType !== 'mouse') return;
      var r = zona.getBoundingClientRect();
      var x = Math.min(Math.max((e.clientX - r.left) / r.width, 0), 1) - 0.5;
      var y = Math.min(Math.max((e.clientY - r.top) / r.height, 0), 1) - 0.5;
      rx = -y * 16; ry = x * 18; px = (x + 0.5) * 100; py = (y + 0.5) * 100;
      zona.classList.add('con-mouse');
      if (!pendiente) pendiente = requestAnimationFrame(pintar);
    });
    zona.addEventListener('pointerleave', function () {
      rx = 0; ry = 0;
      zona.classList.remove('con-mouse');
      if (!pendiente) pendiente = requestAnimationFrame(pintar);
    });
    zona.addEventListener('click', function () {
      if (menosMovimiento.matches) return;
      giro.classList.remove('girando');
      void giro.offsetWidth; // reinicia la animación si se toca seguido
      giro.classList.add('girando');
    });
    giro.addEventListener('animationend', function (e) { if (e.animationName === 'moneda') giro.classList.remove('girando'); });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entradas) {
        zona.classList.toggle('en-pausa', !entradas[0].isIntersecting);
      }).observe(zona);
    }
  })();

  // Logo para la planilla PDF.
  fetch('assets/logo-gt-pdf.jpg').then(function (r) { return r.blob(); }).then(leerComoDataUrl)
    .then(function (u) { estado.logo = u; }).catch(function () { estado.logo = null; });

  function leerComoDataUrl(blob) {
    return new Promise(function (ok, mal) {
      var fr = new FileReader();
      fr.onload = function () { ok(fr.result); };
      fr.onerror = mal;
      fr.readAsDataURL(blob);
    });
  }

  function anunciar(t) { $('anuncio').textContent = ''; setTimeout(function () { $('anuncio').textContent = t; }, 60); }

  function mostrar(nombre) {
    Object.keys(vistas).forEach(function (k) { vistas[k].hidden = k !== nombre; });
    window.scrollTo(0, 0);
  }

  function nuevoId() {
    var r = Math.floor(Math.random() * 1296).toString(36).toUpperCase();
    return 'GT-' + Date.now().toString(36).toUpperCase() + ('0' + r).slice(-2);
  }

  function pad(n) { return (n < 10 ? '0' : '') + n; }

  // ---------- Fecha de nacimiento en tres listas ----------
  (function llenarFecha() {
    var d = $('fn-dia'), m = $('fn-mes'), a = $('fn-anio');
    for (var i = 1; i <= 31; i++) d.add(new Option(String(i), String(i)));
    MESES.forEach(function (n, i) { m.add(new Option(n, String(i + 1))); });
    var y = new Date().getFullYear();
    for (var an = y - 3; an >= y - 30; an--) a.add(new Option(String(an), String(an)));
  })();

  function fechaIso() {
    var d = Number($('fn-dia').value), m = Number($('fn-mes').value), a = Number($('fn-anio').value);
    if (!d || !m || !a) return '';
    var f = new Date(a, m - 1, d);
    if (f.getFullYear() !== a || f.getMonth() !== m - 1 || f.getDate() !== d) return 'invalida';
    return a + '-' + pad(m) + '-' + pad(d);
  }

  function calcularEdad(iso) {
    var p = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
    if (!p) return '';
    var hoy = new Date(), e = hoy.getFullYear() - Number(p[1]);
    var mes = hoy.getMonth() + 1, dia = hoy.getDate();
    if (mes < Number(p[2]) || (mes === Number(p[2]) && dia < Number(p[3]))) e--;
    return e;
  }

  function pintarEdad() {
    var f = fechaIso(), e = calcularEdad(f);
    $('edad').textContent = e === '' || e < 0 ? '' : 'Tiene ' + e + (e === 1 ? ' año' : ' años');
  }

  // ---------- Valores y validación ----------
  function valor(nombre) {
    var el = form.elements[nombre];
    if (!el) return '';
    if (typeof RadioNodeList !== 'undefined' && el instanceof RadioNodeList) return el.value || '';
    return (el.value || '').trim();
  }

  // Reglas por campo. El servidor (n8n) repite las mismas: el navegador se puede saltar.
  var CORREO = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  var LETRAS = /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñÀ-ÿ' .-]+$/;
  var DOMINIOS_MAL = { 'gmial.com': 'gmail.com', 'gmai.com': 'gmail.com', 'gamil.com': 'gmail.com', 'gmail.co': 'gmail.com',
    'gmail.con': 'gmail.com', 'gmail.cm': 'gmail.com', 'hotmial.com': 'hotmail.com', 'hotmal.com': 'hotmail.com',
    'hotmail.co': 'hotmail.com', 'hotmail.con': 'hotmail.com', 'yaho.com': 'yahoo.com', 'yahoo.co': 'yahoo.com',
    'yahoo.con': 'yahoo.com', 'outlok.com': 'outlook.com', 'outlook.co': 'outlook.com', 'outlook.con': 'outlook.com' };
  function digitos(t) { return (String(t).match(/\d/g) || []).length; }
  function telefonoOk(t) { return /^[0-9+()\-\s.]+$/.test(t) && digitos(t) >= 10 && digitos(t) <= 15; }
  function numero(t) { var m = String(t).replace(',', '.').match(/\d+(\.\d+)?/); return m ? parseFloat(m[0]) : NaN; }
  function nombrePersona(v, vacio) {
    if (!v) return vacio;
    if (!LETRAS.test(v)) return 'Usa solo letras, sin números ni símbolos.';
    if (v.split(/\s+/).filter(Boolean).length < 2) return 'Escribe nombre y apellido.';
    return '';
  }

  var reglas = {
    foto: function () { return estado.foto ? '' : 'Agrega una foto del atleta.'; },
    nombre: function () { return nombrePersona(valor('nombre'), 'Escribe el nombre completo.'); },
    documento: function () {
      var v = valor('documento');
      if (!v) return '';
      var limpio = v.replace(/[\s.\-]/g, '');
      return /^[VEJPGvejpg]?\d{6,9}$/.test(limpio) || /^[A-Za-z0-9]{6,12}$/.test(limpio) ? '' : 'Revisa el documento. Ej: V-30123456 o el número del pasaporte.';
    },
    fecha_nac: function () {
      var f = fechaIso();
      if (!f) return 'Elige el día, el mes y el año.';
      if (f === 'invalida') return 'Esa fecha no existe. Revisa el día.';
      var e = calcularEdad(f);
      return e < 3 || e > 30 ? 'Revisa el año: la edad debe estar entre 3 y 30 años.' : '';
    },
    nacionalidad: function () {
      var v = valor('nacionalidad');
      if (!v) return 'Indica la nacionalidad.';
      return LETRAS.test(v) && v.length >= 3 ? '' : 'Escribe la nacionalidad con letras. Ej: Venezolana.';
    },
    direccion: function () {
      var v = valor('direccion');
      if (!v) return 'Escribe la dirección.';
      return v.length >= 8 && /[A-Za-zÁÉÍÓÚÑáéíóúñ]/.test(v) ? '' : 'Escribe la dirección completa: calle o sector y ciudad.';
    },
    telefono: function () {
      var t = valor('telefono');
      return !t || telefonoOk(t) ? '' : 'Revisa el número: debe tener entre 10 y 15 dígitos. Ej: 0412 123 4567.';
    },
    rep_nombre: function () { return nombrePersona(valor('rep_nombre'), 'Escribe tu nombre y apellido.'); },
    rep_telefono: function () {
      var t = valor('rep_telefono');
      if (!t) return 'Escribe un teléfono de contacto.';
      return telefonoOk(t) ? '' : 'Revisa el número: debe tener entre 10 y 15 dígitos. Ej: 0414 765 4321.';
    },
    correo: function () {
      var c = valor('correo').toLowerCase();
      if (!c) return 'Escribe tu correo: ahí te llega la planilla.';
      if (!CORREO.test(c)) return 'Revisa el correo, parece incompleto. Ej: nombre@gmail.com.';
      var dominio = c.split('@')[1];
      if (DOMINIOS_MAL[dominio]) return '¿Quisiste decir ' + c.split('@')[0] + '@' + DOMINIOS_MAL[dominio] + '? Revisa el final del correo.';
      return '';
    },
    liga: function () { var v = valor('liga'); return !v || v.length >= 2 ? '' : 'Escribe el nombre de la liga o déjalo en blanco.'; },
    posicion: function () { return valor('posicion') ? '' : 'Elige una posición (o «Aún no definida»).'; },
    batea: function () { return valor('batea') ? '' : 'Elige con qué mano batea.'; },
    lanza: function () { return valor('lanza') ? '' : 'Elige con qué mano lanza.'; },
    estatura: function () {
      var v = valor('estatura');
      if (!v || /['′"″]/.test(v)) return '';
      var n = numero(v), cm = /cm/i.test(v) || n > 3 ? n : n * 100;
      return !isNaN(n) && cm >= 80 && cm <= 230 ? '' : 'Revisa la estatura. Ej: 1,65 m o 165 cm.';
    },
    peso: function () {
      var v = valor('peso');
      if (!v) return '';
      var n = numero(v), kg = /lb|libra/i.test(v) ? n * 0.4536 : n;
      return !isNaN(n) && kg >= 12 && kg <= 160 ? '' : 'Revisa el peso. Ej: 55 kg o 120 lb.';
    },
    millas: function () {
      var v = valor('millas');
      if (!v) return '';
      var n = numero(v);
      return /^\d{2,3}([.,]\d)?$/.test(v) && n >= 20 && n <= 110 ? '' : 'Escribe solo las millas por hora, entre 20 y 110. Ej: 72.';
    },
    anio_firma: function () {
      var v = valor('anio_firma'), y = new Date().getFullYear();
      if (!v) return '';
      return /^\d{4}$/.test(v) && +v >= y - 1 && +v <= y + 20 ? '' : 'Escribe un año de 4 dígitos. Ej: ' + (y + 2) + '.';
    },
    acepta: function () { return $('acepta').checked ? '' : 'Para enviar, marca la autorización.'; }
  };

  var CAMPOS = {
    1: ['foto', 'nombre', 'fecha_nac', 'documento', 'nacionalidad'],
    2: ['direccion', 'telefono', 'rep_nombre', 'rep_telefono', 'correo'],
    3: ['liga', 'posicion', 'batea', 'lanza', 'estatura', 'peso', 'millas', 'anio_firma'],
    4: ['acepta']
  };

  // Filtro al escribir: en teléfonos, millas y año no entran letras.
  function filtrar(id, re) {
    $(id).addEventListener('input', function () {
      var limpio = this.value.replace(re, '');
      if (limpio !== this.value) this.value = limpio;
    });
  }
  filtrar('telefono', /[^0-9+()\-\s.]/g);
  filtrar('rep_telefono', /[^0-9+()\-\s.]/g);
  filtrar('millas', /[^0-9.,]/g);
  filtrar('anio_firma', /\D/g);

  function controlDe(nombre) {
    if (nombre === 'foto') return $('foto-camara');
    if (nombre === 'fecha_nac') return $('fn-dia');
    var el = form.elements[nombre];
    if (el && typeof RadioNodeList !== 'undefined' && el instanceof RadioNodeList) return el[0];
    return el;
  }

  function nombreDe(el) {
    if (!el) return '';
    if (el.id === 'foto-camara' || el.id === 'foto-galeria') return 'foto';
    if (el.getAttribute && el.getAttribute('data-fecha')) return 'fecha_nac';
    return el.name || '';
  }

  function marcar(nombre, msg) {
    var p = $('err-' + nombre);
    if (p) p.textContent = msg || '';
    var campo = form.querySelector('[data-campo="' + nombre + '"]');
    if (campo) campo.classList.toggle('campo--error', !!msg);
    var controles = nombre === 'fecha_nac' ? [$('fn-dia'), $('fn-mes'), $('fn-anio')] : [controlDe(nombre)];
    controles.forEach(function (c) { if (c && c.setAttribute) c.setAttribute('aria-invalid', msg ? 'true' : 'false'); });
  }

  function validarCampo(nombre) {
    var msg = reglas[nombre] ? reglas[nombre]() : '';
    marcar(nombre, msg);
    return !msg;
  }

  function validarPaso(n) {
    var malos = CAMPOS[n].filter(function (c) { return !validarCampo(c); });
    var aviso = $('aviso-error');
    if (!malos.length) { aviso.hidden = true; return true; }
    aviso.textContent = malos.length === 1 ? 'Falta revisar un dato antes de seguir.' : 'Faltan revisar ' + malos.length + ' datos antes de seguir.';
    aviso.hidden = false;
    var primero = controlDe(malos[0]);
    var zona = form.querySelector('[data-campo="' + malos[0] + '"]');
    if (zona && zona.scrollIntoView) zona.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (primero && primero.focus) setTimeout(function () { primero.focus({ preventScroll: true }); }, 250);
    return false;
  }

  // Premiar pronto, castigar tarde: al salir de un campo con datos se valida; un campo con error se
  // revalida mientras se escribe; los obligatorios vacíos se marcan al tocar «Siguiente».
  form.addEventListener('focusout', function (ev) {
    var n = nombreDe(ev.target);
    if (!n || !reglas[n] || n === 'fecha_nac' || ev.target.type === 'radio' || ev.target.type === 'checkbox') return;
    var tieneDato = ev.target.value && String(ev.target.value).trim();
    var conError = form.querySelector('[data-campo="' + n + '"].campo--error');
    if (tieneDato || conError) validarCampo(n);
  });
  form.addEventListener('input', function (ev) {
    var n = nombreDe(ev.target);
    if (n && form.querySelector('[data-campo="' + n + '"].campo--error')) validarCampo(n);
    programarBorrador();
  });
  form.addEventListener('change', function (ev) {
    var n = nombreDe(ev.target);
    if (n === 'fecha_nac') {
      pintarEdad();
      if ($('fn-dia').value && $('fn-mes').value && $('fn-anio').value) validarCampo('fecha_nac');
    } else if (n === 'batea' || n === 'lanza' || n === 'acepta' || n === 'posicion') {
      validarCampo(n);
    }
    programarBorrador();
  });

  // ---------- Pasos ----------
  function irAPaso(n) {
    estado.paso = n;
    form.querySelectorAll('.paso').forEach(function (f) { f.hidden = Number(f.getAttribute('data-paso')) !== n; });
    $('texto-paso').innerHTML = 'Paso ' + n + ' de ' + TOTAL + ' · <strong>' + NOMBRES[n - 1] + '</strong>';
    document.querySelectorAll('.progreso__lista li').forEach(function (li) {
      var p = Number(li.getAttribute('data-paso'));
      li.classList.toggle('hecho', p < n);
      if (p === n) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
    });
    $('btn-siguiente').textContent = n === TOTAL ? 'Enviar inscripción' : 'Siguiente';
    $('aviso-error').hidden = true;
    if (n === TOTAL) pintarResumen();
    window.scrollTo(0, 0);
    var titulo = form.querySelector('.paso[data-paso="' + n + '"] .paso__titulo');
    if (titulo) { titulo.setAttribute('tabindex', '-1'); titulo.focus({ preventScroll: true }); }
    anunciar('Paso ' + n + ' de ' + TOTAL + ': ' + NOMBRES[n - 1]);
    guardarBorrador();
  }

  function empezar() {
    if (!estado.id) estado.id = nuevoId();
    $('borrador').hidden = true;
    mostrar('formulario');
    irAPaso(1);
  }
  document.querySelectorAll('[data-accion="empezar"]').forEach(function (b) { b.addEventListener('click', empezar); });

  $('btn-atras').addEventListener('click', function () {
    if (estado.paso > 1) irAPaso(estado.paso - 1); else mostrar('portada');
  });

  $('btn-siguiente').addEventListener('click', function () {
    if (!validarPaso(estado.paso)) return;
    if (estado.paso < TOTAL) irAPaso(estado.paso + 1); else enviar();
  });

  // ---------- Foto: recorte 3:4 y compresión en el teléfono ----------
  function alElegirFoto() {
    var f = this.files && this.files[0];
    this.value = '';
    if (!f) return;
    if (!/^image\//.test(f.type) && !/\.(jpe?g|png|heic|heif|webp)$/i.test(f.name)) { marcar('foto', 'Ese archivo no es una imagen.'); return; }
    $('foto-ayuda').textContent = 'Preparando la foto…';
    prepararFoto(f).then(function (url) {
      ponerFoto(url);
      marcar('foto', '');
      guardarBorrador();
    }).catch(function () {
      $('foto-ayuda').textContent = 'De frente y con buena luz, como para un carnet.';
      marcar('foto', 'No pudimos leer esa foto. Prueba con otra.');
    });
  }
  $('foto-camara').addEventListener('change', alElegirFoto);
  $('foto-galeria').addEventListener('change', alElegirFoto);

  function ponerFoto(url) {
    estado.foto = url;
    $('foto-preview').src = url;
    $('foto-preview').hidden = false;
    $('foto-vacio').style.display = 'none';
    $('foto-marco').classList.add('tiene-foto');
    $('foto-ayuda').textContent = 'Si quieres cambiarla, toma o elige otra.';
  }

  function quitarFoto() {
    estado.foto = null;
    $('foto-preview').hidden = true;
    $('foto-preview').removeAttribute('src');
    $('foto-vacio').style.display = '';
    $('foto-marco').classList.remove('tiene-foto');
    $('foto-ayuda').textContent = 'De frente y con buena luz, como para un carnet.';
  }

  function cargarImagen(file) {
    if (window.createImageBitmap) {
      return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return cargarConImg(file); });
    }
    return cargarConImg(file);
  }
  function cargarConImg(file) {
    return new Promise(function (ok, mal) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () { ok(img); };
      img.onerror = function () { URL.revokeObjectURL(url); mal(new Error('imagen')); };
      img.src = url;
    });
  }
  function prepararFoto(file) {
    return cargarImagen(file).then(function (img) {
      var w = img.width, h = img.height, objetivo = 3 / 4, sw = w, sh = h;
      if (w / h > objetivo) sw = h * objetivo; else sh = w / objetivo;
      var sx = (w - sw) / 2, sy = (h - sh) / 2 * 0.6; // un poco hacia arriba: la cara suele estar arriba
      var c = document.createElement('canvas');
      c.width = 600; c.height = 800;
      var ctx = c.getContext('2d');
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, c.width, c.height);
      ctx.drawImage(img, sx, sy, sw, sh, 0, 0, c.width, c.height);
      if (img.close) img.close();
      return c.toDataURL('image/jpeg', 0.85);
    });
  }

  // ---------- Borrador en el teléfono ----------
  var relojBorrador;
  function programarBorrador() { clearTimeout(relojBorrador); relojBorrador = setTimeout(guardarBorrador, 400); }

  function guardarBorrador() {
    if (estado.enviado || !estado.id || vistas.formulario.hidden) return;
    var d = { v: 2, t: Date.now(), paso: estado.paso, id: estado.id, campos: {}, foto: estado.foto };
    TEXTOS.forEach(function (k) { d.campos[k] = form.elements[k].value; });
    d.campos.batea = valor('batea');
    d.campos.lanza = valor('lanza');
    d.campos.fn = [$('fn-dia').value, $('fn-mes').value, $('fn-anio').value];
    d.campos.acepta = $('acepta').checked;
    if (!guardar(CLAVE_BORRADOR, JSON.stringify(d))) { d.foto = null; guardar(CLAVE_BORRADOR, JSON.stringify(d)); }
  }

  function leerBorrador() {
    var s = leer(CLAVE_BORRADOR);
    if (!s) return null;
    try {
      var d = JSON.parse(s);
      if (!d || d.v !== 2 || !d.campos || Date.now() - d.t > 7 * 864e5) return null;
      return d;
    } catch (e) { return null; }
  }

  function restaurar(d) {
    TEXTOS.forEach(function (k) { if (typeof d.campos[k] === 'string') form.elements[k].value = d.campos[k]; });
    ['batea', 'lanza'].forEach(function (k) {
      var r = form.querySelector('input[name="' + k + '"][value="' + d.campos[k] + '"]');
      if (r) r.checked = true;
    });
    if (Array.isArray(d.campos.fn)) { $('fn-dia').value = d.campos.fn[0] || ''; $('fn-mes').value = d.campos.fn[1] || ''; $('fn-anio').value = d.campos.fn[2] || ''; }
    $('acepta').checked = !!d.campos.acepta;
    estado.id = d.id || nuevoId();
    if (d.foto) ponerFoto(d.foto);
    pintarEdad();
  }

  (function ofrecerBorrador() {
    var d = leerBorrador();
    if (!d || !(d.campos.nombre || d.foto || d.campos.rep_nombre)) return;
    $('borrador-titulo').textContent = 'Tienes una inscripción sin terminar' + (d.campos.nombre ? ' de ' + d.campos.nombre : '') + '.';
    $('borrador').hidden = false;
    $('btn-continuar').addEventListener('click', function () {
      restaurar(d);
      $('borrador').hidden = true;
      mostrar('formulario');
      irAPaso(Math.min(Math.max(Number(d.paso) || 1, 1), TOTAL));
    });
    $('btn-descartar').addEventListener('click', function () {
      borrar(CLAVE_BORRADOR);
      $('borrador').hidden = true;
      anunciar('Borrador descartado.');
    });
  })();

  // ---------- Resumen ----------
  function datos() {
    var iso = fechaIso(), fn = iso && iso !== 'invalida' ? iso.split('-') : [];
    return {
      nombre: valor('nombre'), fecha_nac: iso === 'invalida' ? '' : iso, fecha_nac_txt: fn.length === 3 ? fn[2] + '/' + fn[1] + '/' + fn[0] : '',
      documento: valor('documento'), nacionalidad: valor('nacionalidad'), telefono: valor('telefono'), correo: valor('correo'),
      direccion: valor('direccion'), rep_nombre: valor('rep_nombre'), rep_telefono: valor('rep_telefono'), liga: valor('liga'),
      posicion: valor('posicion'), batea: valor('batea'), lanza: valor('lanza'), estatura: valor('estatura'), peso: valor('peso'),
      millas: valor('millas'), anio_firma: valor('anio_firma')
    };
  }

  function esc(t) {
    return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
  }

  function pintarResumen() {
    var d = datos(), edad = calcularEdad(d.fecha_nac);
    function lista(pares) {
      return '<dl>' + pares.filter(function (p) { return p[1]; }).map(function (p) {
        return '<dt>' + esc(p[0]) + '</dt><dd>' + esc(p[1]) + '</dd>';
      }).join('') + '</dl>';
    }
    function bloque(titulo, paso, cuerpo) {
      return '<section class="resumen__bloque"><div class="resumen__cabeza"><h3>' + titulo + '</h3>' +
        '<button type="button" class="resumen__editar" data-ir="' + paso + '" aria-label="Editar ' + titulo.toLowerCase() + '">' +
        '<svg class="ico" aria-hidden="true"><use href="#i-pencil"></use></svg>Editar</button></div>' + cuerpo + '</section>';
    }
    $('resumen').innerHTML =
      bloque('Atleta', 1, '<div class="resumen__atleta"><img src="' + estado.foto + '" alt=""><div>' +
        lista([['Nombre', d.nombre], ['Nacimiento', d.fecha_nac_txt + (edad !== '' ? ' (' + edad + ' años)' : '')],
          ['Documento', d.documento], ['Nacionalidad', d.nacionalidad]]) + '</div></div>') +
      bloque('Contacto', 2, lista([['Dirección', d.direccion], ['Teléfono', d.telefono], ['Representante', d.rep_nombre],
        ['Teléfono', d.rep_telefono], ['Correo', d.correo]])) +
      bloque('Béisbol', 3, lista([['Liga', d.liga], ['Posición', d.posicion], ['Batea', d.batea], ['Lanza', d.lanza],
        ['Estatura', d.estatura], ['Peso', d.peso], ['Millas', d.millas], ['Año de firma', d.anio_firma]]));
  }

  $('resumen').addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-ir]');
    if (b) irAPaso(Number(b.getAttribute('data-ir')));
  });

  // ---------- Planilla PDF ----------
  function fechaHoy() {
    var h = new Date();
    return pad(h.getDate()) + '/' + pad(h.getMonth() + 1) + '/' + h.getFullYear();
  }
  function armarPdf() {
    var d = datos();
    return window.GTPlanilla.crear({ datos: d, edad: calcularEdad(d.fecha_nac), foto: estado.foto, logo: estado.logo, id: estado.id, fecha: fechaHoy() });
  }
  function nombreArchivo() {
    return 'Planilla ' + datos().nombre.replace(/[\\/:*?"<>|]/g, '') + ' - GT Baseball Academy.pdf';
  }
  function archivoPdf() {
    var doc = estado.pdf || armarPdf();
    return new File([doc.output('blob')], nombreArchivo(), { type: 'application/pdf' });
  }
  function puedeCompartir() {
    try { return !!(navigator.canShare && navigator.share && navigator.canShare({ files: [archivoPdf()] })); } catch (e) { return false; }
  }

  $('btn-pdf').addEventListener('click', function () {
    try { (estado.pdf || armarPdf()).save(nombreArchivo()); } catch (e) { alert('No se pudo generar el PDF en este teléfono.'); }
  });
  $('btn-compartir').addEventListener('click', function () {
    var nombre = datos().nombre;
    navigator.share({ files: [archivoPdf()], title: 'Planilla de inscripción', text: 'Planilla de inscripción de ' + nombre + ' en GT Baseball Academy' })
      .catch(function () { /* cancelado por el apoderado */ });
  });

  // ---------- Envío con avance real ----------
  var anillo = $('cargador-avance');
  function mostrarCarga() {
    $('cargador').classList.remove('cargador--procesando');
    anillo.style.strokeDashoffset = '100';
    $('enviando-titulo').textContent = 'Enviando la inscripción';
    $('enviando-detalle').textContent = 'Preparando la planilla';
    $('enviando').hidden = false;
  }
  function avance(pct) {
    anillo.style.strokeDashoffset = String(100 - pct);
    $('enviando-detalle').textContent = 'Subiendo la foto y la planilla: ' + pct + ' %';
  }
  function procesando() {
    anillo.style.strokeDashoffset = '';
    $('cargador').classList.add('cargador--procesando');
    $('enviando-titulo').textContent = 'Procesando';
    $('enviando-detalle').textContent = 'Guardando la inscripción y enviando los correos';
  }

  function enviar() {
    if (estado.enviando || estado.enviado) return;
    if (navigator.onLine === false) { terminar(false, { tipo: 'offline' }); return; }
    estado.enviando = true;
    $('btn-siguiente').disabled = true;
    mostrarCarga();
    var inicio = Date.now();
    // Deja pintar el cargador antes del trabajo pesado (armar el PDF).
    setTimeout(function () {
      var cuerpo;
      try {
        estado.pdf = armarPdf();
        var d = datos();
        d.acepta = true;
        cuerpo = JSON.stringify({ v: 2, id: estado.id, prueba: PRUEBA, hp: $('hp').value, datos: d, foto: estado.foto, pdf: estado.pdf.output('datauristring') });
      } catch (e) { fin(false, { tipo: 'pdf' }); return; }
      var xhr = new XMLHttpRequest();
      xhr.open('POST', ENDPOINT);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.timeout = 90000;
      if (xhr.upload) {
        xhr.upload.onprogress = function (e) { if (e.lengthComputable) avance(Math.round(e.loaded / e.total * 100)); };
        xhr.upload.onload = procesando;
      }
      xhr.onload = function () {
        var j = {};
        try { j = JSON.parse(xhr.responseText || '{}'); } catch (e) { j = {}; }
        var ok = xhr.status >= 200 && xhr.status < 300 && j.ok === true;
        if (!ok) j.tipo = j.errores && j.errores.indexOf('limite') !== -1 ? 'limite' : (xhr.status === 400 ? 'datos' : 'servidor');
        // El cargador se ve al menos un instante: evita un parpadeo si la red es muy rápida.
        setTimeout(function () { fin(ok, j); }, Math.max(0, 900 - (Date.now() - inicio)));
      };
      xhr.onerror = function () { fin(false, { tipo: navigator.onLine === false ? 'offline' : 'red' }); };
      xhr.ontimeout = function () { fin(false, { tipo: 'tiempo' }); };
      xhr.send(cuerpo);
    }, 60);
  }

  function fin(ok, info) {
    $('enviando').hidden = true;
    $('btn-siguiente').disabled = false;
    estado.enviando = false;
    if (ok) { estado.enviado = true; borrar(CLAVE_BORRADOR); }
    terminar(ok, info || {});
  }

  function iconoResultado(ok, tipo) {
    var id = ok ? 'i-circle-check' : (tipo === 'offline' ? 'i-wifi-off' : 'i-alert-circle');
    var simbolo = document.getElementById(id);
    var caja = $('listo-icono');
    caja.classList.toggle('error', !ok);
    caja.innerHTML = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">' + (simbolo ? simbolo.innerHTML : '') + '</svg>';
    if (ok) { var trazos = caja.querySelectorAll('path'); if (trazos.length) trazos[trazos.length - 1].setAttribute('class', 'trazo'); }
  }

  var MENSAJES = {
    offline: ['Sin conexión', 'No tienes internet en este momento. Tus datos siguen guardados en este teléfono: conéctate y toca «Intentar de nuevo».'],
    red: ['No se pudo enviar', 'Hubo un problema de conexión. Tus datos siguen guardados: inténtalo de nuevo.'],
    tiempo: ['La conexión está lenta', 'El envío tardó demasiado. Tus datos siguen guardados: inténtalo de nuevo con mejor señal.'],
    limite: ['Espera un momento', 'Se enviaron varias inscripciones seguidas desde aquí. Espera unos minutos e inténtalo de nuevo.'],
    datos: ['Falta un dato', 'La academia no pudo recibir la inscripción porque falta o está mal un dato. Revísalo y vuelve a enviar.'],
    servidor: ['No se pudo enviar', 'La academia no pudo recibir la inscripción en este momento. Tus datos siguen guardados: inténtalo en unos minutos.'],
    pdf: ['No se pudo preparar', 'Este navegador no pudo armar la planilla. Prueba con Chrome o Safari actualizado.']
  };

  function terminar(ok, info) {
    var d = datos();
    estado.ultimo = ok ? 'ok' : (info.tipo || 'servidor');
    iconoResultado(ok, info.tipo);
    var correo = $('listo-correo');
    if (ok) {
      $('listo-titulo').textContent = '¡Inscripción enviada!';
      $('listo-texto').textContent = 'GT Baseball Academy ya recibió la planilla de ' + d.nombre + '.';
      if (info.correo_apoderado === true) {
        correo.innerHTML = '<svg class="ico" aria-hidden="true"><use href="#i-mail"></use></svg><span>Te enviamos la confirmación con la planilla a <strong>' + esc(d.correo) + '</strong>. Si no la ves, revisa la carpeta de spam.</span>';
        correo.hidden = false;
      } else if (info.correo_apoderado === false) {
        correo.innerHTML = '<svg class="ico" aria-hidden="true"><use href="#i-alert-circle"></use></svg><span>No pudimos enviarte el correo. Guarda la planilla con los botones de aquí abajo.</span>';
        correo.hidden = false;
      } else {
        correo.hidden = true;
      }
    } else {
      var m = MENSAJES[info.tipo] || MENSAJES.servidor;
      $('listo-titulo').textContent = m[0];
      $('listo-texto').textContent = m[1];
      correo.hidden = true;
    }
    $('btn-reintentar').hidden = ok;
    $('btn-reintentar').querySelector('span').textContent = estado.ultimo === 'datos' ? 'Revisar datos' : 'Intentar de nuevo';
    $('btn-compartir').hidden = !ok || !puedeCompartir();
    $('btn-pdf').hidden = info.tipo === 'pdf';
    $('btn-otro').hidden = !ok;
    $('listo-id').textContent = 'N.º de inscripción: ' + estado.id;
    mostrar('listo');
    $('listo-titulo').focus({ preventScroll: true });
    anunciar($('listo-titulo').textContent + '. ' + $('listo-texto').textContent);
  }

  $('btn-reintentar').addEventListener('click', function () {
    mostrar('formulario');
    if (estado.ultimo === 'datos') { irAPaso(1); return; }
    irAPaso(TOTAL);
    enviar();
  });

  // Otro atleta: conserva los datos del representante (hermanos).
  $('btn-otro').addEventListener('click', function () {
    var conservar = ['direccion', 'rep_nombre', 'rep_telefono', 'correo'];
    var copia = {};
    conservar.forEach(function (k) { copia[k] = valor(k); });
    form.reset();
    conservar.forEach(function (k) { form.elements[k].value = copia[k]; });
    form.querySelectorAll('.campo--error').forEach(function (c) { c.classList.remove('campo--error'); });
    form.querySelectorAll('.campo__error').forEach(function (p) { p.textContent = ''; });
    quitarFoto();
    estado.pdf = null; estado.enviado = false; estado.id = nuevoId();
    pintarEdad();
    mostrar('formulario');
    irAPaso(1);
  });
})();
