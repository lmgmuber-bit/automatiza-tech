/* Formulario de inscripción GT Baseball Academy (prototipo AutomatizaTech).
 * Pasos → resumen → arma la planilla PDF en el teléfono → la envía con la foto al webhook de n8n.
 * Con ?prueba=1 el envío se marca PRUEBA y n8n solo avisa a AutomatizaTech. */
(function () {
  'use strict';

  var app = document.getElementById('app');
  var ENDPOINT = app.getAttribute('data-endpoint');
  var PRUEBA = /[?&]prueba=1\b/.test(location.search);
  var TOTAL = 4;

  var $ = function (id) { return document.getElementById(id); };
  var form = $('formulario');
  var vistas = { portada: $('vista-portada'), formulario: $('vista-formulario'), listo: $('vista-listo') };
  var estado = { paso: 1, foto: null, logo: null, id: null, pdf: null, enviado: false };

  if (PRUEBA) $('etiqueta-prueba').hidden = false;

  // WhatsApp de la academia: solo si la copia publicada trae el número (en el repo va vacío).
  var WHATSAPP = (app.getAttribute('data-whatsapp') || '').replace(/\D/g, '');
  if (WHATSAPP.length >= 8) {
    var enlaceWa = 'https://wa.me/' + WHATSAPP + '?text=' +
      encodeURIComponent('Hola, quiero información sobre las inscripciones de GT Baseball Academy.');
    document.querySelectorAll('[data-whatsapp-link]').forEach(function (a) { a.href = enlaceWa; a.hidden = false; });
  }

  // Logo para el PDF (se carga una vez)
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

  function mostrar(nombre) {
    Object.keys(vistas).forEach(function (k) { vistas[k].hidden = k !== nombre; });
    window.scrollTo(0, 0);
  }

  function nuevoId() {
    var r = Math.floor(Math.random() * 1296).toString(36).toUpperCase();
    return 'GT-' + Date.now().toString(36).toUpperCase() + ('0' + r).slice(-2);
  }

  // ---------- Pasos ----------
  function irAPaso(n) {
    estado.paso = n;
    form.querySelectorAll('.paso').forEach(function (f) { f.hidden = Number(f.getAttribute('data-paso')) !== n; });
    $('texto-paso').textContent = 'Paso ' + n + ' de ' + TOTAL;
    $('barra-progreso').style.width = (n / TOTAL * 100) + '%';
    $('btn-siguiente').textContent = n === TOTAL ? 'Enviar inscripción' : 'Siguiente';
    if (n === TOTAL) pintarResumen();
    window.scrollTo(0, 0);
    var titulo = form.querySelector('.paso[data-paso="' + n + '"] .paso__titulo');
    if (titulo) { titulo.setAttribute('tabindex', '-1'); titulo.focus({ preventScroll: true }); }
  }

  $('btn-empezar').addEventListener('click', function () {
    if (!estado.id) estado.id = nuevoId();
    mostrar('formulario');
    irAPaso(1);
  });

  $('btn-atras').addEventListener('click', function () {
    if (estado.paso > 1) irAPaso(estado.paso - 1); else mostrar('portada');
  });

  $('btn-siguiente').addEventListener('click', function () {
    if (!validarPaso(estado.paso)) return;
    if (estado.paso < TOTAL) irAPaso(estado.paso + 1); else enviar();
  });

  // ---------- Validación ----------
  function valor(nombre) {
    var el = form.elements[nombre];
    if (!el) return '';
    if (el instanceof RadioNodeList) return el.value || '';
    return (el.value || '').trim();
  }

  function marcar(nombre, mensaje) {
    var p = form.querySelector('[data-error-para="' + nombre + '"]');
    if (p) p.textContent = mensaje || '';
    var campo = p ? p.closest('.campo') : null;
    if (campo) campo.classList.toggle('campo--error', !!mensaje);
  }

  var TEL = /^[0-9+()\-\s.]{6,30}$/;
  var CORREO = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  var reglas = {
    1: function () {
      var e = {};
      if (!estado.foto) e.foto = 'Agrega una foto del atleta.';
      if (valor('nombre').length < 3) e.nombre = 'Escribe el nombre completo.';
      var edad = calcularEdad(valor('fecha_nac'));
      if (!valor('fecha_nac')) e.fecha_nac = 'Indica la fecha de nacimiento.';
      else if (edad === '' || edad < 3 || edad > 30) e.fecha_nac = 'Revisa la fecha: la edad debe estar entre 3 y 30 años.';
      if (!valor('nacionalidad')) e.nacionalidad = 'Indica la nacionalidad.';
      return e;
    },
    2: function () {
      var e = {};
      if (valor('direccion').length < 5) e.direccion = 'Escribe la dirección.';
      if (valor('telefono') && !TEL.test(valor('telefono'))) e.telefono = 'Revisa el número (solo dígitos, espacios o +).';
      if (valor('correo') && !CORREO.test(valor('correo'))) e.correo = 'Revisa el correo.';
      if (valor('rep_nombre').length < 3) e.rep_nombre = 'Escribe el nombre y apellido del representante.';
      if (!TEL.test(valor('rep_telefono'))) e.rep_telefono = 'Escribe un teléfono de contacto.';
      return e;
    },
    3: function () {
      var e = {};
      if (!valor('posicion')) e.posicion = 'Elige una posición (o «Aún no definida»).';
      if (!valor('batea')) e.batea = 'Elige con qué mano batea.';
      if (!valor('lanza')) e.lanza = 'Elige con qué mano lanza.';
      return e;
    },
    4: function () {
      return $('acepta').checked ? {} : { acepta: 'Para enviar, marca la autorización.' };
    }
  };

  var CAMPOS_PASO = {
    1: ['foto', 'nombre', 'fecha_nac', 'nacionalidad'],
    2: ['direccion', 'telefono', 'correo', 'rep_nombre', 'rep_telefono'],
    3: ['posicion', 'batea', 'lanza'],
    4: ['acepta']
  };

  function validarPaso(n) {
    var errores = reglas[n]();
    var primero = null;
    CAMPOS_PASO[n].forEach(function (k) {
      marcar(k, errores[k]);
      if (errores[k] && !primero) primero = k;
    });
    if (primero) {
      var el = primero === 'foto' ? form.querySelector('.foto') : (form.elements[primero] instanceof RadioNodeList ? form.elements[primero][0] : form.elements[primero]);
      if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (el && el.focus && primero !== 'foto') el.focus({ preventScroll: true });
      return false;
    }
    return true;
  }

  form.addEventListener('input', function (ev) {
    var n = ev.target.name;
    if (n) marcar(n, '');
  });
  form.addEventListener('change', function (ev) {
    var n = ev.target.name;
    if (n) marcar(n, '');
  });

  // ---------- Edad ----------
  function calcularEdad(iso) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
    if (!m) return '';
    var hoy = new Date();
    var e = hoy.getFullYear() - Number(m[1]);
    var mes = hoy.getMonth() + 1, dia = hoy.getDate();
    if (mes < Number(m[2]) || (mes === Number(m[2]) && dia < Number(m[3]))) e--;
    return e;
  }
  $('fecha_nac').max = new Date().toISOString().slice(0, 10);
  $('fecha_nac').addEventListener('change', function () {
    var e = calcularEdad(this.value);
    $('edad').textContent = e === '' || e < 0 ? '—' : e + ' años';
  });

  // ---------- Foto: recorte 3:4 y compresión en el teléfono ----------
  $('foto').addEventListener('change', function () {
    var f = this.files && this.files[0];
    if (!f) return;
    if (!/^image\//.test(f.type) && !/\.(jpe?g|png|heic|heif|webp)$/i.test(f.name)) {
      marcar('foto', 'Ese archivo no es una imagen.');
      return;
    }
    $('foto-ayuda').textContent = 'Procesando la foto…';
    prepararFoto(f).then(function (url) {
      estado.foto = url;
      $('foto-preview').src = url;
      $('foto-preview').hidden = false;
      $('foto-vacio').hidden = true;
      form.querySelector('.foto').classList.add('tiene-foto');
      $('foto-ayuda').textContent = 'Toca la foto si quieres cambiarla.';
      marcar('foto', '');
    }).catch(function () {
      $('foto-ayuda').textContent = 'Una foto de frente, como la de un carnet.';
      marcar('foto', 'No pudimos leer esa foto. Prueba con otra.');
    });
  });

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
      var w = img.width, h = img.height, objetivo = 3 / 4;
      var sw = w, sh = h;
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

  // ---------- Resumen ----------
  function datos() {
    var fn = valor('fecha_nac').split('-');
    return {
      nombre: valor('nombre'), fecha_nac: valor('fecha_nac'), fecha_nac_txt: fn.length === 3 ? fn[2] + '/' + fn[1] + '/' + fn[0] : '',
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
    var d = datos();
    var edad = calcularEdad(d.fecha_nac);
    function lista(pares) {
      return '<dl>' + pares.filter(function (p) { return p[1]; }).map(function (p) {
        return '<dt>' + esc(p[0]) + '</dt><dd>' + esc(p[1]) + '</dd>';
      }).join('') + '</dl>';
    }
    function bloque(titulo, paso, cuerpo) {
      return '<section class="resumen__bloque"><div class="resumen__cabeza"><h3>' + titulo + '</h3>' +
        '<button type="button" class="resumen__editar" data-ir="' + paso + '">Editar</button></div>' + cuerpo + '</section>';
    }
    $('resumen').innerHTML =
      bloque('Atleta', 1, '<div class="resumen__atleta"><img src="' + estado.foto + '" alt=""><div>' +
        lista([['Nombre', d.nombre], ['Nacimiento', d.fecha_nac_txt + (edad !== '' ? ' (' + edad + ' años)' : '')],
          ['Documento', d.documento], ['Nacionalidad', d.nacionalidad]]) + '</div></div>') +
      bloque('Contacto', 2, lista([['Dirección', d.direccion], ['Teléfono', d.telefono], ['Correo', d.correo],
        ['Representante', d.rep_nombre], ['Tel. representante', d.rep_telefono]])) +
      bloque('Béisbol', 3, lista([['Liga', d.liga], ['Posición', d.posicion], ['Batea', d.batea], ['Lanza', d.lanza],
        ['Estatura', d.estatura], ['Peso', d.peso], ['Millas', d.millas], ['Año de firma', d.anio_firma]]));
  }

  $('resumen').addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-ir]');
    if (b) irAPaso(Number(b.getAttribute('data-ir')));
  });

  // ---------- PDF ----------
  function fechaHoy() {
    var h = new Date();
    return ('0' + h.getDate()).slice(-2) + '/' + ('0' + (h.getMonth() + 1)).slice(-2) + '/' + h.getFullYear();
  }

  function armarPdf() {
    var d = datos();
    return window.GTPlanilla.crear({ datos: d, edad: calcularEdad(d.fecha_nac), foto: estado.foto, logo: estado.logo, id: estado.id, fecha: fechaHoy() });
  }

  function nombreArchivo() {
    return 'Planilla ' + datos().nombre.replace(/[\\/:*?"<>|]/g, '') + ' - GT Baseball Academy.pdf';
  }

  $('btn-pdf').addEventListener('click', function () {
    try { (estado.pdf || armarPdf()).save(nombreArchivo()); }
    catch (e) { alert('No se pudo generar el PDF en este teléfono.'); }
  });

  // ---------- Envío ----------
  function enviar() {
    if (estado.enviado) return;
    var boton = $('btn-siguiente');
    boton.disabled = true;
    $('enviando').hidden = false;
    var cuerpo;
    try {
      estado.pdf = armarPdf();
      var d = datos();
      d.acepta = true;
      cuerpo = JSON.stringify({
        v: 1, id: estado.id, prueba: PRUEBA, hp: $('hp').value, datos: d,
        foto: estado.foto, pdf: estado.pdf.output('datauristring')
      });
    } catch (e) {
      terminar(false, 'No se pudo preparar la planilla en este teléfono. Prueba con otro navegador.');
      return;
    }
    var ctrl = window.AbortController ? new AbortController() : null;
    var reloj = setTimeout(function () { if (ctrl) ctrl.abort(); }, 60000);
    fetch(ENDPOINT, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: cuerpo, signal: ctrl ? ctrl.signal : undefined })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { r: r, j: j }; }); })
      .then(function (x) {
        clearTimeout(reloj);
        if (x.r.ok && x.j.ok) { estado.enviado = true; terminar(true); }
        else terminar(false, 'La academia no pudo recibir la inscripción (' + (x.j.errores ? x.j.errores.join(', ') : 'error ' + x.r.status) + ').');
      })
      .catch(function () {
        clearTimeout(reloj);
        terminar(false, 'No hay conexión o tardó demasiado. Revisa tu internet e inténtalo de nuevo.');
      });
  }

  function terminar(ok, mensaje) {
    $('enviando').hidden = true;
    $('btn-siguiente').disabled = false;
    var nombre = datos().nombre;
    $('listo-icono').classList.toggle('error', !ok);
    $('listo-icono').innerHTML = ok
      ? '<svg viewBox="0 0 24 24" width="44" height="44"><path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>'
      : '<svg viewBox="0 0 24 24" width="44" height="44"><path d="M12 7v6M12 16.5v.5" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>';
    $('listo-titulo').textContent = ok ? '¡Inscripción enviada!' : 'No se pudo enviar';
    $('listo-texto').textContent = ok
      ? 'La academia recibió la planilla de ' + nombre + '. Descárgala si quieres imprimirla y llevarla firmada.'
      : mensaje + ' Tus datos siguen aquí; también puedes descargar la planilla y llevarla impresa.';
    $('btn-reintentar').hidden = ok;
    $('btn-otro').hidden = !ok;
    $('listo-id').textContent = 'N.º de inscripción: ' + estado.id;
    mostrar('listo');
  }

  $('btn-reintentar').addEventListener('click', function () { mostrar('formulario'); irAPaso(TOTAL); enviar(); });

  // Otro atleta: conserva contacto y representante (hermanos)
  $('btn-otro').addEventListener('click', function () {
    var guardar = ['direccion', 'rep_nombre', 'rep_telefono', 'correo'];
    var copia = {};
    guardar.forEach(function (k) { copia[k] = valor(k); });
    form.reset();
    guardar.forEach(function (k) { form.elements[k].value = copia[k]; });
    estado.foto = null; estado.pdf = null; estado.enviado = false; estado.id = nuevoId();
    $('foto').value = '';
    $('foto-preview').hidden = true; $('foto-preview').removeAttribute('src');
    $('foto-vacio').hidden = false;
    form.querySelector('.foto').classList.remove('tiene-foto');
    $('edad').textContent = '—';
    mostrar('formulario');
    irAPaso(1);
  });
})();
