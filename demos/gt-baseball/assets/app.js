/* Formulario de inscripción GT Baseball Academy (v2, AutomatizaTech).
 * Pasos con nombre, validación al salir de cada campo, borrador en el teléfono, planilla PDF armada aquí,
 * paso de pago con comprobante, envío con avance real (XHR) y resultado con compartir o descargar.
 * Con ?prueba=1 n8n marca PRUEBA y avisa solo a AutomatizaTech. El número de WhatsApp (data-whatsapp) y las
 * formas de pago (#datos-pago) los pone el despliegue: el repositorio es público y no los guarda. */
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
  var TOTAL = 5;
  var NOMBRES = ['Atleta', 'Contacto', 'Béisbol', 'Pago', 'Enviar'];
  var MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  var CLAVE_BORRADOR = 'gt-inscripcion-borrador-v2';
  var CLAVE_TEMA = 'gt-tema';
  var TEXTOS = ['nombre', 'documento', 'nacionalidad', 'direccion', 'telefono', 'rep_nombre', 'rep_telefono', 'correo',
    'liga', 'posicion', 'estatura', 'peso', 'millas', 'anio_firma'];

  var form = $('formulario');
  var vistas = { portada: $('vista-portada'), formulario: $('vista-formulario'), listo: $('vista-listo') };
  var estado = { paso: 1, foto: null, logo: null, id: null, pdf: null, comprobante: null, enviado: false, enviando: false };

  // Formas de pago: {monto, permitir_despues, metodos: [{id, nombre, detalle, comprobante, datos: [{etiqueta, valor, copiar}], texto}]}.
  var PAGO = {};
  try { PAGO = JSON.parse($('datos-pago').textContent || '{}') || {}; } catch (e) { PAGO = {}; }
  var METODOS = (Array.isArray(PAGO.metodos) ? PAGO.metodos : []).filter(function (m) { return m && m.id && m.nombre; });
  var PERMITIR_DESPUES = PAGO.permitir_despues !== false;
  var ICONOS_PAGO = { pago_movil: 'i-device-mobile', zelle: 'i-currency-dollar', efectivo: 'i-cash-banknote' };

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
    'gmail.con': 'gmail.com', 'gmail.cm': 'gmail.com', 'gmail.comm': 'gmail.com', 'gmal.com': 'gmail.com', 'gmil.com': 'gmail.com',
    'gmaill.com': 'gmail.com', 'hotmial.com': 'hotmail.com', 'hotmal.com': 'hotmail.com', 'hotmai.com': 'hotmail.com',
    'hotmail.co': 'hotmail.com', 'hotmail.con': 'hotmail.com', 'hotmail.comm': 'hotmail.com', 'yaho.com': 'yahoo.com',
    'yahoo.co': 'yahoo.com', 'yahoo.con': 'yahoo.com', 'yahoo.comm': 'yahoo.com', 'outlok.com': 'outlook.com',
    'outlook.co': 'outlook.com', 'outlook.con': 'outlook.com', 'icloud.co': 'icloud.com', 'icloud.con': 'icloud.com',
    'iclod.com': 'icloud.com', 'icoud.com': 'icloud.com' };
  function numero(t) { var m = String(t).replace(',', '.').match(/\d+(\.\d+)?/); return m ? parseFloat(m[0]) : NaN; }

  // Teléfonos de Venezuela: 11 dígitos con el 0 (0412-1234567). Celulares 0412 y 0422 (Digitel), 0414 y 0424
  // (Movistar), 0416 y 0426 (Movilnet); fijos 02XX. Se acepta sin el 0 o con +58 y queda escrito como 0412-1234567.
  // Un número de otro país va con + y el código del país (8 a 15 dígitos): hay representantes con WhatsApp de afuera.
  var CELULARES_VE = ['412', '414', '416', '422', '424', '426'];
  function analizarTelefono(t) {
    var s = String(t || '').trim(), d = s.replace(/\D/g, '');
    if (!s) return { tipo: 'vacio' };
    if (!/^[0-9+()\-\s.]+$/.test(s)) return { tipo: 'malo' };
    if (/^\+|^00/.test(s)) {
      d = d.replace(/^00/, '');
      if (d.indexOf('58') !== 0) return d.length >= 8 && d.length <= 15 ? { tipo: 'extranjero', texto: s.replace(/\s+/g, ' ') } : { tipo: 'largo' };
      d = d.slice(2);
    } else if (d.length === 12 && d.indexOf('58') === 0) {
      d = d.slice(2);
    }
    if (d.length === 10 && d.charAt(0) !== '0') d = '0' + d;
    if (d.length !== 11 || d.charAt(0) !== '0') return { tipo: 'largo' };
    var cod = d.slice(1, 4), texto = d.slice(0, 4) + '-' + d.slice(4);
    if (CELULARES_VE.indexOf(cod) !== -1) return { tipo: 'celular', texto: texto };
    if (cod.charAt(0) === '2') return { tipo: 'fijo', texto: texto };
    return { tipo: 'codigo', codigo: '0' + cod };
  }
  function reglaTelefono(t, soloCelular) {
    var a = analizarTelefono(t);
    if (a.tipo === 'vacio' || a.tipo === 'celular' || a.tipo === 'extranjero') return '';
    if (a.tipo === 'fijo') return soloCelular ? 'Escribe un celular con WhatsApp (0412, 0414, 0416, 0422, 0424 o 0426): ahí te agrega la academia al grupo.' : '';
    if (a.tipo === 'codigo') return 'El ' + a.codigo + ' no es un código de Venezuela. Los celulares empiezan por 0412, 0414, 0416, 0422, 0424 o 0426.';
    if (a.tipo === 'malo') return 'Usa solo números. Ej: 0412-1234567.';
    return 'El número debe tener 11 dígitos, con el código: 0412-1234567. Si es de otro país, empieza con + y el código del país.';
  }

  // Nombre y apellido completos: al menos dos palabras de 2 letras o más, sin contar «de», «la», «del»…
  var CONECTORES = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'das', 'do', 'dos', 'van', 'von', 'di'];
  function nombrePersona(v, vacio) {
    if (!v) return vacio;
    if (!LETRAS.test(v)) return 'Usa solo letras, sin números ni símbolos.';
    var llenas = v.split(/\s+/).filter(function (p) { return p.replace(/[.'-]/g, '').length >= 2 && CONECTORES.indexOf(p.toLowerCase()) === -1; });
    return llenas.length >= 2 ? '' : 'Escribe nombre y apellido completos, sin iniciales.';
  }
  // Todo en minúsculas o todo en mayúsculas se ordena («maría de los ángeles» → «María de los Ángeles»); lo demás se respeta.
  function capitalizar(v) {
    if (!v || (v !== v.toLowerCase() && v !== v.toUpperCase())) return v;
    return v.toLowerCase().split(/(\s+|-)/).map(function (p, i) {
      if (!p || /^\s+$|^-$/.test(p) || (i > 0 && CONECTORES.indexOf(p) !== -1)) return p;
      return p.charAt(0).toUpperCase() + p.slice(1);
    }).join('');
  }

  // Cédula venezolana: V o E y 6 a 8 números, escrita V-30.123.456. Pasaporte: 6 a 12 letras y números, con algún número.
  function analizarDocumento(v) {
    var s = String(v || '').toUpperCase().replace(/[\s.\-]/g, '');
    if (!s) return { tipo: 'vacio' };
    var m = /^([VE])?(\d{6,8})$/.exec(s);
    if (m) return { tipo: 'cedula', texto: (m[1] || 'V') + '-' + m[2].replace(/\B(?=(\d{3})+(?!\d))/g, '.') };
    if (/^(?=.*\d)[A-Z0-9]{6,12}$/.test(s)) return { tipo: 'pasaporte', texto: s };
    return { tipo: 'malo' };
  }
  function normalizarEstatura(v) {
    var n = numero(v);
    if (!v || /['′"″]/.test(v) || isNaN(n)) return v;
    return ((/cm/i.test(v) || n > 3 ? n : n * 100) / 100).toFixed(2).replace('.', ',') + ' m';
  }
  function normalizarPeso(v) {
    var n = numero(v);
    if (!v || isNaN(n)) return v;
    return String(n).replace('.', ',') + (/lb|libra/i.test(v) ? ' lb' : ' kg');
  }

  var reglas = {
    foto: function () { return estado.foto ? '' : 'Agrega una foto del atleta.'; },
    nombre: function () { return nombrePersona(valor('nombre'), 'Escribe el nombre completo.'); },
    documento: function () {
      return analizarDocumento(valor('documento')).tipo === 'malo' ? 'Revisa el documento: la cédula lleva 6 a 8 números (V-30.123.456); el pasaporte, letras y números.' : '';
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
      return v.length >= 10 && v.split(/\s+/).length >= 2 && /[A-Za-zÁÉÍÓÚÑáéíóúñ]/.test(v) ? '' : 'Escribe la dirección completa: calle o sector y ciudad.';
    },
    telefono: function () { return reglaTelefono(valor('telefono'), false); },
    rep_nombre: function () { return nombrePersona(valor('rep_nombre'), 'Escribe tu nombre y apellido.'); },
    rep_telefono: function () {
      return valor('rep_telefono') ? reglaTelefono(valor('rep_telefono'), true) : 'Escribe tu celular: ahí te agrega la academia al grupo de WhatsApp.';
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
    pago_metodo: function () { return !METODOS.length || metodoElegido() ? '' : 'Elige cómo vas a pagar.'; },
    comprobante: function () {
      if (!pideComprobante() || estado.comprobante) return '';
      if (PERMITIR_DESPUES && $('pago_despues').checked) return '';
      return PERMITIR_DESPUES ? 'Adjunta el comprobante o marca que lo envías después.' : 'Adjunta el comprobante del pago.';
    },
    acepta: function () { return $('acepta').checked ? '' : 'Para enviar, marca la autorización.'; }
  };

  var CAMPOS = {
    1: ['foto', 'nombre', 'fecha_nac', 'documento', 'nacionalidad'],
    2: ['direccion', 'telefono', 'rep_nombre', 'rep_telefono', 'correo'],
    3: ['liga', 'posicion', 'batea', 'lanza', 'estatura', 'peso', 'millas', 'anio_firma'],
    4: ['pago_metodo', 'comprobante'],
    5: ['acepta']
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
    if (nombre === 'comprobante') return $('comp-archivo');
    var el = form.elements[nombre];
    if (el && typeof RadioNodeList !== 'undefined' && el instanceof RadioNodeList) return el[0];
    return el;
  }

  function nombreDe(el) {
    if (!el) return '';
    if (el.id === 'foto-camara' || el.id === 'foto-galeria') return 'foto';
    if (el.id === 'comp-archivo') return 'comprobante';
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
  // Al salir de un campo válido, el dato queda escrito siempre igual: así llega parejo a la planilla, al correo y al Sheet.
  var NORMALIZAR = {
    nombre: capitalizar, rep_nombre: capitalizar, nacionalidad: capitalizar, liga: capitalizar,
    telefono: function (v) { return analizarTelefono(v).texto || v; },
    rep_telefono: function (v) { return analizarTelefono(v).texto || v; },
    documento: function (v) { return analizarDocumento(v).texto || v; },
    correo: function (v) { return v.trim().toLowerCase(); },
    direccion: function (v) { return v.replace(/\s+/g, ' ').trim(); },
    estatura: normalizarEstatura, peso: normalizarPeso
  };
  function limpio(k) {
    var v = valor(k);
    return v && NORMALIZAR[k] && !reglas[k]() ? NORMALIZAR[k](v) : v;
  }

  form.addEventListener('focusout', function (ev) {
    var n = nombreDe(ev.target);
    if (!n || !reglas[n] || n === 'fecha_nac' || ev.target.type === 'radio' || ev.target.type === 'checkbox') return;
    var tieneDato = ev.target.value && String(ev.target.value).trim();
    var conError = form.querySelector('[data-campo="' + n + '"].campo--error');
    if (!tieneDato && !conError) return;
    if (validarCampo(n) && tieneDato && NORMALIZAR[n]) {
      var nuevo = NORMALIZAR[n](ev.target.value);
      if (nuevo !== ev.target.value) { ev.target.value = nuevo; programarBorrador(); }
    }
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
    } else if (n === 'pago_metodo') {
      mostrarMetodo();
      validarCampo('pago_metodo');
      marcar('comprobante', '');
    } else if (n === 'pago_despues') {
      if (form.querySelector('[data-campo="comprobante"].campo--error')) validarCampo('comprobante');
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
    if (estado.paso > 1) irAPaso(estado.paso - 1); else volverAlInicio();
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
    }).catch(function (e) {
      $('foto-ayuda').textContent = 'De frente y con buena luz, como para un carnet.';
      marcar('foto', e && e.message === 'chica' ? 'La foto es muy pequeña y saldría borrosa en la planilla. Toma una nueva o elige otra de mejor calidad.'
        : 'No pudimos leer esa foto. Prueba con otra.');
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
  // Menos de 300 px por el lado corto saldría borrosa en la planilla (la foto se agranda a 600x800).
  var FOTO_MINIMA = 300;
  function prepararFoto(file) {
    return cargarImagen(file).then(function (img) {
      if (Math.min(img.width, img.height) < FOTO_MINIMA) { if (img.close) img.close(); throw new Error('chica'); }
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

  // ---------- Pago: forma de pago, datos para copiar y comprobante ----------
  function metodoElegido() { var r = form.querySelector('input[name="pago_metodo"]:checked'); return r ? r.value : ''; }
  function metodoActual() {
    var id = metodoElegido();
    return METODOS.filter(function (m) { return m.id === id; })[0] || null;
  }
  function pideComprobante() { var m = metodoActual(); return !!(m && m.comprobante); }
  // 'adjunto' | 'despues' | 'no_aplica' (se paga sin comprobante, como el efectivo) | '' (sin forma de pago)
  function estadoPago() {
    var m = metodoActual();
    if (!m) return '';
    if (!m.comprobante) return 'no_aplica';
    return estado.comprobante ? 'adjunto' : 'despues';
  }

  // Monto de cada forma de pago (por pago móvil, en bolívares a la tasa oficial); si no trae, el general.
  function montoDe(m) { return (m && m.monto) || PAGO.monto || ''; }

  function filaDato(etiqueta, valor, copiar) {
    var boton = copiar ? '<button type="button" class="copiar" data-copiar="' + esc(copiar) + '" data-que="' + esc(etiqueta) + '"' +
      ' aria-label="Copiar ' + esc(String(etiqueta).toLowerCase()) + ': ' + esc(valor) + '">' +
      '<svg class="ico" aria-hidden="true"><use href="#i-copy"></use></svg><span>Copiar</span></button>' : '';
    // Un correo largo se corta antes de la @ y no a mitad de palabra.
    return '<div class="dato"><span class="dato__etiqueta">' + esc(etiqueta) + '</span>' + boton +
      '<span class="dato__valor">' + esc(valor).replace(/@/g, '<wbr>@') + '</span></div>';
  }

  // Filas para pagar: el monto primero y después los datos. Fuera del paso (pantalla final) van con la forma de pago.
  function htmlDatosPago(m, fueraDelPaso) {
    var filas = [];
    if (fueraDelPaso) filas.push(filaDato('Forma de pago', m.nombre + (m.detalle ? ' · ' + m.detalle : '')));
    if (montoDe(m)) filas.push(filaDato('Monto a pagar', montoDe(m)));
    (m.datos || []).forEach(function (d) { filas.push(filaDato(d.etiqueta, d.valor, d.copiar)); });
    return (filas.length ? '<div class="datos-pago">' + filas.join('') + '</div>' : '') +
      (m.texto ? '<p class="pago-caja__texto">' + esc(m.texto) + '</p>' : '');
  }

  (function pintarMetodos() {
    if (!METODOS.length) {
      // Sin datos de pago (solo pasa en desarrollo, sin el despliegue): el paso avisa y no pide nada.
      $('pago-intro').textContent = 'La academia te indicará cómo pagar la inscripción. Cuando verifique el pago, te agregará al grupo de WhatsApp.';
      form.querySelector('[data-campo="pago_metodo"]').hidden = true;
      return;
    }
    $('pago-metodos').innerHTML = METODOS.map(function (m) {
      return '<label class="metodo"><input type="radio" name="pago_metodo" value="' + esc(m.id) + '">' +
        '<span class="metodo__caja"><svg class="ico metodo__ico" aria-hidden="true"><use href="#' + (ICONOS_PAGO[m.id] || 'i-receipt') + '"></use></svg>' +
        '<span class="metodo__texto"><span class="metodo__nombre">' + esc(m.nombre) + '</span>' +
        (m.detalle ? '<span class="metodo__detalle">' + esc(m.detalle) + '</span>' : '') + '</span>' +
        '<svg class="ico metodo__marca" aria-hidden="true"><use href="#i-circle-check"></use></svg></span></label>';
    }).join('');
    if (PAGO.monto) {
      $('pago-monto').textContent = 'Monto de la inscripción: ' + PAGO.monto;
      $('pago-monto').hidden = false;
      $('portada-monto').textContent = 'La inscripción es de ' + PAGO.monto + '. ';
    }
    if (!PERMITIR_DESPUES) { $('comp-despues').hidden = true; $('portada-despues').hidden = true; }
  })();

  function mostrarMetodo() {
    var m = metodoActual();
    $('pago-caja').hidden = !m;
    $('bloque-comprobante').hidden = !(m && m.comprobante);
    if (!m) return;
    $('pago-caja-titulo').textContent = m.nombre;
    $('pago-datos').innerHTML = htmlDatosPago(m, false);
  }

  function copiarTexto(t) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(t);
    return new Promise(function (ok, mal) {
      var a = document.createElement('textarea');
      a.value = t; a.setAttribute('readonly', ''); a.style.position = 'fixed'; a.style.top = '0'; a.style.opacity = '0';
      document.body.appendChild(a); a.select();
      var hecho = false;
      try { hecho = document.execCommand('copy'); } catch (e) { hecho = false; }
      document.body.removeChild(a);
      if (hecho) ok(); else mal(new Error('copiar'));
    });
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('.copiar') : null;
    if (!b) return;
    var texto = b.querySelector('span'), uso = b.querySelector('use');
    copiarTexto(b.getAttribute('data-copiar')).then(function () {
      b.classList.add('copiado'); texto.textContent = 'Copiado'; uso.setAttribute('href', '#i-check');
      anunciar('Copiado: ' + String(b.getAttribute('data-que')).toLowerCase());
      clearTimeout(b._reloj);
      b._reloj = setTimeout(function () { b.classList.remove('copiado'); texto.textContent = 'Copiar'; uso.setAttribute('href', '#i-copy'); }, 2200);
    }).catch(function () { anunciar('No se pudo copiar. Mantén presionado el dato para copiarlo.'); });
  });

  var MAX_PDF = 3 * 1024 * 1024;
  function tamano(bytes) {
    return bytes < 1048576 ? Math.max(1, Math.round(bytes / 1024)) + ' KB' : (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
  }
  // Capturas del banco: se achican a 2000 px por el lado largo (el texto sigue legible) y van en JPEG.
  function prepararComprobante(file) {
    return cargarImagen(file).then(function (img) {
      var k = Math.min(1, 2000 / Math.max(img.width, img.height));
      var c = document.createElement('canvas');
      c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
      var ctx = c.getContext('2d');
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, c.width, c.height);
      ctx.drawImage(img, 0, 0, c.width, c.height);
      if (img.close) img.close();
      return c.toDataURL('image/jpeg', 0.85);
    });
  }
  function ponerComprobante(c) {
    estado.comprobante = c;
    var vista = $('comp-vista');
    vista.innerHTML = c.tipo === 'pdf' ? '<svg class="ico" aria-hidden="true"><use href="#i-file-type-pdf"></use></svg>' : '<img alt="" src="' + c.url + '">';
    $('comp-nombre').textContent = c.tipo === 'pdf' ? (c.nombre || 'Comprobante.pdf') : 'Captura del pago';
    $('comp-peso').textContent = (c.tipo === 'pdf' ? 'PDF · ' : 'Imagen · ') + tamano(c.peso);
    $('comp-adjunto').hidden = false;
    $('comp-boton-texto').textContent = 'Cambiar comprobante';
    $('pago_despues').checked = false;
    $('comp-despues').hidden = true;
  }
  function quitarComprobante() {
    estado.comprobante = null;
    $('comp-adjunto').hidden = true;
    $('comp-vista').innerHTML = '';
    $('comp-boton-texto').textContent = 'Adjuntar captura o PDF';
    $('comp-despues').hidden = !PERMITIR_DESPUES;
  }
  $('comp-archivo').addEventListener('change', function () {
    var f = this.files && this.files[0];
    this.value = '';
    if (!f) return;
    var esPdf = f.type === 'application/pdf' || /\.pdf$/i.test(f.name);
    var esImagen = /^image\//.test(f.type) || /\.(jpe?g|png|heic|heif|webp)$/i.test(f.name);
    if (!esPdf && !esImagen) { marcar('comprobante', 'Adjunta una imagen (la captura del pago) o un PDF.'); return; }
    if (esPdf && f.size > MAX_PDF) { marcar('comprobante', 'Ese PDF pesa más de 3 MB. Envía mejor una captura de pantalla del pago.'); return; }
    $('comp-boton-texto').textContent = 'Preparando…';
    var listo = esPdf ? leerComoDataUrl(f).then(function (u) {
      var datos64 = String(u).split(',')[1] || '';
      if (datos64.slice(0, 5) !== 'JVBER') throw new Error('pdf'); // debe empezar con %PDF
      return 'data:application/pdf;base64,' + datos64;
    }) : prepararComprobante(f);
    listo.then(function (url) {
      ponerComprobante({ url: url, tipo: esPdf ? 'pdf' : 'imagen', nombre: f.name, peso: esPdf ? f.size : Math.round((url.length - 23) * 0.75) });
      marcar('comprobante', '');
      anunciar('Comprobante adjuntado.');
      guardarBorrador();
    }).catch(function () {
      $('comp-boton-texto').textContent = estado.comprobante ? 'Cambiar comprobante' : 'Adjuntar captura o PDF';
      marcar('comprobante', esPdf ? 'No pudimos leer ese PDF. Prueba con una captura de pantalla del pago.' : 'No pudimos leer esa imagen. Prueba con otra captura.');
    });
  });
  $('comp-quitar').addEventListener('click', function () {
    quitarComprobante();
    anunciar('Comprobante quitado.');
    guardarBorrador();
    $('comp-archivo').focus();
  });

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
    d.campos.pago_metodo = metodoElegido();
    d.campos.pago_despues = $('pago_despues').checked;
    // El comprobante se guarda si cabe; si el teléfono no tiene espacio, primero se suelta él y después la foto.
    d.comp = estado.comprobante && estado.comprobante.url.length < 1500000 ? estado.comprobante : null;
    if (guardar(CLAVE_BORRADOR, JSON.stringify(d))) return;
    d.comp = null;
    if (guardar(CLAVE_BORRADOR, JSON.stringify(d))) return;
    d.foto = null;
    guardar(CLAVE_BORRADOR, JSON.stringify(d));
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
    form.querySelectorAll('input[name="pago_metodo"]').forEach(function (r) { r.checked = r.value === d.campos.pago_metodo; });
    if (d.comp && typeof d.comp.url === 'string') ponerComprobante(d.comp);
    else $('pago_despues').checked = !!d.campos.pago_despues;
    mostrarMetodo();
    pintarEdad();
  }

  // Aviso de inscripción sin terminar: al abrir la página y cada vez que se vuelve al inicio desde el formulario.
  function ofrecerBorrador() {
    var d = leerBorrador();
    var hay = !!(d && (d.campos.nombre || d.foto || d.campos.rep_nombre));
    if (hay) $('borrador-titulo').textContent = 'Tienes una inscripción sin terminar' + (d.campos.nombre ? ' de ' + d.campos.nombre : '') + '.';
    $('borrador').hidden = !hay;
  }
  $('btn-continuar').addEventListener('click', function () {
    var d = leerBorrador();
    $('borrador').hidden = true;
    if (!d) return;
    restaurar(d);
    mostrar('formulario');
    irAPaso(Math.min(Math.max(Number(d.paso) || 1, 1), TOTAL));
  });
  $('btn-descartar').addEventListener('click', function () {
    borrar(CLAVE_BORRADOR);
    limpiarFormulario([]);
    estado.id = null;
    $('borrador').hidden = true;
    anunciar('Borrador descartado.');
  });
  ofrecerBorrador();

  // Deja el formulario en blanco; conserva los campos que se le pidan (los del representante, para un hermano).
  function limpiarFormulario(conservar) {
    var copia = {};
    conservar.forEach(function (k) { copia[k] = valor(k); });
    form.reset();
    conservar.forEach(function (k) { form.elements[k].value = copia[k]; });
    form.querySelectorAll('.campo--error').forEach(function (c) { c.classList.remove('campo--error'); });
    form.querySelectorAll('.campo__error').forEach(function (p) { p.textContent = ''; });
    form.querySelectorAll('[aria-invalid="true"]').forEach(function (c) { c.setAttribute('aria-invalid', 'false'); });
    $('aviso-error').hidden = true;
    quitarFoto();
    quitarComprobante();
    mostrarMetodo();
    estado.pdf = null;
    pintarEdad();
  }

  // Salir al inicio: pregunta antes. Lo escrito queda en el borrador y el inicio ofrece continuar o empezar de nuevo.
  function volverAlInicio() {
    guardarBorrador();
    mostrar('portada');
    ofrecerBorrador();
    anunciar('Volviste al inicio.');
  }
  var dialogoSalir = $('dialogo-salir');
  function pedirSalir() {
    if (dialogoSalir && typeof dialogoSalir.showModal === 'function') { dialogoSalir.showModal(); return; }
    if (window.confirm('¿Deseas salir de la inscripción? Lo que llevas queda guardado en este teléfono.')) volverAlInicio();
  }
  document.querySelectorAll('[data-accion="salir"]').forEach(function (b) { b.addEventListener('click', pedirSalir); });
  $('btn-quedarse').addEventListener('click', function () { dialogoSalir.close(); });
  $('btn-salir-si').addEventListener('click', function () { dialogoSalir.close(); volverAlInicio(); });

  // ---------- Resumen ----------
  function datos() {
    var iso = fechaIso(), fn = iso && iso !== 'invalida' ? iso.split('-') : [];
    // limpio(): el mismo formato del campo al salir, aunque la persona no haya salido de él (autocompletar).
    return {
      nombre: limpio('nombre'), fecha_nac: iso === 'invalida' ? '' : iso, fecha_nac_txt: fn.length === 3 ? fn[2] + '/' + fn[1] + '/' + fn[0] : '',
      documento: limpio('documento'), nacionalidad: limpio('nacionalidad'), telefono: limpio('telefono'), correo: limpio('correo'),
      direccion: limpio('direccion'), rep_nombre: limpio('rep_nombre'), rep_telefono: limpio('rep_telefono'), liga: limpio('liga'),
      posicion: valor('posicion'), batea: valor('batea'), lanza: valor('lanza'), estatura: limpio('estatura'), peso: limpio('peso'),
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
        ['Estatura', d.estatura], ['Peso', d.peso], ['Millas', d.millas], ['Año de firma', d.anio_firma]])) +
      (METODOS.length ? bloque('Pago', 4, lista(filasPago())) : '');
  }

  function filasPago() {
    var m = metodoActual(), ep = estadoPago();
    if (!m) return [];
    var comp = { adjunto: estado.comprobante && estado.comprobante.tipo === 'pdf' ? 'PDF adjunto' : 'Captura adjunta', despues: 'Lo envías después' }[ep];
    return [['Forma de pago', m.nombre + (m.detalle ? ' · ' + m.detalle : '')], ['Comprobante', comp || '']];
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
    $('enviando-detalle').textContent = 'Subiendo ' + (estado.subiendo || 'la foto y la planilla') + ': ' + pct + ' %';
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
        var d = datos(), m = metodoActual(), ep = estadoPago();
        d.acepta = true;
        d.pago_metodo = m ? m.id : '';
        d.pago_despues = ep === 'despues';
        // El comprobante solo viaja si la forma de pago lo pide (con efectivo no se manda aunque haya quedado uno).
        var comp = ep === 'adjunto' ? estado.comprobante.url : '';
        estado.subiendo = comp ? 'la foto, la planilla y el comprobante' : 'la foto y la planilla';
        cuerpo = JSON.stringify({ v: 2, id: estado.id, prueba: PRUEBA, hp: $('hp').value, datos: d, foto: estado.foto,
          pdf: estado.pdf.output('datauristring'), comprobante: comp });
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
      // Desde los 15 años el atleta también firma la planilla (la línea la pone planilla.js con la misma edad).
      var edadFirma = calcularEdad(d.fecha_nac), limiteFirma = window.GTPlanilla ? window.GTPlanilla.EDAD_FIRMA_ATLETA : 15;
      $('listo-texto').textContent = 'GT Baseball Academy ya recibió la planilla de ' + d.nombre + '.' +
        (edadFirma !== '' && edadFirma >= limiteFirma ? ' Al imprimirla, la firman ' + d.nombre.split(' ')[0] + ' y su representante.' : '');
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
    pintarPagoFinal(ok, d);
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

  // Próximo paso del pago en la pantalla final: lo mismo que dice el correo de confirmación.
  var GRUPO = 'Cuando la academia verifique el pago, te agregará al grupo de WhatsApp.';
  function pintarPagoFinal(ok, d) {
    var caja = $('listo-pago'), m = metodoActual(), ep = estadoPago(), wa = $('listo-pago-wa');
    caja.hidden = !ok || !m;
    if (caja.hidden) return;
    var titulo, texto, datosHtml = '';
    if (ep === 'adjunto') {
      titulo = 'Pago en revisión';
      texto = 'Recibimos tu comprobante. ' + GRUPO;
    } else if (ep === 'despues') {
      titulo = 'Falta el comprobante';
      texto = 'Paga con estos datos y envía la captura del pago respondiendo el correo de confirmación o por WhatsApp. ' + GRUPO;
      datosHtml = htmlDatosPago(m, true);
    } else {
      titulo = m.nombre + (m.detalle ? ' · ' + m.detalle : '');
      texto = (m.texto ? m.texto + ' ' : '') + GRUPO;
      datosHtml = montoDe(m) ? '<div class="datos-pago">' + filaDato('Monto a pagar', montoDe(m)) + '</div>' : '';
    }
    $('listo-pago-titulo').textContent = titulo;
    $('listo-pago-texto').textContent = texto;
    $('listo-pago-datos').innerHTML = datosHtml;
    wa.hidden = !(ep === 'despues' && WHATSAPP.length >= 8);
    if (!wa.hidden) {
      wa.href = 'https://wa.me/' + WHATSAPP + '?text=' + encodeURIComponent('Hola, envío el comprobante de pago de la inscripción de ' + d.nombre + ' (N.º ' + estado.id + ').');
    }
  }

  $('btn-reintentar').addEventListener('click', function () {
    mostrar('formulario');
    if (estado.ultimo === 'datos') { irAPaso(1); return; }
    irAPaso(TOTAL);
    enviar();
  });

  // Otro atleta: conserva los datos del representante (hermanos).
  $('btn-otro').addEventListener('click', function () {
    limpiarFormulario(['direccion', 'rep_nombre', 'rep_telefono', 'correo']);
    estado.enviado = false; estado.id = nuevoId();
    mostrar('formulario');
    irAPaso(1);
  });
})();
