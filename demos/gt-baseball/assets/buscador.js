/* GT Baseball Academy · buscador privado de planillas (buscador/index.html).
 * Pide la clave, trae la lista de inscritos del flujo n8n «GT Baseball · Buscador de planillas» y la filtra, ordena y
 * pagina aquí mismo (son pocos cientos de filas). Nada de los datos se dibuja con innerHTML: todo va por textContent.
 * La clave se guarda en este equipo solo si se marca «Recordar» (si no, dura mientras la pestaña esté abierta).
 * En local (localhost o 127.0.0.1) acepta ?endpoint=<url> para probar contra un receptor de prueba. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var app = $('app');
  var CLAVE_ACCESO = 'gt-buscador-clave';
  var CLAVE_TEMA = 'gt-tema';
  var LOCAL = /^(localhost|127\.0\.0\.1)$/.test(location.hostname);
  var ENDPOINT = app.getAttribute('data-endpoint');
  if (LOCAL) {
    var otro = new URLSearchParams(location.search).get('endpoint');
    if (otro) ENDPOINT = otro;
  }
  var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

  var estado = { atletas: [], filtrados: [], pagina: 1, cargando: false };

  // ---------- Almacenamiento (puede no existir en modo privado) ----------
  function leer(clave) {
    try { return localStorage.getItem(clave) || sessionStorage.getItem(clave) || ''; } catch (e) { return ''; }
  }
  function guardarClave(valor, recordar) {
    try {
      localStorage.removeItem(CLAVE_ACCESO); sessionStorage.removeItem(CLAVE_ACCESO);
      if (valor) (recordar ? localStorage : sessionStorage).setItem(CLAVE_ACCESO, valor);
    } catch (e) { /* sin almacenamiento: se pedirá la clave cada vez */ }
  }

  // ---------- Tema (el mismo botón y la misma preferencia que la portada) ----------
  function temaClaro() { return document.documentElement.getAttribute('data-theme') === 'light'; }
  function pintarTema() {
    var claro = temaClaro();
    var b = $('btn-tema');
    b.setAttribute('aria-pressed', claro ? 'true' : 'false');
    b.title = claro ? 'Cambiar a modo oscuro' : 'Cambiar a modo claro';
    b.querySelector('use').setAttribute('href', claro ? '#i-moon' : '#i-sun');
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', claro ? '#f2f4f7' : '#0e1216');
  }
  $('btn-tema').addEventListener('click', function () {
    var nuevo = temaClaro() ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', nuevo);
    try { localStorage.setItem(CLAVE_TEMA, nuevo); } catch (e) {}
    pintarTema();
  });
  pintarTema();

  function anunciar(t) { var a = $('anuncio'); a.textContent = ''; setTimeout(function () { a.textContent = t; }, 60); }

  // ---------- Vistas ----------
  function verClave(error) {
    $('vista-lista').hidden = true;
    $('vista-clave').hidden = false;
    $('error-clave').textContent = error || '';
    $('clave').setAttribute('aria-invalid', error ? 'true' : 'false');
    if (error) $('clave').focus();
  }
  function verLista() {
    $('vista-clave').hidden = true;
    $('vista-lista').hidden = false;
  }

  // ---------- Pedir la lista ----------
  var MENSAJES = {
    401: 'La clave no es correcta. Revísala y vuelve a intentarlo.',
    429: 'Hubo demasiados intentos con una clave equivocada. Espera 15 minutos y vuelve a intentarlo.',
    503: 'El buscador todavía no está activado. Avísale a AutomatizaTech.',
    red: 'No se pudo conectar. Revisa tu internet y vuelve a intentarlo.',
    otro: 'El buscador no respondió bien. Intenta de nuevo en un momento.',
  };

  function pedirLista(clave) {
    return fetch(ENDPOINT, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ clave: clave }),
      cache: 'no-store', credentials: 'omit', referrerPolicy: 'no-referrer',
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) { return { estado: r.status, datos: d }; });
    }, function () { return { estado: 0, datos: {} }; });
  }

  // recordar: true o false guarda la clave (formulario); null no toca lo guardado (entrada automática y Actualizar).
  function cargar(clave, recordar, entrando) {
    if (estado.cargando) return;
    estado.cargando = true;
    var btn = entrando ? $('btn-entrar') : $('btn-actualizar');
    var texto = btn.querySelector('span') || btn;
    var antes = texto.textContent;
    btn.disabled = true;
    texto.textContent = entrando ? 'Entrando…' : 'Actualizando…';
    pedirLista(clave).then(function (res) {
      estado.cargando = false;
      btn.disabled = false;
      texto.textContent = antes;
      if (res.estado === 200 && res.datos && res.datos.ok && Array.isArray(res.datos.atletas)) {
        if (recordar !== null) guardarClave(clave, recordar);
        estado.atletas = res.datos.atletas.map(preparar);
        estado.clave = clave;
        verLista();
        aplicar(!entrando ? false : true);
        if (entrando) {
          var titulo = $('t-lista');
          titulo.setAttribute('tabindex', '-1');
          titulo.focus({ preventScroll: true });
        } else {
          anunciar('Lista actualizada.');
        }
        return;
      }
      if (res.estado === 401 || res.estado === 429 || res.estado === 503) {
        if (res.estado === 401) guardarClave('', false);
        verClave(MENSAJES[res.estado]);
        return;
      }
      if ($('vista-lista').hidden) verClave(res.estado === 0 ? MENSAJES.red : MENSAJES.otro);
      else anunciar(res.estado === 0 ? MENSAJES.red : MENSAJES.otro);
    });
  }

  $('form-clave').addEventListener('submit', function (e) {
    e.preventDefault();
    var clave = $('clave').value.trim();
    if (!clave) { verClave('Escribe la clave de acceso.'); return; }
    cargar(clave, $('recordar').checked, true);
  });
  $('ver-clave').addEventListener('click', function () {
    var campo = $('clave');
    var ver = campo.type === 'password';
    campo.type = ver ? 'text' : 'password';
    this.setAttribute('aria-pressed', ver ? 'true' : 'false');
    this.setAttribute('aria-label', ver ? 'Ocultar la clave' : 'Mostrar la clave');
    this.querySelector('use').setAttribute('href', ver ? '#i-eye-off' : '#i-eye');
  });
  $('btn-actualizar').addEventListener('click', function () { if (estado.clave) cargar(estado.clave, null, false); });
  $('btn-salir').addEventListener('click', function () {
    guardarClave('', false);
    estado = { atletas: [], filtrados: [], pagina: 1, cargando: false };
    $('cuerpo').textContent = '';
    $('clave').value = '';
    verClave('');
    $('clave').focus();
  });

  // ---------- Datos ----------
  function normalizar(t) {
    return String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
  }
  function soloDigitos(t) { return String(t || '').replace(/\D/g, ''); }

  // La fecha del Sheet viene como «AAAA-MM-DD HH:MM» (la escribe el flujo); por si alguien la edita a mano, también
  // se acepta «DD/MM/AAAA». Devuelve «AAAA-MM-DD» o ''.
  function fechaISO(t) {
    var s = String(t || '').trim();
    var m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) return m[1] + '-' + m[2] + '-' + m[3];
    m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
    if (m) return m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2);
    return '';
  }
  function fechaCorta(iso, original) {
    if (!iso) return original || '';
    var p = iso.split('-');
    return Number(p[2]) + ' ' + MESES[Number(p[1]) - 1] + ' ' + p[0];
  }

  function preparar(a) {
    var iso = fechaISO(a.fecha);
    return {
      datos: a,
      iso: iso,
      orden: String(a.fecha || ''),
      nombre: normalizar(a.nombre),
      digitos: soloDigitos(a.documento),
      idNorm: normalizar(a.id),
      pago: normalizar(a.pago),
    };
  }

  // ---------- Filtros ----------
  function valores() {
    var min = parseInt($('f-edad-min').value, 10);
    var max = parseInt($('f-edad-max').value, 10);
    return {
      texto: normalizar($('f-texto').value),
      posicion: $('f-posicion').value,
      pago: normalizar($('f-pago').value),
      comprobante: $('f-comprobante').value,
      edadMin: isNaN(min) ? null : min,
      edadMax: isNaN(max) ? null : max,
      desde: $('f-desde').value,
      hasta: $('f-hasta').value,
      pruebas: $('f-pruebas').checked,
      orden: $('f-orden').value,
      porPagina: parseInt($('f-por-pagina').value, 10) || 20,
    };
  }

  function cumple(p, f) {
    var a = p.datos;
    if (!f.pruebas && a.prueba) return false;
    if (f.texto) {
      var dig = soloDigitos(f.texto);
      var porCedula = dig.length >= 3 && p.digitos.indexOf(dig) !== -1;
      var palabras = f.texto.replace(/[^a-z0-9ñ ]/g, ' ').split(' ').filter(Boolean);
      var porNombre = palabras.length > 0 && palabras.every(function (w) { return p.nombre.indexOf(w) !== -1; });
      var porId = p.idNorm && p.idNorm.indexOf(f.texto) !== -1;
      if (!porCedula && !porNombre && !porId) return false;
    }
    if (f.posicion && a.posicion !== f.posicion) return false;
    if (f.pago && p.pago.indexOf(f.pago) !== 0) return false;
    if (f.comprobante && a.comprobante !== f.comprobante) return false;
    if (f.edadMin !== null && (a.edad === null || a.edad < f.edadMin)) return false;
    if (f.edadMax !== null && (a.edad === null || a.edad > f.edadMax)) return false;
    if (f.desde && (!p.iso || p.iso < f.desde)) return false;
    if (f.hasta && (!p.iso || p.iso > f.hasta)) return false;
    return true;
  }

  function ordenar(lista, orden) {
    var copia = lista.slice();
    copia.sort(function (x, y) {
      if (orden === 'nombre') return x.nombre.localeCompare(y.nombre, 'es');
      if (orden === 'edad') return (x.datos.edad == null ? 99 : x.datos.edad) - (y.datos.edad == null ? 99 : y.datos.edad) || x.nombre.localeCompare(y.nombre, 'es');
      if (orden === 'antiguo') return x.orden < y.orden ? -1 : x.orden > y.orden ? 1 : x.datos.fila - y.datos.fila;
      return x.orden < y.orden ? 1 : x.orden > y.orden ? -1 : y.datos.fila - x.datos.fila;
    });
    return copia;
  }

  function contarFiltros(f) {
    var n = 0;
    ['posicion', 'pago', 'comprobante', 'desde', 'hasta'].forEach(function (k) { if (f[k]) n++; });
    if (f.edadMin !== null || f.edadMax !== null) n++;
    if (f.pruebas) n++;
    var el = $('filtros-n');
    el.hidden = n === 0;
    el.textContent = n ? String(n) : '';
    el.setAttribute('aria-label', n === 1 ? '1 filtro activo' : n + ' filtros activos');
  }

  function aplicar(volverAlInicio) {
    var f = valores();
    contarFiltros(f);
    estado.filtrados = ordenar(estado.atletas.filter(function (p) { return cumple(p, f); }), f.orden);
    if (volverAlInicio) estado.pagina = 1;
    dibujar(f);
  }

  // ---------- Dibujo ----------
  function el(tag, clase, texto) {
    var n = document.createElement(tag);
    if (clase) n.className = clase;
    if (texto != null) n.textContent = texto;
    return n;
  }
  function icono(id) {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'ico');
    svg.setAttribute('aria-hidden', 'true');
    var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', '#' + id);
    svg.appendChild(use);
    return svg;
  }
  // Solo ids de Drive con la forma de un id; todo lo demás se descarta, así un dato raro no arma un enlace raro.
  function idDrive(id) { return /^[A-Za-z0-9_-]{10,}$/.test(String(id || '')) ? String(id) : ''; }
  function enlace(clase, href, ico, texto, etiqueta) {
    var a = el('a', clase);
    a.href = href; a.target = '_blank'; a.rel = 'noopener noreferrer';
    a.appendChild(icono(ico));
    a.appendChild(el('span', null, texto));
    if (etiqueta) a.setAttribute('aria-label', etiqueta);
    return a;
  }
  function dato(dl, k, v) {
    if (!v) return;
    dl.appendChild(el('dt', null, k));
    dl.appendChild(el('dd', null, v));
  }

  function fila(p) {
    var a = p.datos;
    var tr = el('tr');
    if (a.prueba) tr.className = 'es-prueba';

    var tdA = el('td', 'col-atleta');
    tdA.setAttribute('data-etiqueta', 'Atleta');
    var nombre = el('p', 'atleta__nombre', a.nombre);
    if (a.prueba) nombre.appendChild(el('span', 'chip chip--prueba', 'Prueba'));
    tdA.appendChild(nombre);
    tdA.appendChild(el('p', 'atleta__doc', a.documento ? 'Cédula ' + a.documento : 'Sin cédula'));
    var det = el('details', 'atleta__mas');
    det.appendChild(el('summary', null, 'Ver datos'));
    var dl = el('dl', 'atleta__datos');
    dato(dl, 'Nacimiento', a.nacimiento);
    dato(dl, 'Nacionalidad', a.nacionalidad);
    dato(dl, 'Liga', a.liga);
    dato(dl, 'Batea / lanza', [a.batea, a.lanza].filter(Boolean).join(' / '));
    dato(dl, 'Representante', a.representante);
    dato(dl, 'Tel. representante', a.rep_telefono);
    dato(dl, 'Teléfono', a.telefono);
    dato(dl, 'Correo', a.correo);
    dato(dl, 'N.º de inscripción', a.id);
    det.appendChild(dl);
    tdA.appendChild(det);
    tr.appendChild(tdA);

    var tdE = el('td', 'col-edad', a.edad == null ? '—' : a.edad + ' años');
    tdE.setAttribute('data-etiqueta', 'Edad');
    tr.appendChild(tdE);

    var tdP = el('td', 'col-posicion', a.posicion || '—');
    tdP.setAttribute('data-etiqueta', 'Posición');
    tr.appendChild(tdP);

    var tdF = el('td', 'col-fecha', fechaCorta(p.iso, a.fecha) || '—');
    tdF.setAttribute('data-etiqueta', 'Inscripción');
    tr.appendChild(tdF);

    var tdG = el('td', 'col-pago');
    tdG.setAttribute('data-etiqueta', 'Pago');
    var forma = String(a.pago || '').split('·')[0].trim();
    tdG.appendChild(el('p', 'pago__forma', forma || '—'));
    var estados = { adjunto: ['Comprobante recibido', 'chip--ok'], pendiente: ['Falta el comprobante', 'chip--falta'], no_aplica: ['Paga en la oficina', 'chip--neutro'] };
    if (estados[a.comprobante]) tdG.appendChild(el('span', 'chip ' + estados[a.comprobante][1], estados[a.comprobante][0]));
    tr.appendChild(tdG);

    var tdX = el('td', 'col-archivos');
    tdX.setAttribute('data-etiqueta', 'Planilla');
    var planilla = idDrive(a.planilla);
    if (planilla) {
      tdX.appendChild(enlace('boton boton--chico boton--principal archivo', 'https://drive.google.com/uc?export=download&id=' + encodeURIComponent(planilla),
        'i-download', 'Descargar planilla', 'Descargar la planilla de ' + a.nombre));
      var extra = el('div', 'archivos__mas');
      extra.appendChild(enlace('archivo-link', 'https://drive.google.com/file/d/' + encodeURIComponent(planilla) + '/view', 'i-file-type-pdf', 'Ver', 'Ver la planilla de ' + a.nombre));
      var foto = idDrive(a.foto);
      if (foto) extra.appendChild(enlace('archivo-link', 'https://drive.google.com/file/d/' + encodeURIComponent(foto) + '/view', 'i-photo', 'Foto', 'Ver la foto de ' + a.nombre));
      var comp = idDrive(a.comprobante_id);
      if (comp) extra.appendChild(enlace('archivo-link', 'https://drive.google.com/file/d/' + encodeURIComponent(comp) + '/view', 'i-receipt', 'Comprobante', 'Ver el comprobante de ' + a.nombre));
      tdX.appendChild(extra);
    } else {
      tdX.appendChild(el('p', 'archivos__falta', 'Sin planilla guardada'));
    }
    tr.appendChild(tdX);
    return tr;
  }

  function dibujar(f) {
    var total = estado.filtrados.length;
    var porPagina = f.porPagina;
    var paginas = Math.max(1, Math.ceil(total / porPagina));
    if (estado.pagina > paginas) estado.pagina = paginas;
    var desde = (estado.pagina - 1) * porPagina;
    var visibles = estado.filtrados.slice(desde, desde + porPagina);

    var cuerpo = $('cuerpo');
    cuerpo.textContent = '';
    var frag = document.createDocumentFragment();
    visibles.forEach(function (p) { frag.appendChild(fila(p)); });
    cuerpo.appendChild(frag);

    var reales = estado.atletas.filter(function (p) { return !p.datos.prueba; }).length;
    var hayFiltro = !!(f.texto || f.posicion || f.pago || f.comprobante || f.desde || f.hasta || f.edadMin !== null || f.edadMax !== null);
    $('cuenta').textContent = (total === 1 ? '1 atleta' : total + ' atletas') +
      (hayFiltro ? ' encontrados de ' + reales + ' inscritos' : (total === 1 ? ' inscrito' : ' inscritos'));

    var vacio = total === 0;
    $('vacio').hidden = !vacio;
    $('vacio-titulo').textContent = estado.atletas.length === 0 ? 'Todavía no hay inscripciones.' : 'No hay atletas con esos filtros.';
    $('btn-limpiar-2').hidden = estado.atletas.length === 0;
    $('tabla').hidden = vacio;
    $('paginas').hidden = paginas <= 1;
    $('pagina-txt').textContent = 'Página ' + estado.pagina + ' de ' + paginas;
    $('btn-anterior').disabled = estado.pagina <= 1;
    $('btn-siguiente').disabled = estado.pagina >= paginas;
  }

  // ---------- Eventos de los filtros ----------
  var espera = null;
  $('f-texto').addEventListener('input', function () {
    clearTimeout(espera);
    espera = setTimeout(function () { aplicar(true); }, 180);
  });
  ['f-posicion', 'f-pago', 'f-comprobante', 'f-edad-min', 'f-edad-max', 'f-desde', 'f-hasta', 'f-pruebas', 'f-orden', 'f-por-pagina'].forEach(function (id) {
    $(id).addEventListener('change', function () { aplicar(true); });
  });
  ['f-edad-min', 'f-edad-max'].forEach(function (id) {
    $(id).addEventListener('input', function () { clearTimeout(espera); espera = setTimeout(function () { aplicar(true); }, 250); });
  });
  $('filtros').addEventListener('submit', function (e) { e.preventDefault(); aplicar(true); });

  function limpiar() {
    $('f-texto').value = '';
    ['f-posicion', 'f-pago', 'f-comprobante'].forEach(function (id) { $(id).value = ''; });
    ['f-edad-min', 'f-edad-max', 'f-desde', 'f-hasta'].forEach(function (id) { $(id).value = ''; });
    $('f-pruebas').checked = false;
    aplicar(true);
    anunciar('Filtros limpios.');
  }
  $('btn-limpiar').addEventListener('click', limpiar);
  $('btn-limpiar-2').addEventListener('click', limpiar);

  function irA(n) {
    estado.pagina = n;
    dibujar(valores());
    var cabeza = $('t-lista');
    cabeza.setAttribute('tabindex', '-1');
    cabeza.focus({ preventScroll: true });
    cabeza.scrollIntoView({ block: 'start' });
  }
  $('btn-anterior').addEventListener('click', function () { if (estado.pagina > 1) irA(estado.pagina - 1); });
  $('btn-siguiente').addEventListener('click', function () { irA(estado.pagina + 1); });

  // En escritorio los filtros quedan abiertos; en el celular, cerrados para que se vea la lista.
  if (window.matchMedia('(min-width: 900px)').matches) $('filtros-mas').open = true;

  // ---------- Inicio: si la clave está guardada, entra directo ----------
  var guardada = leer(CLAVE_ACCESO);
  if (guardada) cargar(guardada, null, true);
  else verClave('');
})();
