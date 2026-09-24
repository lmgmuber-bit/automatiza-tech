/*
 * paginador.js — paginación en el navegador para las grillas largas del admin (2026-09-15).
 *
 * Se incluye INLINE desde album.php con un require de PHP dentro de una etiqueta script, igual
 * que los estilos: el admin no carga archivos aparte. Al final se exporta también para Node,
 * así la aritmética de páginas tiene prueba (tests/frontend/paginador.test.mjs).
 *
 * OJO: PHP interpreta etiquetas de apertura de PHP también dentro de este archivo al incluirlo.
 * Este comentario decía la etiqueta literal y el require se llamaba a sí mismo sin fin (2026-09-15).
 * Nada de "<" seguido de "?" en este archivo.
 *
 * Qué hace: toma un <ol> con sus <li>, esconde los que no caen en la página actual
 * (clase .pag-oculta, con !important para que ningún display de clase la pise), y pone una
 * barra arriba y otra abajo con "1–20 de 84", los números de página y el selector de
 * cuántos por página (10/20/50/100). Los <li> siguen TODOS en el DOM: las casillas marcadas
 * en otra página siguen marcadas y el arrastre para reordenar sigue viendo el orden entero.
 *
 * Se recuerda el tamaño en localStorage (por sección) y la página en sessionStorage (por
 * sección y fiesta): después de aprobar una foto en la página 5, la recarga vuelve a la 5.
 */
(function (root) {
  'use strict';

  var TAMANOS = [10, 20, 50, 100];

  /** Aritmética pura: cuántas páginas, cuál es válida y qué rango (1-based) muestra. */
  function ccPaginas(total, porPagina, actual) {
    total = Math.max(0, parseInt(total, 10) || 0);
    porPagina = TAMANOS.indexOf(parseInt(porPagina, 10)) === -1 ? 20 : parseInt(porPagina, 10);
    var paginas = Math.max(1, Math.ceil(total / porPagina));
    actual = Math.min(paginas, Math.max(1, parseInt(actual, 10) || 1));
    var desde = total === 0 ? 0 : (actual - 1) * porPagina + 1;
    var hasta = total === 0 ? 0 : Math.min(total, actual * porPagina);
    return { total: total, porPagina: porPagina, paginas: paginas, actual: actual, desde: desde, hasta: hasta };
  }

  /**
   * Qué botones de página dibujar: siempre la primera y la última, y una ventana alrededor
   * de la actual; los huecos se marcan con '…'. Con pocas páginas van todas.
   */
  function ccRangoPaginas(paginas, actual, ancho) {
    ancho = ancho || 7;
    if (paginas <= ancho) {
      var todas = [];
      for (var i = 1; i <= paginas; i++) { todas.push(i); }
      return todas;
    }
    var lado = Math.floor((ancho - 3) / 2); // primera, última y la actual ya ocupan 3
    var ini = Math.max(2, actual - lado);
    var fin = Math.min(paginas - 1, actual + lado);
    if (actual - lado < 2) { fin = Math.min(paginas - 1, fin + (2 - (actual - lado))); }
    if (actual + lado > paginas - 1) { ini = Math.max(2, ini - ((actual + lado) - (paginas - 1))); }
    var salida = [1];
    if (ini > 2) { salida.push('…'); }
    for (var p = ini; p <= fin; p++) { salida.push(p); }
    if (fin < paginas - 1) { salida.push('…'); }
    salida.push(paginas);
    return salida;
  }

  function leer(almacen, clave, porDefecto) {
    try { var v = root[almacen].getItem(clave); return v === null ? porDefecto : v; } catch (e) { return porDefecto; }
  }
  function guardar(almacen, clave, valor) {
    try { root[almacen].setItem(clave, String(valor)); } catch (e) { /* sin almacenamiento */ }
  }

  /**
   * Pagina una lista del DOM. opciones: { clave, etiqueta, porDefecto }.
   * Devuelve { irA(n), refrescar() } o null si la lista no existe o no tiene ítems.
   */
  function ccPaginar(lista, opciones) {
    if (!lista || !root.document) { return null; }
    opciones = opciones || {};
    var doc = root.document;
    var items = Array.prototype.slice.call(lista.children);
    if (!items.length) { return null; }
    var clave = opciones.clave || lista.id || 'lista';
    var etiqueta = opciones.etiqueta || 'elementos';
    var porPagina = parseInt(leer('localStorage', 'cc-pag-tam:' + clave.split(':')[0], opciones.porDefecto || 20), 10);
    var actual = parseInt(leer('sessionStorage', 'cc-pag-pag:' + clave, 1), 10);

    function barra() {
      var b = doc.createElement('div');
      b.className = 'pag-barra';
      b.innerHTML = '<span class="pag-info" aria-live="polite"></span>'
        + '<nav class="pag-paginas" aria-label="Páginas"></nav>'
        + '<label class="pag-tam">Por página <select aria-label="Cuántos por página"></select></label>';
      var sel = b.querySelector('select');
      TAMANOS.forEach(function (t) {
        var o = doc.createElement('option');
        o.value = String(t); o.textContent = String(t);
        sel.appendChild(o);
      });
      sel.addEventListener('change', function () {
        porPagina = parseInt(sel.value, 10);
        guardar('localStorage', 'cc-pag-tam:' + clave.split(':')[0], porPagina);
        irA(1);
      });
      b.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-pagina]');
        if (!btn || btn.disabled) { return; }
        irA(parseInt(btn.getAttribute('data-pagina'), 10));
        // La barra de abajo queda fuera de vista al cambiar de página: se vuelve al inicio de la lista.
        if (btn.closest('.pag-barra') === abajo) { lista.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
      });
      return b;
    }

    var arriba = barra();
    var abajo = barra();
    lista.parentNode.insertBefore(arriba, lista);
    lista.parentNode.insertBefore(abajo, lista.nextSibling);

    function pintar(b, p) {
      b.querySelector('.pag-info').textContent = p.total === 0
        ? 'Sin ' + etiqueta
        : (p.desde === 1 && p.hasta === p.total
            ? p.total + ' ' + etiqueta
            : p.desde + '–' + p.hasta + ' de ' + p.total + ' ' + etiqueta);
      b.querySelector('select').value = String(p.porPagina);
      var nav = b.querySelector('.pag-paginas');
      nav.innerHTML = '';
      if (p.paginas <= 1) { nav.hidden = true; return; }
      nav.hidden = false;
      function boton(texto, pagina, etiquetaAria, deshabilitado, esActual) {
        var x = doc.createElement('button');
        x.type = 'button'; x.className = 'pag-btn'; x.textContent = texto;
        x.setAttribute('data-pagina', String(pagina));
        if (etiquetaAria) { x.setAttribute('aria-label', etiquetaAria); }
        if (deshabilitado) { x.disabled = true; }
        if (esActual) { x.setAttribute('aria-current', 'page'); }
        nav.appendChild(x);
      }
      boton('‹', p.actual - 1, 'Página anterior', p.actual === 1, false);
      ccRangoPaginas(p.paginas, p.actual).forEach(function (n) {
        if (n === '…') {
          var s = doc.createElement('span'); s.className = 'pag-puntos'; s.textContent = '…'; nav.appendChild(s);
        } else {
          boton(String(n), n, 'Página ' + n, false, n === p.actual);
        }
      });
      boton('›', p.actual + 1, 'Página siguiente', p.actual === p.paginas, false);
    }

    function refrescar() {
      items = Array.prototype.slice.call(lista.children);
      var p = ccPaginas(items.length, porPagina, actual);
      actual = p.actual;
      items.forEach(function (li, i) {
        li.classList.toggle('pag-oculta', i < p.desde - 1 || i > p.hasta - 1);
      });
      pintar(arriba, p);
      pintar(abajo, p);
      guardar('sessionStorage', 'cc-pag-pag:' + clave, actual);
    }

    function irA(n) { actual = n; refrescar(); }

    refrescar();
    return { irA: irA, refrescar: refrescar };
  }

  var api = { ccPaginas: ccPaginas, ccRangoPaginas: ccRangoPaginas, ccPaginar: ccPaginar, TAMANOS: TAMANOS };
  if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
  root.ccPaginador = api;
})(typeof window !== 'undefined' ? window : globalThis);
