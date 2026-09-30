/* GT Baseball Academy · buscador privado de planillas (buscador/index.html).
 * Pide la clave, trae la lista de inscritos del flujo n8n «GT Baseball · Buscador de planillas» y la filtra, ordena y
 * pagina aquí mismo (son pocos cientos de filas). Nada de los datos se dibuja con innerHTML: todo va por textContent.
 * La clave se guarda en este teléfono solo si se marca «Recordar» y vence a los 30 días; si no, dura mientras la
 * pestaña esté abierta. En local (localhost o 127.0.0.1) acepta ?endpoint=<url local> para probar contra un receptor
 * de prueba; con ese endpoint no entra sola con la clave guardada. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var app = $('app');
  var CLAVE_ACCESO = 'gt-buscador-clave';
  var CLAVE_TEMA = 'gt-tema';
  var DIAS_RECORDAR = 30;
  var ESPERA_MAX_MS = 25000;
  var LOCAL = /^(localhost|127\.0\.0\.1)$/.test(location.hostname);
  var ENDPOINT_OFICIAL = app.getAttribute('data-endpoint');
  var ENDPOINT = ENDPOINT_OFICIAL;
  if (LOCAL) {
    var otro = new URLSearchParams(location.search).get('endpoint');
    try { if (otro && /^(localhost|127\.0\.0\.1)$/.test(new URL(otro).hostname)) ENDPOINT = otro; } catch (e) { /* url inválida: se ignora */ }
  }
  var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

  var estado = { atletas: [], filtrados: [], pagina: 1, cargando: false, clave: '', fotos: {} };

  // ---------- Almacenamiento (puede no existir en modo privado) ----------
  // Se guarda {clave, vence}; una clave vencida o con otro formato se descarta.
  function leerClave() {
    var crudo = '';
    try { crudo = localStorage.getItem(CLAVE_ACCESO) || sessionStorage.getItem(CLAVE_ACCESO) || ''; } catch (e) { return ''; }
    try {
      var d = JSON.parse(crudo);
      if (d && typeof d.clave === 'string' && (!d.vence || d.vence > Date.now())) return d.clave;
    } catch (e) { /* formato viejo o dañado */ }
    guardarClave('', false);
    return '';
  }
  function guardarClave(valor, recordar) {
    try {
      localStorage.removeItem(CLAVE_ACCESO); sessionStorage.removeItem(CLAVE_ACCESO);
      if (valor) {
        var d = { clave: valor, vence: recordar ? Date.now() + DIAS_RECORDAR * 864e5 : 0 };
        (recordar ? localStorage : sessionStorage).setItem(CLAVE_ACCESO, JSON.stringify(d));
      }
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

  // Aviso visible bajo el contador (y leído por los lectores de pantalla): error en rojo, éxito breve.
  var avisoTimer = null;
  function aviso(texto, tipo) {
    var el = $('estado-lista');
    clearTimeout(avisoTimer);
    el.textContent = texto || '';
    el.className = 'lista__estado' + (tipo ? ' lista__estado--' + tipo : '');
    if (tipo === 'ok') avisoTimer = setTimeout(function () { el.textContent = ''; }, 6000);
  }

  // ---------- Vistas ----------
  function verClave(error, marcar) {
    $('vista-lista').hidden = true;
    $('vista-clave').hidden = false;
    $('error-clave').textContent = error || '';
    $('clave').setAttribute('aria-invalid', error && marcar !== false ? 'true' : 'false');
    if (error) $('clave').focus();
  }
  function verLista() {
    $('vista-clave').hidden = true;
    $('vista-lista').hidden = false;
  }

  // ---------- Pedir la lista ----------
  var MENSAJES = {
    401: 'La clave no es correcta. Revísala y vuelve a intentarlo.',
    '401-guardada': 'La clave guardada en este teléfono ya no sirve. Escribe la clave nueva.',
    429: 'Hubo demasiados intentos con una clave equivocada. Espera 15 minutos y vuelve a intentarlo.',
    503: 'El buscador todavía no está activado. Avísale a AutomatizaTech.',
    red: 'No se pudo conectar. Revisa tu internet y vuelve a intentarlo.',
    lento: 'El buscador está tardando más de lo normal. Revisa tu internet y vuelve a intentarlo.',
    otro: 'El buscador no respondió bien. Intenta de nuevo en un momento.',
  };

  function pedirLista(clave) {
    var control = typeof AbortController === 'function' ? new AbortController() : null;
    var corte = setTimeout(function () { if (control) control.abort(); }, ESPERA_MAX_MS);
    return fetch(ENDPOINT, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ clave: clave }),
      cache: 'no-store', credentials: 'omit', referrerPolicy: 'no-referrer', signal: control ? control.signal : undefined,
    }).then(function (r) {
      clearTimeout(corte);
      return r.json().catch(function () { return {}; }).then(function (d) { return { estado: r.status, datos: d }; });
    }, function (e) {
      clearTimeout(corte);
      return { estado: e && e.name === 'AbortError' ? 'lento' : 0, datos: {} };
    });
  }

  // recordar: true o false guarda la clave (formulario); null no toca lo guardado (entrada automática y Actualizar).
  function cargar(clave, recordar, entrando, automatica) {
    if (estado.cargando) return;
    estado.cargando = true;
    var btn = entrando ? $('btn-entrar') : $('btn-actualizar');
    var texto = btn.querySelector('span') || btn;
    var antes = texto.textContent;
    btn.disabled = true;
    texto.textContent = entrando ? 'Entrando…' : 'Actualizando…';
    if (!entrando) aviso('Actualizando la lista…');
    pedirLista(clave).then(function (res) {
      estado.cargando = false;
      btn.disabled = false;
      texto.textContent = antes;
      var d = res.datos;
      if (res.estado === 200 && d && d.ok === true && Array.isArray(d.atletas)) {
        if (recordar !== null) guardarClave(clave, recordar);
        estado.atletas = d.atletas.filter(function (a) { return a && typeof a === 'object' && typeof a.nombre === 'string' && a.nombre; }).map(preparar);
        estado.clave = clave;
        verLista();
        aplicar(!!entrando);
        if (entrando) {
          aviso('');
          var titulo = $('t-lista');
          titulo.setAttribute('tabindex', '-1');
          titulo.focus({ preventScroll: true });
        } else {
          var h = new Date();
          aviso('Lista actualizada a las ' + ('0' + h.getHours()).slice(-2) + ':' + ('0' + h.getMinutes()).slice(-2) + '.', 'ok');
        }
        return;
      }
      if (res.estado === 401 || res.estado === 429 || res.estado === 503) {
        if (res.estado === 401) guardarClave('', false);
        if (res.estado === 401 && automatica) verClave(MENSAJES['401-guardada'], false);
        else verClave(MENSAJES[res.estado]);
        return;
      }
      var msg = res.estado === 'lento' ? MENSAJES.lento : (res.estado === 0 ? MENSAJES.red : MENSAJES.otro);
      if ($('vista-lista').hidden) verClave(msg, false);
      else aviso(msg, 'error');
    });
  }

  $('form-clave').addEventListener('submit', function (e) {
    e.preventDefault();
    var clave = $('clave').value.trim();
    if (!clave) { verClave('Escribe la clave de acceso.'); return; }
    cargar(clave, $('recordar').checked, true, false);
  });
  $('ver-clave').addEventListener('click', function () {
    var campo = $('clave');
    var ver = campo.type === 'password';
    campo.type = ver ? 'text' : 'password';
    this.setAttribute('aria-pressed', ver ? 'true' : 'false');
    this.setAttribute('aria-label', ver ? 'Ocultar la clave' : 'Mostrar la clave');
    this.querySelector('use').setAttribute('href', ver ? '#i-eye-off' : '#i-eye');
  });
  $('btn-actualizar').addEventListener('click', function () { if (estado.clave) cargar(estado.clave, null, false, false); });
  $('btn-salir').addEventListener('click', function () {
    if (!window.confirm('¿Cerrar el buscador en este teléfono? Tendrás que volver a escribir la clave.')) return;
    guardarClave('', false);
    estado = { atletas: [], filtrados: [], pagina: 1, cargando: false, clave: '', fotos: {} };
    limpiarCampos();
    $('cuerpo').textContent = '';
    $('cuenta').textContent = '';
    $('pagina-txt').textContent = '';
    aviso('');
    $('clave').value = '';
    verClave('');
    $('clave').focus();
  });

  // ---------- Datos ----------
  function texto(v) { return v == null ? '' : String(v); }
  function normalizar(t) {
    return texto(t).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
  }
  function soloDigitos(t) { return texto(t).replace(/\D/g, ''); }

  // La fecha del Sheet viene como «AAAA-MM-DD HH:MM» (la escribe el flujo); por si alguien la edita a mano, también
  // se acepta «DD/MM/AAAA». Devuelve «AAAA-MM-DD» o '' si no es una fecha válida.
  function fechaISO(t) {
    var s = texto(t).trim();
    var a, m, d, r = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (r) { a = r[1]; m = r[2]; d = r[3]; }
    else if ((r = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/))) { a = r[3]; m = ('0' + r[2]).slice(-2); d = ('0' + r[1]).slice(-2); }
    else return '';
    if (+m < 1 || +m > 12 || +d < 1 || +d > 31) return '';
    return a + '-' + m + '-' + d;
  }
  function fechaCorta(iso, original) {
    if (!iso) return texto(original);
    var p = iso.split('-');
    return Number(p[2]) + ' ' + MESES[Number(p[1]) - 1] + ' ' + p[0];
  }

  function preparar(a) {
    var edad = typeof a.edad === 'number' && isFinite(a.edad) ? a.edad : null;
    return {
      datos: a,
      edad: edad,
      iso: fechaISO(a.fecha),
      orden: texto(a.fecha),
      nombre: normalizar(a.nombre),
      rep: normalizar(a.representante),
      digitos: soloDigitos(a.documento),
      telRep: soloDigitos(a.rep_telefono),
      idNorm: normalizar(a.id),
      pago: normalizar(a.pago),
    };
  }

  // ---------- Filtros ----------
  function valores() {
    var min = parseInt($('f-edad-min').value, 10);
    var max = parseInt($('f-edad-max').value, 10);
    min = isNaN(min) ? null : min;
    max = isNaN(max) ? null : max;
    if (min !== null && max !== null && min > max) { var t = min; min = max; max = t; } // rango al revés: se corrige solo
    return {
      texto: normalizar($('f-texto').value),
      posicion: $('f-posicion').value,
      pago: normalizar($('f-pago').value),
      comprobante: $('f-comprobante').value,
      edadMin: min,
      edadMax: max,
      desde: $('f-desde').value,
      hasta: $('f-hasta').value,
      pruebas: $('f-pruebas').checked,
      orden: $('f-orden').value,
      porPagina: parseInt($('f-por-pagina').value, 10) || 20,
    };
  }

  function cumple(p, f) {
    var a = p.datos;
    if (!f.pruebas && a.prueba === true) return false;
    if (f.texto) {
      var dig = soloDigitos(f.texto);
      var porNumero = dig.length >= 3 && (p.digitos.indexOf(dig) !== -1 || p.telRep.indexOf(dig) !== -1);
      var palabras = f.texto.replace(/[^a-z0-9ñ ]/g, ' ').split(' ').filter(Boolean);
      var enNombre = function (base) { return palabras.length > 0 && palabras.every(function (w) { return base.indexOf(w) !== -1; }); };
      var porId = p.idNorm && p.idNorm.indexOf(f.texto) !== -1;
      if (!porNumero && !enNombre(p.nombre) && !enNombre(p.rep) && !porId) return false;
    }
    if (f.posicion && a.posicion !== f.posicion) return false;
    if (f.pago && p.pago.indexOf(f.pago) !== 0) return false;
    if (f.comprobante && a.comprobante !== f.comprobante) return false;
    if (f.edadMin !== null && (p.edad === null || p.edad < f.edadMin)) return false;
    if (f.edadMax !== null && (p.edad === null || p.edad > f.edadMax)) return false;
    if (f.desde && (!p.iso || p.iso < f.desde)) return false;
    if (f.hasta && (!p.iso || p.iso > f.hasta)) return false;
    return true;
  }

  function ordenar(lista, orden) {
    var copia = lista.slice();
    copia.sort(function (x, y) {
      if (orden === 'nombre') return x.nombre.localeCompare(y.nombre, 'es');
      if (orden === 'edad') return (x.edad === null ? 99 : x.edad) - (y.edad === null ? 99 : y.edad) || x.nombre.localeCompare(y.nombre, 'es');
      if (orden === 'antiguo') return x.orden < y.orden ? -1 : x.orden > y.orden ? 1 : (x.datos.fila || 0) - (y.datos.fila || 0);
      return x.orden < y.orden ? 1 : x.orden > y.orden ? -1 : (y.datos.fila || 0) - (x.datos.fila || 0);
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
  function el(tag, clase, contenido) {
    var n = document.createElement(tag);
    if (clase) n.className = clase;
    if (contenido != null) n.textContent = contenido;
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
  function idDrive(id) { return typeof id === 'string' && /^[A-Za-z0-9_-]{10,}$/.test(id) ? id : ''; }
  function enlace(clase, href, ico, contenido, etiqueta) {
    var a = el('a', clase);
    a.href = href; a.target = '_blank'; a.rel = 'noopener noreferrer';
    a.appendChild(icono(ico));
    a.appendChild(el('span', null, contenido));
    if (etiqueta) a.setAttribute('aria-label', etiqueta);
    return a;
  }
  function dato(dl, k, v) {
    v = texto(v);
    if (!v) return;
    dl.appendChild(el('dt', null, k));
    dl.appendChild(el('dd', null, v));
  }
  var ESTADOS_PAGO = Object.create(null);
  ESTADOS_PAGO.adjunto = ['Comprobante recibido', 'chip--ok'];
  ESTADOS_PAGO.pendiente = ['Falta el comprobante', 'chip--falta'];
  ESTADOS_PAGO.no_aplica = ['Paga en la oficina', 'chip--neutro'];

  function fila(p) {
    var a = p.datos;
    var nombreTxt = texto(a.nombre);
    var tr = el('tr');
    if (a.prueba === true) tr.className = 'es-prueba';

    var tdA = el('td', 'col-atleta');
    tdA.setAttribute('data-etiqueta', 'Atleta');
    // Foto (miniatura que llega aparte, solo para la página visible) + nombre y cédula.
    var cabeza = el('div', 'atleta__cabeza');
    var marco = el('span', 'atleta__foto');
    marco.appendChild(icono('i-user'));
    var fotoId = idDrive(a.foto);
    if (fotoId) marco.setAttribute('data-foto-id', fotoId);
    cabeza.appendChild(marco);
    var textos = el('div', 'atleta__textos');
    var nombre = el('p', 'atleta__nombre', nombreTxt);
    if (a.prueba === true) nombre.appendChild(el('span', 'chip chip--prueba', 'Prueba'));
    textos.appendChild(nombre);
    textos.appendChild(el('p', 'atleta__doc', a.documento ? 'Cédula ' + texto(a.documento) : (a.representante ? 'Representante: ' + texto(a.representante) : 'Sin cédula')));
    cabeza.appendChild(textos);
    tdA.appendChild(cabeza);
    var det = el('details', 'atleta__mas');
    det.appendChild(el('summary', null, 'Ver datos'));
    var dl = el('dl', 'atleta__datos');
    dato(dl, 'Nacimiento', a.nacimiento);
    dato(dl, 'Representante', a.representante);
    dato(dl, 'Tel. representante', a.rep_telefono);
    dato(dl, 'N.º de inscripción', a.id);
    det.appendChild(dl);
    tdA.appendChild(det);
    tr.appendChild(tdA);

    // Archivos: la planilla es la acción principal; foto y comprobante van aparte, aunque falte la planilla.
    var tdX = el('td', 'col-archivos');
    tdX.setAttribute('data-etiqueta', 'Planilla');
    var planilla = idDrive(a.planilla);
    if (planilla) {
      tdX.appendChild(enlace('boton boton--chico boton--principal archivo', 'https://drive.google.com/uc?export=download&id=' + encodeURIComponent(planilla),
        'i-download', 'Descargar planilla', 'Descargar la planilla de ' + nombreTxt));
    } else {
      tdX.appendChild(el('p', 'archivos__falta', 'No se guardó la planilla de esta inscripción. Pídele a AutomatizaTech que la recupere.'));
    }
    var extra = el('div', 'archivos__mas');
    if (planilla) extra.appendChild(enlace('archivo-link', 'https://drive.google.com/file/d/' + encodeURIComponent(planilla) + '/view', 'i-file-type-pdf', 'Ver', 'Ver la planilla de ' + nombreTxt));
    var foto = idDrive(a.foto);
    if (foto) extra.appendChild(enlace('archivo-link', 'https://drive.google.com/file/d/' + encodeURIComponent(foto) + '/view', 'i-photo', 'Foto', 'Ver la foto de ' + nombreTxt));
    var comp = idDrive(a.comprobante_id);
    if (comp) extra.appendChild(enlace('archivo-link', 'https://drive.google.com/file/d/' + encodeURIComponent(comp) + '/view', 'i-receipt', 'Comprobante', 'Ver el comprobante de ' + nombreTxt));
    if (extra.childNodes.length) tdX.appendChild(extra);

    var tdE = el('td', 'col-edad', p.edad === null ? '—' : p.edad + ' años');
    tdE.setAttribute('data-etiqueta', 'Edad');
    var tdP = el('td', 'col-posicion', texto(a.posicion) || '—');
    tdP.setAttribute('data-etiqueta', 'Posición');
    var tdF = el('td', 'col-fecha', fechaCorta(p.iso, a.fecha) || '—');
    tdF.setAttribute('data-etiqueta', 'Inscripción');
    var tdG = el('td', 'col-pago');
    tdG.setAttribute('data-etiqueta', 'Pago');
    var forma = texto(a.pago).split('·')[0].trim();
    tdG.appendChild(el('p', 'pago__forma', forma || '—'));
    var est = ESTADOS_PAGO[a.comprobante];
    if (est) tdG.appendChild(el('span', 'chip ' + est[1], est[0]));

    // Orden en el DOM = orden de la tabla de escritorio; en el celular el CSS sube la planilla junto al nombre.
    tr.appendChild(tdE);
    tr.appendChild(tdP);
    tr.appendChild(tdF);
    tr.appendChild(tdG);
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
    cargarFotos(visibles);

    var reales = estado.atletas.filter(function (p) { return p.datos.prueba !== true; }).length;
    var pruebas = estado.atletas.length - reales;
    var hayFiltro = !!(f.texto || f.posicion || f.pago || f.comprobante || f.desde || f.hasta || f.edadMin !== null || f.edadMax !== null);
    var txt;
    if (hayFiltro) txt = total + (total === 1 ? ' atleta encontrado' : ' atletas encontrados') + ' de ' + reales + ' inscritos';
    else txt = reales + (reales === 1 ? ' atleta inscrito' : ' atletas inscritos');
    if (f.pruebas && pruebas) txt += ' (se muestran también ' + pruebas + (pruebas === 1 ? ' de prueba)' : ' de prueba)');
    $('cuenta').textContent = txt;
    $('btn-ver-resultados').textContent = total === 1 ? 'Ver 1 atleta' : 'Ver ' + total + ' atletas';

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

  // ---------- Fotos ----------
  // Se piden al flujo solo las de la página visible (de a 24, el tope del flujo) y quedan en memoria mientras la
  // página está abierta; nunca en el almacenamiento del teléfono. Solo se aceptan data URL de imagen.
  var FOTO_OK = /^data:image\/(jpeg|png|webp);base64,[A-Za-z0-9+\/=]+$/;
  function pintarFotos() {
    document.querySelectorAll('#cuerpo [data-foto-id]').forEach(function (marco) {
      var v = estado.fotos[marco.getAttribute('data-foto-id')];
      if (!v || marco.querySelector('img')) return;
      var img = document.createElement('img');
      img.alt = ''; img.width = 56; img.height = 56; img.decoding = 'async';
      img.src = v;
      marco.appendChild(img);
      marco.classList.add('con-foto');
    });
  }
  function cargarFotos(visibles) {
    var faltan = [];
    visibles.forEach(function (p) {
      var id = idDrive(p.datos.foto);
      if (id && !(id in estado.fotos) && faltan.indexOf(id) === -1) faltan.push(id);
    });
    pintarFotos();
    if (!faltan.length || !estado.clave) return;
    faltan.forEach(function (id) { estado.fotos[id] = null; }); // pendiente: no se vuelve a pedir
    var clave = estado.clave;
    for (var i = 0; i < faltan.length; i += 24) {
      (function (lote) {
        fetch(ENDPOINT, {
          method: 'POST', headers: { 'Content-Type': 'application/json' }, cache: 'no-store', credentials: 'omit',
          referrerPolicy: 'no-referrer', body: JSON.stringify({ clave: clave, accion: 'fotos', ids: lote }),
        }).then(function (r) { return r.ok ? r.json() : {}; }).then(function (d) {
          if (estado.clave !== clave) return; // se salió o cambió la clave mientras tanto
          var f = (d && d.fotos) || {};
          lote.forEach(function (id) {
            var v = Object.prototype.hasOwnProperty.call(f, id) ? f[id] : '';
            estado.fotos[id] = typeof v === 'string' && FOTO_OK.test(v) ? v : false;
          });
          pintarFotos();
        }, function () {
          lote.forEach(function (id) { if (estado.fotos[id] === null) delete estado.fotos[id]; }); // se reintenta después
        });
      })(faltan.slice(i, i + 24));
    }
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

  function limpiarCampos() {
    $('f-texto').value = '';
    ['f-posicion', 'f-pago', 'f-comprobante'].forEach(function (id) { $(id).value = ''; });
    ['f-edad-min', 'f-edad-max', 'f-desde', 'f-hasta'].forEach(function (id) { $(id).value = ''; });
    $('f-pruebas').checked = false;
    contarFiltros(valores());
  }
  function limpiar() {
    limpiarCampos();
    aplicar(true);
    anunciar('Filtros limpios.');
  }
  $('btn-limpiar').addEventListener('click', limpiar);
  $('btn-limpiar-2').addEventListener('click', limpiar);

  // «Ver resultados» (celular): cierra el panel y lleva a la lista.
  $('btn-ver-resultados').addEventListener('click', function () {
    $('filtros-mas').open = false;
    irALista();
  });

  function irALista() {
    var cabeza = $('t-lista');
    cabeza.setAttribute('tabindex', '-1');
    cabeza.focus({ preventScroll: true });
    cabeza.scrollIntoView({ block: 'start' });
  }
  function irA(n) {
    estado.pagina = n;
    dibujar(valores());
    irALista();
  }
  $('btn-anterior').addEventListener('click', function () { if (estado.pagina > 1) irA(estado.pagina - 1); });
  $('btn-siguiente').addEventListener('click', function () { irA(estado.pagina + 1); });

  // En escritorio los filtros quedan abiertos; en el celular, cerrados para que se vea la lista.
  if (window.matchMedia('(min-width: 900px)').matches) $('filtros-mas').open = true;

  // ---------- Inicio: si la clave está guardada (y el endpoint es el oficial), entra directo ----------
  var guardada = ENDPOINT === ENDPOINT_OFICIAL ? leerClave() : '';
  if (guardada) cargar(guardada, null, true, true);
  else verClave('');
})();
